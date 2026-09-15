<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\EquipmentCategory;
use App\Models\Maintenance\FunctionalLocation;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenancePlan;
use App\Services\Maintenance\EquipmentCategoryService;
use App\Services\Maintenance\EquipmentService;
use App\Services\Maintenance\FunctionalLocationService;
use App\Services\Maintenance\MaintenancePlanService;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly MaintenanceService $maintenanceService,
        private readonly FunctionalLocationService $locationService,
        private readonly EquipmentCategoryService $categoryService,
        private readonly EquipmentService $equipmentService,
        private readonly MaintenancePlanService $planService,
    ) {}

    // =========================================================================
    // Functional Locations
    // =========================================================================

    public function functionalLocationIndex(Request $request): JsonResponse
    {
        return $this->paginated($this->locationService->paginate([
            'search' => $request->input('search'),
            'location_type' => $request->input('location_type'),
            'roots_only' => $request->boolean('roots_only'),
            'parent_id' => $request->input('parent_id'),
        ], $request->integer('per_page', 15)));
    }

    public function functionalLocationStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('functional_locations')],
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'location_type' => 'required|in:'.implode(',', FunctionalLocation::LOCATION_TYPES),
            'branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
            'address' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->created($this->locationService->create($data), 'Functional location created successfully.');
    }

    public function functionalLocationShow(FunctionalLocation $functionalLocation): JsonResponse
    {
        return $this->success($functionalLocation->load(['parent', 'children', 'equipment']));
    }

    public function functionalLocationUpdate(Request $request, FunctionalLocation $functionalLocation): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('functional_locations')],
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'location_type' => 'sometimes|required|in:'.implode(',', FunctionalLocation::LOCATION_TYPES),
            'branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
            'address' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->success(
            $this->locationService->update($functionalLocation, $data),
            'Functional location updated successfully.'
        );
    }

    public function functionalLocationDestroy(FunctionalLocation $functionalLocation): JsonResponse
    {
        try {
            $this->locationService->delete($functionalLocation);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Functional location deleted successfully.');
    }

    // =========================================================================
    // Equipment Categories
    // =========================================================================

    public function categoryIndex(Request $request): JsonResponse
    {
        return $this->paginated($this->categoryService->paginate(
            $request->input('search'),
            $request->integer('per_page', 15)
        ));
    }

    public function categoryStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        return $this->created($this->categoryService->create($data), 'Equipment category created successfully.');
    }

    public function categoryShow(EquipmentCategory $equipmentCategory): JsonResponse
    {
        return $this->success($equipmentCategory->load('equipment'));
    }

    public function categoryUpdate(Request $request, EquipmentCategory $equipmentCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
        ]);

        return $this->success($this->categoryService->update($equipmentCategory, $data), 'Category updated successfully.');
    }

    public function categoryDestroy(EquipmentCategory $equipmentCategory): JsonResponse
    {
        try {
            $this->categoryService->delete($equipmentCategory);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Equipment category deleted successfully.');
    }

    // =========================================================================
    // Equipment
    // =========================================================================

    public function equipmentIndex(Request $request): JsonResponse
    {
        return $this->paginated($this->equipmentService->paginate([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'equipment_category_id' => $request->input('equipment_category_id'),
            'functional_location_id' => $request->input('functional_location_id'),
        ], $request->integer('per_page', 15)));
    }

    public function equipmentStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'functional_location_id' => ['nullable', 'integer', $this->ownedBy('functional_locations')],
            'equipment_category_id' => ['nullable', 'integer', $this->ownedBy('equipment_categories')],
            'equipment_number' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'acquisition_date' => 'nullable|date',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'status' => 'nullable|in:'.implode(',', Equipment::STATUSES),
            'notes' => 'nullable|string',
        ]);

        return $this->created(
            $this->equipmentService->create($data, $request->user()->id),
            'Equipment created successfully.'
        );
    }

    public function equipmentShow(Equipment $equipment): JsonResponse
    {
        return $this->success($equipment->load(['category', 'functionalLocation', 'maintenancePlans', 'creator']));
    }

    public function equipmentUpdate(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'functional_location_id' => ['nullable', 'integer', $this->ownedBy('functional_locations')],
            'equipment_category_id' => ['nullable', 'integer', $this->ownedBy('equipment_categories')],
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'acquisition_date' => 'nullable|date',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'status' => 'nullable|in:'.implode(',', Equipment::STATUSES),
            'notes' => 'nullable|string',
        ]);

        return $this->success($this->equipmentService->update($equipment, $data), 'Equipment updated successfully.');
    }

    public function equipmentDestroy(Equipment $equipment): JsonResponse
    {
        try {
            $this->equipmentService->delete($equipment);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Equipment deleted successfully.');
    }

    public function equipmentDueSoon(Request $request): JsonResponse
    {
        return $this->success($this->equipmentService->dueWithin(
            $request->user()->organization_id,
            $request->integer('days', 7)
        ));
    }

    // =========================================================================
    // Maintenance Plans
    // =========================================================================

    public function planIndex(Request $request): JsonResponse
    {
        return $this->paginated($this->planService->paginate([
            'equipment_id' => $request->input('equipment_id'),
            'is_active' => $request->input('is_active') !== null ? $request->boolean('is_active') : null,
            'maintenance_type' => $request->input('maintenance_type'),
        ], $request->integer('per_page', 15)));
    }

    public function planStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', $this->ownedBy('equipment')],
            'name' => 'required|string|max:200',
            'maintenance_type' => 'required|in:'.implode(',', MaintenancePlan::MAINTENANCE_TYPES),
            'frequency_type' => 'required|in:'.implode(',', MaintenancePlan::FREQUENCY_TYPES),
            'frequency_value' => 'required|integer|min:1',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'tasks' => 'nullable|array',
            'tasks.*.description' => 'required_with:tasks|string',
            'tasks.*.is_safety_critical' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->created(
            $this->planService->create($data, $request->user()->id),
            'Maintenance plan created successfully.'
        );
    }

    public function planUpdate(Request $request, MaintenancePlan $maintenancePlan): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'maintenance_type' => 'sometimes|required|in:'.implode(',', MaintenancePlan::MAINTENANCE_TYPES),
            'frequency_type' => 'sometimes|required|in:'.implode(',', MaintenancePlan::FREQUENCY_TYPES),
            'frequency_value' => 'sometimes|required|integer|min:1',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'tasks' => 'nullable|array',
            'tasks.*.description' => 'required_with:tasks|string',
            'tasks.*.is_safety_critical' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->success($this->planService->update($maintenancePlan, $data), 'Maintenance plan updated successfully.');
    }

    public function planToggleActive(MaintenancePlan $maintenancePlan): JsonResponse
    {
        $plan = $this->planService->toggleActive($maintenancePlan);
        $state = $plan->is_active ? 'activated' : 'deactivated';

        return $this->success($plan, "Maintenance plan {$state} successfully.");
    }

    public function planGenerateOrder(Request $request, MaintenancePlan $maintenancePlan): JsonResponse
    {
        try {
            $order = $this->maintenanceService->generateOrderFromPlan($maintenancePlan, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($order, 'Maintenance order generated from plan successfully.');
    }

    // =========================================================================
    // Maintenance Orders
    // =========================================================================

    public function orderIndex(Request $request): JsonResponse
    {
        return $this->paginated($this->maintenanceService->paginateOrders([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'order_type' => $request->input('order_type'),
            'equipment_id' => $request->input('equipment_id'),
            'assigned_to' => $request->input('assigned_to'),
        ], $request->integer('per_page', 15)));
    }

    public function orderStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', $this->ownedBy('equipment')],
            'order_type' => 'required|in:'.implode(',', MaintenanceOrder::ORDER_TYPES),
            'priority' => 'nullable|in:'.implode(',', MaintenanceOrder::PRIORITIES),
            'description' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'assigned_to' => ['nullable', 'integer', $this->ownedBy('users')],
            'estimated_cost' => 'nullable|numeric|min:0',
            'tasks' => 'nullable|array',
            'tasks.*.task_description' => 'required_with:tasks|string',
            'tasks.*.is_safety_critical' => 'nullable|boolean',
            'tasks.*.sort_order' => 'nullable|integer',
            'parts' => 'nullable|array',
            'parts.*.product_id' => ['nullable', 'integer', $this->ownedBy('products')],
            'parts.*.description' => 'required_with:parts|string',
            'parts.*.quantity_required' => 'nullable|numeric|min:0',
            'parts.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $tasks = $data['tasks'] ?? [];
        $parts = $data['parts'] ?? [];
        unset($data['tasks'], $data['parts']);

        try {
            $order = $this->maintenanceService->createMaintenanceOrder($data, $tasks, $parts, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($order, 'Maintenance order created successfully.');
    }

    public function orderShow(MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        return $this->success($maintenanceOrder->load([
            'equipment.category', 'equipment.functionalLocation', 'tasks', 'parts.product', 'assignee', 'plan', 'creator',
        ]));
    }

    public function orderUpdate(Request $request, MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        $data = $request->validate([
            'priority' => 'nullable|in:'.implode(',', MaintenanceOrder::PRIORITIES),
            'description' => 'nullable|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'assigned_to' => ['nullable', 'integer', $this->ownedBy('users')],
            'estimated_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:'.MaintenanceOrder::STATUS_ON_HOLD,
        ]);

        try {
            $order = $this->maintenanceService->updateOrder($maintenanceOrder, $data);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($order, 'Maintenance order updated successfully.');
    }

    public function orderDestroy(MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        try {
            $this->maintenanceService->deleteOrder($maintenanceOrder);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Maintenance order deleted successfully.');
    }

    public function orderStart(Request $request, MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->maintenanceService->startOrder($maintenanceOrder, $request->user()->id),
            'Maintenance order started successfully.',
            'INVALID_STATE'
        );
    }

    public function orderCompleteTask(Request $request, MaintenanceOrder $maintenanceOrder, int $taskId): JsonResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn () => $this->maintenanceService->completeTask($maintenanceOrder, $taskId, $data['notes'] ?? '', $request->user()->id),
            'Task completed successfully.',
            'INVALID_STATE'
        );
    }

    public function orderComplete(Request $request, MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        $data = $request->validate([
            'resolution_notes' => 'nullable|string',
            'actual_cost' => 'nullable|numeric|min:0',
            'downtime_hours' => 'nullable|numeric|min:0',
        ]);

        return $this->tryAction(
            fn () => $this->maintenanceService->completeOrder($maintenanceOrder, $data, $request->user()->id),
            'Maintenance order completed successfully.',
            'INVALID_STATE'
        );
    }

    public function orderCancel(Request $request, MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->maintenanceService->cancelOrder($maintenanceOrder, $request->user()->id),
            'Maintenance order cancelled successfully.',
            'INVALID_STATE'
        );
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        return $this->success($this->maintenanceService->getMaintenanceStats(
            $request->user()->organization_id,
            $validated['from'],
            $validated['to']
        ));
    }

    private function ruleError(BusinessRuleException $e): JsonResponse
    {
        return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
    }
}
