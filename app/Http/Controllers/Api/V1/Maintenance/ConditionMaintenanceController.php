<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceConditionRule;
use App\Services\Maintenance\ConditionBasedMaintenanceService;
use App\Services\Maintenance\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionMaintenanceController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ConditionBasedMaintenanceService $cbmService,
        private readonly EquipmentService $equipmentService,
    ) {}

    /**
     * Record a new condition measurement.
     */
    public function recordMeasurement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', 'integer', $this->ownedBy('equipment')],
            'measurement_point' => 'required|string|max:100',
            'measurement_value' => 'required|numeric',
            'unit_of_measure' => 'nullable|string|max:20',
            'measured_at' => 'nullable|date',
        ]);

        try {
            $measurement = $this->cbmService->recordMeasurement(
                $request->user()->organization_id,
                $request->user()->id,
                $validated
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'MEASUREMENT_ERROR', 422);
        }

        return $this->success($measurement, 'Measurement recorded.', 201);
    }

    // -------------------------------------------------------------------------
    // Condition Rules — CRUD
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->cbmService->paginateRules([
            'equipment_id' => $request->input('equipment_id'),
            'is_active' => $request->input('is_active'),
        ], $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_name' => 'required|string|max:100',
            'equipment_id' => ['required', 'integer', $this->ownedBy('equipment')],
            'measurement_point' => 'required|string|max:100',
            'condition_operator' => 'required|in:greater_than,less_than,equals,between',
            'threshold_value' => 'required|numeric',
            'threshold_value_to' => 'nullable|numeric',
            'unit_of_measure' => 'nullable|string|max:20',
            'trigger_action' => 'required|in:create_order,notify,both',
            'maintenance_type' => 'required|in:inspection,repair,overhaul,replacement',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->success($this->cbmService->createRule($validated), 'Condition rule created.', 201);
    }

    public function show(MaintenanceConditionRule $conditionRule): JsonResponse
    {
        return $this->success($conditionRule->load('equipment'));
    }

    public function update(Request $request, MaintenanceConditionRule $conditionRule): JsonResponse
    {
        $validated = $request->validate([
            'rule_name' => 'sometimes|string|max:100',
            'measurement_point' => 'sometimes|string|max:100',
            'condition_operator' => 'sometimes|in:greater_than,less_than,equals,between',
            'threshold_value' => 'sometimes|numeric',
            'threshold_value_to' => 'nullable|numeric',
            'unit_of_measure' => 'nullable|string|max:20',
            'trigger_action' => 'sometimes|in:create_order,notify,both',
            'maintenance_type' => 'sometimes|in:inspection,repair,overhaul,replacement',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success($this->cbmService->updateRule($conditionRule, $validated), 'Condition rule updated.');
    }

    public function destroy(MaintenanceConditionRule $conditionRule): JsonResponse
    {
        $this->cbmService->deleteRule($conditionRule);

        return $this->success(null, 'Condition rule deleted.');
    }

    // -------------------------------------------------------------------------
    // Spare Parts
    // -------------------------------------------------------------------------

    public function spareParts(Request $request, int $equipmentId): JsonResponse
    {
        $equipment = $this->equipmentService->findOrFail($request->user()->organization_id, $equipmentId);

        return $this->success($this->cbmService->spareParts($equipment));
    }

    /**
     * Add or update a spare part for an equipment.
     */
    public function addSparePart(Request $request, int $equipmentId): JsonResponse
    {
        $equipment = $this->equipmentService->findOrFail($request->user()->organization_id, $equipmentId);

        $validated = $request->validate([
            'product_id' => ['required', $this->ownedBy('products')],
            'recommended_stock_qty' => 'required|numeric|min:0',
            'is_critical' => 'nullable|boolean',
            'lead_time_days' => 'nullable|numeric|min:0',
        ]);

        try {
            $part = $this->cbmService->addSparePart($equipment, $validated);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'SPARE_PART_ERROR', 422);
        }

        return $this->success($part, 'Spare part linked to equipment.', 201);
    }

    public function sparePartsAvailability(Request $request, int $equipmentId): JsonResponse
    {
        $equipment = $this->equipmentService->findOrFail($request->user()->organization_id, $equipmentId);

        return $this->success($this->cbmService->checkSparePartsAvailability($equipment));
    }
}
