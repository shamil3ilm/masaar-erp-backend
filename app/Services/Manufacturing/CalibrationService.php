<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CalibrationCertificate;
use App\Models\Manufacturing\CalibrationEquipment;
use App\Models\Manufacturing\CalibrationOrder;
use App\Models\Manufacturing\CalibrationPlan;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CalibrationService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    // -------------------------------------------------------------------------
    // Equipment
    // -------------------------------------------------------------------------

    /**
     * The organization's equipment by name, optionally filtered by category,
     * whether it is active and a search on name, code or serial number.
     *
     * @param  array{category?: mixed, active_only?: bool, search?: mixed}  $filters
     */
    public function listEquipment(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return CalibrationEquipment::where('organization_id', $organizationId)
            ->with('responsiblePerson')
            ->when(isset($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(! empty($filters['active_only']), fn ($q) => $q->where('is_active', true))
            ->when(isset($filters['search']), function ($q) use ($filters): void {
                $search = '%' . $filters['search'] . '%';
                $q->where(function ($q) use ($search): void {
                    $q->where('name', 'like', $search)
                        ->orWhere('equipment_code', 'like', $search)
                        ->orWhere('serial_number', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createEquipment(int $organizationId, array $data): CalibrationEquipment
    {
        return CalibrationEquipment::create([
            'organization_id' => $organizationId,
            ...$data,
        ]);
    }

    public function findEquipment(int $organizationId, int $id): ?CalibrationEquipment
    {
        return CalibrationEquipment::where('organization_id', $organizationId)->find($id);
    }

    /**
     * Equipment with its plans and its five most recently scheduled orders.
     */
    public function findEquipmentForDisplay(int $organizationId, int $id): ?CalibrationEquipment
    {
        return CalibrationEquipment::where('organization_id', $organizationId)
            ->with(['responsiblePerson', 'calibrationPlans', 'calibrationOrders' => function ($q) {
                $q->orderByDesc('scheduled_date')->limit(5);
            }])
            ->find($id);
    }

    public function updateEquipment(CalibrationEquipment $equipment, array $data): CalibrationEquipment
    {
        $equipment->update($data);

        return $equipment->fresh();
    }

    // -------------------------------------------------------------------------
    // Plans
    // -------------------------------------------------------------------------

    /**
     * @param  array{equipment_id?: mixed, active_only?: bool}  $filters
     */
    public function listPlans(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return CalibrationPlan::where('organization_id', $organizationId)
            ->with('equipment')
            ->when(isset($filters['equipment_id']), fn ($q) => $q->where('calibration_equipment_id', $filters['equipment_id']))
            ->when(! empty($filters['active_only']), fn ($q) => $q->where('is_active', true))
            ->orderBy('plan_code')
            ->paginate($perPage);
    }

    public function createPlan(int $organizationId, array $data): CalibrationPlan
    {
        $plan = CalibrationPlan::create([
            'organization_id' => $organizationId,
            ...$data,
        ]);

        return $plan->load('equipment');
    }

    /**
     * A plan with its equipment and its ten most recently scheduled orders.
     */
    public function findPlanForDisplay(int $organizationId, int $id): ?CalibrationPlan
    {
        return CalibrationPlan::where('organization_id', $organizationId)
            ->with(['equipment', 'calibrationOrders' => function ($q) {
                $q->orderByDesc('scheduled_date')->limit(10);
            }])
            ->find($id);
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    /**
     * @param  array{status?: mixed, equipment_id?: mixed, result?: mixed}  $filters
     */
    public function listOrders(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return CalibrationOrder::where('organization_id', $organizationId)
            ->with(['equipment', 'plan', 'calibratedBy'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['equipment_id']), fn ($q) => $q->where('calibration_equipment_id', $filters['equipment_id']))
            ->when(isset($filters['result']), fn ($q) => $q->where('result', $filters['result']))
            ->orderByDesc('scheduled_date')
            ->paginate($perPage);
    }

    /**
     * One of the organization's orders, or null.
     *
     * @param  array<int, string>  $with
     */
    public function findOrder(int $organizationId, int $id, array $with = []): ?CalibrationOrder
    {
        return CalibrationOrder::where('organization_id', $organizationId)
            ->with($with)
            ->find($id);
    }

    /**
     * An order's certificates, most recently issued first.
     */
    public function certificatesOf(CalibrationOrder $order): Collection
    {
        return $order->certificates()->orderByDesc('issued_date')->get();
    }

    /**
     * Create a planned calibration order for a piece of equipment, outside its
     * plan's schedule.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrder(int $organizationId, array $data): CalibrationOrder
    {
        return CalibrationOrder::create([
            'organization_id' => $organizationId,
            'order_number'    => $this->nextOrderNumber($organizationId),
            'status'          => CalibrationOrder::STATUS_PLANNED,
            ...$data,
        ]);
    }

    /**
     * Schedule the next calibration order from a calibration plan.
     */
    public function createCalibrationOrder(CalibrationPlan $plan): CalibrationOrder
    {
        return DB::transaction(function () use ($plan): CalibrationOrder {
            $orgId = $plan->organization_id;

            // Determine scheduled date: based on last completed order or today
            $lastCompleted = CalibrationOrder::where('organization_id', $orgId)
                ->where('calibration_equipment_id', $plan->calibration_equipment_id)
                ->where('status', CalibrationOrder::STATUS_COMPLETED)
                ->orderByDesc('completed_date')
                ->first();

            $baseDate = $lastCompleted?->completed_date ?? now()->toDateTimeImmutable();
            $nextDate = $plan->calculateNextDueDate($baseDate);
            $orderNumber = $this->nextOrderNumber($orgId);

            return CalibrationOrder::create([
                'organization_id' => $orgId,
                'calibration_equipment_id' => $plan->calibration_equipment_id,
                'calibration_plan_id' => $plan->id,
                'order_number' => $orderNumber,
                'scheduled_date' => $nextDate->format('Y-m-d'),
                'status' => CalibrationOrder::STATUS_PLANNED,
                'external_lab' => $plan->external_lab,
            ]);
        });
    }

    /**
     * Record calibration results, issue a certificate, and schedule the next order.
     *
     * The status is checked on the locked order, so a double submit completes
     * it, issues its certificate and schedules the next order once.
     */
    public function completeCalibration(CalibrationOrder $order, array $results): CalibrationOrder
    {
        return $order->lockForTransition(function (CalibrationOrder $order) use ($results): CalibrationOrder {
            if (! $order->canBeCompleted()) {
                throw new InvalidArgumentException('Only planned or in-progress orders can be completed.');
            }

            $completedDate = now()->toDateString();
            $plan = $order->calibration_plan_id !== null ? $order->plan()->first() : null;

            // Determine next calibration date
            $baseDate = \DateTimeImmutable::createFromFormat('Y-m-d', $completedDate);
            $nextDate = $plan?->calculateNextDueDate($baseDate)->format('Y-m-d');

            $order->update([
                'status' => CalibrationOrder::STATUS_COMPLETED,
                'completed_date' => $completedDate,
                'result' => $results['result'] ?? null,
                'actual_measurement' => $results['actual_measurement'] ?? null,
                'notes' => $results['notes'] ?? $order->notes,
                'calibrated_by' => $results['calibrated_by'] ?? $order->calibrated_by,
                'next_calibration_date' => $nextDate,
            ]);

            // Issue certificate if provided
            if (! empty($results['certificate'])) {
                $cert = $results['certificate'];
                CalibrationCertificate::create([
                    'organization_id' => $order->organization_id,
                    'calibration_order_id' => $order->id,
                    'certificate_number' => $cert['certificate_number'],
                    'issued_date' => $cert['issued_date'] ?? $completedDate,
                    'valid_until' => $cert['valid_until'] ?? $nextDate ?? $completedDate,
                    'issued_by' => $cert['issued_by'] ?? null,
                    'accreditation_body' => $cert['accreditation_body'] ?? null,
                    'certificate_data' => $cert['certificate_data'] ?? null,
                ]);
            }

            // Auto-schedule next order if a plan exists
            if ($plan?->is_active && $nextDate !== null) {
                $this->createCalibrationOrder($plan);
            }

            return $order;
        });
    }

    /**
     * Get all equipment with overdue calibration for an organization.
     *
     * Uses a single JOIN + DISTINCT instead of loading all orders and
     * de-duplicating equipment in PHP memory.
     */
    public function getOverdueEquipment(int $organizationId): Collection
    {
        $equipmentIds = CalibrationOrder::where('organization_id', $organizationId)
            ->whereIn('status', [CalibrationOrder::STATUS_PLANNED, CalibrationOrder::STATUS_OVERDUE])
            ->where('scheduled_date', '<', now()->toDateString())
            ->distinct()
            ->pluck('calibration_equipment_id');

        if ($equipmentIds->isEmpty()) {
            return collect();
        }

        return CalibrationEquipment::whereIn('id', $equipmentIds)->get();
    }

    /**
     * Get upcoming calibration orders within a given number of days.
     */
    public function getUpcomingCalibrations(int $organizationId, int $days = 30): Collection
    {
        return CalibrationOrder::with(['equipment', 'plan'])
            ->where('organization_id', $organizationId)
            ->where('status', CalibrationOrder::STATUS_PLANNED)
            ->whereBetween('scheduled_date', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Check whether a piece of equipment has a valid, completed calibration.
     */
    public function isEquipmentCalibrated(int $equipmentId): bool
    {
        $lastCompleted = CalibrationOrder::where('calibration_equipment_id', $equipmentId)
            ->where('status', CalibrationOrder::STATUS_COMPLETED)
            ->where('result', CalibrationOrder::RESULT_PASS)
            ->whereNotNull('next_calibration_date')
            ->where('next_calibration_date', '>=', now()->toDateString())
            ->exists();

        return $lastCompleted;
    }

    /**
     * Auto-generate due calibration orders for all active plans in an organization.
     *
     * @return int Number of orders generated.
     */
    public function generateCalibrationOrders(int $organizationId): int
    {
        $plans = CalibrationPlan::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('equipment')
            ->get();

        $generated = 0;

        foreach ($plans as $plan) {
            // Skip if a pending/in-progress order already exists for this plan
            $exists = CalibrationOrder::where('calibration_plan_id', $plan->id)
                ->whereIn('status', [CalibrationOrder::STATUS_PLANNED, CalibrationOrder::STATUS_IN_PROGRESS])
                ->exists();

            if ($exists) {
                continue;
            }

            // Check whether the last completed order's next_calibration_date has arrived
            $lastCompleted = CalibrationOrder::where('calibration_plan_id', $plan->id)
                ->where('status', CalibrationOrder::STATUS_COMPLETED)
                ->orderByDesc('completed_date')
                ->first();

            $shouldGenerate = $lastCompleted === null
                || ($lastCompleted->next_calibration_date !== null
                    && $lastCompleted->next_calibration_date <= now()->addDays(7)->toDateString());

            if ($shouldGenerate) {
                $this->createCalibrationOrder($plan);
                $generated++;
            }
        }

        return $generated;
    }

    private function nextOrderNumber(int $organizationId): string
    {
        return $this->numberGenerator->generate(CalibrationOrder::NUMBER_SEQUENCE, CalibrationOrder::NUMBER_FORMAT, $organizationId);
    }
}
