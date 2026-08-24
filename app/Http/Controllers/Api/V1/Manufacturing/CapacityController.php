<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CapacityRequirement;
use App\Models\Manufacturing\WorkCenter;
use App\Services\Manufacturing\CapacityPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capacity planning and reporting for work centers.
 *
 * Work center records themselves are managed by WorkCenterController.
 */
class CapacityController extends Controller
{
    public function __construct(
        private readonly CapacityPlanningService $capacityService
    ) {}

    /**
     * Get capacity load for a specific work center over a date range.
     */
    public function workCenterLoad(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $validated['from'] ?? now()->toDateString();
        $to   = $validated['to']   ?? now()->addDays(30)->toDateString();

        $data = $this->capacityService->getCapacityLoad(
            auth()->user()->organization_id,
            $from,
            $to,
            $workCenter->id
        );

        return $this->success($data);
    }

    // -------------------------------------------------------------------------
    // Work Order Capacity actions
    // -------------------------------------------------------------------------

    /**
     * Plan capacity requirements for a work order.
     */
    public function planCapacity(int $workOrderId): JsonResponse
    {
        $requirements = $this->capacityService->planCapacity($workOrderId, auth()->id());

        return $this->created([
            'requirements' => $requirements,
            'count'        => count($requirements),
        ], 'Capacity planned successfully.');
    }

    /**
     * Reschedule a work order to a new start date.
     */
    public function reschedule(Request $request, int $workOrderId): JsonResponse
    {
        $validated = $request->validate([
            'new_start_date' => 'required|date',
        ]);

        $requirements = $this->capacityService->rescheduleOrder(
            $workOrderId,
            $validated['new_start_date'],
            auth()->id()
        );

        return $this->success([
            'requirements' => $requirements,
            'count'        => count($requirements),
        ], 'Work order rescheduled successfully.');
    }

    /**
     * Release capacity for a work order (cancel requirements).
     */
    public function releaseCapacity(int $workOrderId): JsonResponse
    {
        $this->capacityService->releaseCapacity($workOrderId, auth()->id());

        return $this->success(null, 'Capacity released successfully.');
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    /**
     * Get capacity load for a date range.
     */
    public function capacityLoad(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'           => 'required|date',
            'to'             => 'required|date|after_or_equal:from|before_or_equal:' . now()->parse($request->input('from', now()))->addDays(90)->toDateString(),
            'work_center_id' => 'nullable|integer|exists:work_centers,id',
        ]);

        $data = $this->capacityService->getCapacityLoad(
            auth()->user()->organization_id,
            $validated['from'],
            $validated['to'],
            isset($validated['work_center_id']) ? (int) $validated['work_center_id'] : null
        );

        return $this->success($data);
    }

    /**
     * Detect bottleneck work centers (>90% utilisation).
     */
    public function bottlenecks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $data = $this->capacityService->detectBottlenecks(
            auth()->user()->organization_id,
            $validated['from'],
            $validated['to']
        );

        return $this->success($data);
    }

    /**
     * List capacity requirements (with filters).
     */
    public function requirements(Request $request): JsonResponse
    {
        $query = CapacityRequirement::with(['workCenter', 'workOrder'])
            ->when($request->work_center_id, fn($q, $id) => $q->where('work_center_id', $id))
            ->when($request->work_order_id, fn($q, $id) => $q->where('work_order_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('scheduled_start');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            null
        );
    }
}
