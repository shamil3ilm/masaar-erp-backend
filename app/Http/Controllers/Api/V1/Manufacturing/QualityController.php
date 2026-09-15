<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\QualityManagementService;
use App\Services\Manufacturing\QualityRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private QualityManagementService $qualityService,
        private QualityRecordService $records,
    ) {}

    // =========================================================================
    // Quality Plans
    // =========================================================================

    /**
     * List quality plans with optional filters.
     */
    public function indexPlans(Request $request): JsonResponse
    {
        return $this->paginated($this->records->paginatePlans(
            $request->only(['is_active', 'inspection_stage', 'product_id', 'search']),
            $this->safeSortBy($request->sort_by, QualityRecordService::PLAN_SORT_COLUMNS, 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Store a new quality plan.
     */
    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'product_id' => ['nullable', $this->ownedBy('products')],
            'product_category_id' => ['nullable', $this->ownedBy('categories')],
            'inspection_stage' => 'nullable|in:goods_receipt,production,pre_shipment,in_process,final',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
            'characteristics' => 'nullable|array',
            'characteristics.*.name' => 'required_with:characteristics|string|max:255',
            'characteristics.*.description' => 'nullable|string',
            'characteristics.*.inspection_method' => 'nullable|string|max:255',
            'characteristics.*.measurement_unit' => 'nullable|string|max:100',
            'characteristics.*.lower_limit' => 'nullable|numeric',
            'characteristics.*.upper_limit' => 'nullable|numeric|gte:characteristics.*.lower_limit',
            'characteristics.*.target_value' => 'nullable|numeric',
            'characteristics.*.is_mandatory' => 'nullable|boolean',
            'characteristics.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $characteristics = $validated['characteristics'] ?? [];
        unset($validated['characteristics']);

        $plan = $this->qualityService->createQualityPlan(
            $validated,
            $characteristics,
            auth()->id()
        );

        return $this->created($plan->load(['characteristics', 'product']));
    }

    /**
     * Show a single quality plan with its characteristics.
     */
    public function showPlan(int $id): JsonResponse
    {
        $plan = $this->records->findPlan($id, ['characteristics', 'product', 'productCategory', 'creator']);

        if ($plan === null) {
            return $this->notFound('Quality plan not found.');
        }

        return $this->success($plan);
    }

    /**
     * Update a quality plan (plan fields only; characteristics managed separately).
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = $this->records->findPlan($id);

        if ($plan === null) {
            return $this->notFound('Quality plan not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'product_id' => ['nullable', $this->ownedBy('products')],
            'product_category_id' => ['nullable', $this->ownedBy('categories')],
            'inspection_stage' => 'nullable|in:goods_receipt,production,pre_shipment,in_process,final',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        return $this->success($this->records->updatePlan($plan, $validated));
    }

    /**
     * Soft-delete a quality plan.
     */
    public function destroyPlan(int $id): JsonResponse
    {
        $plan = $this->records->findPlan($id);

        if ($plan === null) {
            return $this->notFound('Quality plan not found.');
        }

        $this->records->deletePlan($plan);

        return $this->success(null, 'Quality plan deleted successfully.');
    }

    // =========================================================================
    // Inspection Lots
    // =========================================================================

    /**
     * List inspection lots with optional filters.
     */
    public function indexLots(Request $request): JsonResponse
    {
        return $this->paginated($this->records->paginateLots(
            $request->only(['status', 'product_id', 'source_type', 'search', 'from', 'to']),
            $this->safeSortBy($request->sort_by, QualityRecordService::LOT_SORT_COLUMNS, 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a new inspection lot.
     */
    public function storeLot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', $this->ownedBy('products')],
            'quality_plan_id' => ['nullable', $this->ownedBy('quality_plans')],
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'source_type' => 'nullable|in:purchase_order,production,transfer,manual',
            'source_id' => 'nullable|integer',
            'quantity' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string',
        ]);

        return $this->created($this->qualityService->createInspectionLot($validated, auth()->id()));
    }

    /**
     * Show a single inspection lot with results.
     */
    public function showLot(int $id): JsonResponse
    {
        $lot = $this->records->findLot($id, [
            'qualityPlan.characteristics',
            'product',
            'warehouse',
            'inspector',
            'creator',
            'results.recorder',
            'results.characteristic',
        ]);

        if ($lot === null) {
            return $this->notFound('Inspection lot not found.');
        }

        return $this->success($lot);
    }

    /**
     * Record inspection results against an existing lot.
     */
    public function recordResults(Request $request, int $id): JsonResponse
    {
        $lot = $this->records->findLot($id);

        if ($lot === null) {
            return $this->notFound('Inspection lot not found.');
        }

        if ($lot->isAccepted() || $lot->isRejected()) {
            return $this->error('Inspection lot is already completed.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'results' => 'required|array|min:1',
            'results.*.quality_plan_characteristic_id' => ['nullable', $this->ownedThrough('quality_plan_characteristics', 'quality_plan_id', 'quality_plans')],
            'results.*.characteristic_name' => 'required_without:results.*.quality_plan_characteristic_id|string|max:255',
            'results.*.measured_value' => 'nullable|numeric',
            'results.*.text_result' => 'nullable|string|max:500',
            'results.*.is_conforming' => 'nullable|boolean',
            'results.*.notes' => 'nullable|string',
        ]);

        $lot = $this->qualityService->recordInspectionResults(
            $lot,
            $validated['results'],
            auth()->id()
        );

        return $this->success($lot->load(['results.recorder', 'results.characteristic']));
    }

    /**
     * Complete an inspection lot by providing accepted and rejected quantities.
     */
    public function completeLot(Request $request, int $id): JsonResponse
    {
        $lot = $this->records->findLot($id);

        if ($lot === null) {
            return $this->notFound('Inspection lot not found.');
        }

        $validated = $request->validate([
            'accepted_quantity' => 'required|numeric|min:0',
            'rejected_quantity' => 'required|numeric|min:0',
        ]);

        $lot = $this->qualityService->completeInspection(
            $lot,
            (float) $validated['accepted_quantity'],
            (float) $validated['rejected_quantity'],
            auth()->id()
        );

        return $this->success($lot->fresh(['product', 'inspector']));
    }

    // =========================================================================
    // Quality Notifications
    // =========================================================================

    /**
     * List quality notifications with optional filters.
     */
    public function indexNotifications(Request $request): JsonResponse
    {
        return $this->paginated($this->records->paginateNotifications(
            $request->only(['status', 'priority', 'notification_type', 'assigned_to', 'product_id', 'overdue', 'search', 'from', 'to']),
            $this->safeSortBy($request->sort_by, QualityRecordService::NOTIFICATION_SORT_COLUMNS, 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a new quality notification.
     */
    public function storeNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_type' => 'nullable|in:defect,complaint,improvement,deviation',
            'source_type' => 'nullable|in:inspection_lot,customer,supplier,internal',
            'source_id' => 'nullable|integer',
            'product_id' => ['nullable', $this->ownedBy('products')],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => ['nullable', $this->ownedBy('users')],
            'due_date' => 'nullable|date',
            'defects' => 'nullable|array',
            'defects.*.defect_type' => 'required_with:defects|string|max:100',
            'defects.*.defect_code' => 'nullable|string|max:50',
            'defects.*.quantity' => 'nullable|integer|min:1',
            'defects.*.severity' => 'nullable|in:minor,major,critical',
            'defects.*.description' => 'nullable|string',
            'defects.*.location' => 'nullable|string|max:255',
        ]);

        return $this->created($this->qualityService->createNotification($validated, auth()->id()));
    }

    /**
     * Show a single quality notification with defects.
     */
    public function showNotification(int $id): JsonResponse
    {
        $notification = $this->records->findNotification($id, ['defects', 'product', 'assignee', 'creator', 'resolver']);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        return $this->success($notification);
    }

    /**
     * Assign a notification to a user.
     */
    public function assignNotification(Request $request, int $id): JsonResponse
    {
        $notification = $this->records->findNotification($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        $validated = $request->validate([
            'assigned_to' => ['required', $this->ownedBy('users')],
        ]);

        return $this->success($this->qualityService->assignNotification(
            $notification,
            (int) $validated['assigned_to'],
            auth()->id()
        ));
    }

    /**
     * Resolve a quality notification.
     */
    public function resolveNotification(Request $request, int $id): JsonResponse
    {
        $notification = $this->records->findNotification($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        $validated = $request->validate([
            'root_cause' => 'required|string',
            'corrective_action' => 'required|string',
            'preventive_action' => 'nullable|string',
        ]);

        return $this->success($this->qualityService->resolveNotification($notification, $validated, auth()->id()));
    }

    /**
     * Close a resolved notification.
     */
    public function closeNotification(int $id): JsonResponse
    {
        $notification = $this->records->findNotification($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        return $this->success($this->qualityService->closeNotification($notification, auth()->id()));
    }

    /**
     * List defect records for a notification.
     */
    public function listDefects(int $id): JsonResponse
    {
        $notification = $this->records->findNotification($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        return $this->success($this->records->defectsOf($notification));
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Return quality management statistics for the authenticated organisation.
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        return $this->success($this->qualityService->getQualityStats(
            auth()->user()->organization_id,
            $validated['from'],
            $validated['to']
        ));
    }
}
