<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Inventory\StockLevel;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\EquipmentSparePart;
use App\Models\Maintenance\MaintenanceConditionRule;
use App\Models\Maintenance\MaintenanceMeasurement;
use App\Models\Maintenance\MaintenanceOrder;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Condition-based maintenance: rules on measurement points, measurements
 * checked against them, and the spare parts equipment should have in stock.
 *
 * Rules and stock are read for the organization the equipment belongs to.
 * Spare part rows have no organization of their own and are reached through
 * their equipment.
 */
class ConditionBasedMaintenanceService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Rules in equipment order. is_active filters only when it is given.
     *
     * @param  array{equipment_id?: mixed, is_active?: mixed}  $filters
     */
    public function paginateRules(array $filters, int $perPage): LengthAwarePaginator
    {
        $isActive = $filters['is_active'] ?? null;

        return MaintenanceConditionRule::query()
            ->with('equipment')
            ->when($filters['equipment_id'] ?? null, fn ($query, $id) => $query->forEquipment((int) $id))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->orderBy('equipment_id')
            ->paginate($perPage);
    }

    public function createRule(array $data): MaintenanceConditionRule
    {
        return MaintenanceConditionRule::create($data);
    }

    public function updateRule(MaintenanceConditionRule $rule, array $data): MaintenanceConditionRule
    {
        $rule->update($data);

        return $rule->fresh();
    }

    public function deleteRule(MaintenanceConditionRule $rule): void
    {
        $rule->delete();
    }

    /**
     * Record a measurement on the organization's equipment and act on the
     * first of the organization's active rules it breaches.
     *
     * @param  array{equipment_id: int, measurement_point: string, measurement_value: float|int|string, unit_of_measure?: ?string, measured_at?: ?string}  $data
     */
    public function recordMeasurement(int $organizationId, int $userId, array $data): MaintenanceMeasurement
    {
        return DB::transaction(function () use ($organizationId, $userId, $data): MaintenanceMeasurement {
            $equipmentId = (int) $data['equipment_id'];
            $measurementPoint = $data['measurement_point'];
            $value = (float) $data['measurement_value'];

            $breachedRule = $this->evaluateRules($organizationId, $equipmentId, $measurementPoint, $value);

            $measurement = MaintenanceMeasurement::create([
                'organization_id' => $organizationId,
                'equipment_id' => $equipmentId,
                'measurement_point' => $measurementPoint,
                'measurement_value' => $value,
                'unit_of_measure' => $data['unit_of_measure'] ?? null,
                'measured_at' => $data['measured_at'] ?? now(),
                'recorded_by' => $userId,
                'threshold_breached' => $breachedRule !== null,
                'triggered_rule_id' => $breachedRule?->id,
            ]);

            if ($breachedRule !== null) {
                $this->handleBreach($measurement, $breachedRule);
            }

            return $measurement;
        });
    }

    /**
     * The first of the organization's active rules for the equipment and
     * measurement point that the value breaches, or null.
     */
    public function evaluateRules(int $organizationId, int $equipmentId, string $measurementPoint, float $value): ?MaintenanceConditionRule
    {
        return MaintenanceConditionRule::forOrganization($organizationId)
            ->active()
            ->forEquipment($equipmentId)
            ->forMeasurementPoint($measurementPoint)
            ->get()
            ->first(fn (MaintenanceConditionRule $rule): bool => $rule->isBreached($value));
    }

    public function spareParts(Equipment $equipment): Collection
    {
        return EquipmentSparePart::with('product')
            ->forEquipment($equipment->id)
            ->get();
    }

    /**
     * Add a spare part to the equipment, or update the one it has for the
     * product, with the organization's current stock of the product.
     */
    public function addSparePart(Equipment $equipment, array $data): EquipmentSparePart
    {
        $productId = (int) $data['product_id'];

        return EquipmentSparePart::updateOrCreate(
            [
                'equipment_id' => $equipment->id,
                'product_id' => $productId,
            ],
            [
                'recommended_stock_qty' => $data['recommended_stock_qty'] ?? 0,
                'current_stock_qty' => $this->stockOnHand($equipment->organization_id, $productId),
                'is_critical' => $data['is_critical'] ?? false,
                'lead_time_days' => $data['lead_time_days'] ?? 0,
            ]
        )->load('product');
    }

    /**
     * Refresh each spare part's stock from the organization's stock levels and
     * report which parts fall short of their recommended quantity.
     *
     * @return array{equipment_id: int, total_parts: int, sufficient_count: int, shortfall_count: int, critical_shortfall: int, parts: Collection}
     */
    public function checkSparePartsAvailability(Equipment $equipment): array
    {
        $parts = $this->spareParts($equipment);

        foreach ($parts as $part) {
            $current = $this->stockOnHand($equipment->organization_id, $part->product_id);
            if ((float) $part->current_stock_qty !== $current) {
                $part->update(['current_stock_qty' => $current]);
                $part->current_stock_qty = $current;
            }
        }

        $shortfalls = $parts->filter(fn ($p) => ! $p->isStockSufficient());
        $criticalShortfalls = $shortfalls->filter(fn ($p) => $p->is_critical);

        return [
            'equipment_id' => $equipment->id,
            'total_parts' => $parts->count(),
            'sufficient_count' => $parts->count() - $shortfalls->count(),
            'shortfall_count' => $shortfalls->count(),
            'critical_shortfall' => $criticalShortfalls->count(),
            'parts' => $parts->map(fn ($p) => [
                'product_id' => $p->product_id,
                'product_name' => $p->product?->name,
                'recommended_stock_qty' => (float) $p->recommended_stock_qty,
                'current_stock_qty' => (float) $p->current_stock_qty,
                'deficit' => $p->getStockDeficit(),
                'is_critical' => $p->is_critical,
                'lead_time_days' => (float) $p->lead_time_days,
                'sufficient' => $p->isStockSufficient(),
            ])->values(),
        ];
    }

    private function handleBreach(MaintenanceMeasurement $measurement, MaintenanceConditionRule $rule): void
    {
        if ($rule->shouldCreateOrder()) {
            $this->createMaintenanceOrder($measurement, $rule);
        }

        if ($rule->shouldNotify()) {
            $this->logBreach($measurement, $rule);
        }
    }

    /**
     * Open a corrective order for the breach. A failure is logged and the
     * measurement is still recorded.
     */
    private function createMaintenanceOrder(MaintenanceMeasurement $measurement, MaintenanceConditionRule $rule): void
    {
        try {
            $orgId = $measurement->organization_id;

            MaintenanceOrder::create([
                'organization_id' => $orgId,
                'order_number' => $this->numberGenerator->generate(MaintenanceOrder::NUMBER_SEQUENCE, MaintenanceOrder::NUMBER_FORMAT, $orgId),
                'equipment_id' => $measurement->equipment_id,
                'order_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'priority' => MaintenanceOrder::PRIORITY_HIGH,
                'status' => MaintenanceOrder::STATUS_OPEN,
                'description' => "Auto-generated: rule '{$rule->rule_name}' breached. "
                    ."Measurement: {$measurement->measurement_value} {$measurement->unit_of_measure} "
                    ."at {$measurement->measurement_point}.",
                'scheduled_start' => now(),
                'created_by' => $measurement->recorded_by,
            ]);
        } catch (\Throwable $e) {
            Log::error('ConditionBasedMaintenanceService: failed to create maintenance order.', [
                'rule_id' => $rule->id,
                'measurement_id' => $measurement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** No notification channel exists for breaches; the breach is logged. */
    private function logBreach(MaintenanceMeasurement $measurement, MaintenanceConditionRule $rule): void
    {
        Log::info('ConditionBasedMaintenanceService: threshold breached, notification queued.', [
            'rule_id' => $rule->id,
            'equipment_id' => $measurement->equipment_id,
            'measurement_point' => $measurement->measurement_point,
            'value' => $measurement->measurement_value,
        ]);
    }

    /** The organization's stock of a product across all of its warehouses. */
    private function stockOnHand(int $organizationId, int $productId): float
    {
        return (float) StockLevel::forOrganization($organizationId)
            ->where('product_id', $productId)
            ->sum('quantity');
    }
}
