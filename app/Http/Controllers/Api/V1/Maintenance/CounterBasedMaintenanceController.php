<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Maintenance\CounterBasedMaintenanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterBasedMaintenanceController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly CounterBasedMaintenanceService $service) {}

    public function counters(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateCounters($request->user()->organization_id));
    }

    public function storeCounter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counter_name' => 'required|string|max:255',
            'equipment_id' => ['nullable', 'integer', $this->ownedBy('location_equipment')],
            'floc_id' => ['nullable', 'integer', $this->ownedBy('functional_locations')],
            'uom' => 'required|string|max:20',
            'overflow_value' => 'nullable|numeric',
        ]);

        return $this->created($this->service->createCounter($request->user()->organization_id, $data));
    }

    public function recordReading(Request $request, int $counterId): JsonResponse
    {
        $data = $request->validate([
            'reading_value' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
        ]);

        $counter = $this->service->findCounterOrFail($request->user()->organization_id, $counterId);
        $reading = $this->service->recordReading(
            $counter,
            (float) $data['reading_value'],
            Carbon::parse($data['reading_date']),
            $request->user()->id
        );

        return $this->success($reading, 'Reading recorded successfully');
    }

    public function plans(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginatePlans($request->user()->organization_id));
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_number' => 'required|string|max:50|unique:counter_based_plans',
            'plan_type' => 'required|in:time_based,counter_based,condition_based',
            'floc_id' => ['nullable', 'integer', $this->ownedBy('functional_locations')],
            'counter_id' => ['nullable', 'integer', $this->ownedBy('equipment_counters')],
            'task_list_id' => ['nullable', 'integer', $this->ownedBy('maintenance_task_lists')],
            'counter_interval' => 'nullable|numeric|min:0',
            'threshold_warning' => 'nullable|numeric|min:0',
        ]);

        return $this->created($this->service->createPlan($request->user()->organization_id, $data));
    }

    public function dueOrders(Request $request): JsonResponse
    {
        return $this->success($this->service->checkDueOrders($request->user()->organization_id));
    }

    public function generateOrder(Request $request, int $planId): JsonResponse
    {
        $plan = $this->service->findPlanOrFail($request->user()->organization_id, $planId);

        return $this->created($this->service->generatePmOrder($plan), 'PM Order generated');
    }

    public function orders(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateOrders($request->user()->organization_id));
    }

    public function completeOrder(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['actual_end' => 'nullable|date']);
        $order = $this->service->findOrderOrFail($request->user()->organization_id, $orderId);

        return $this->tryAction(
            fn () => $this->service->completePmOrder($order, $data),
            'PM Order completed',
            'INVALID_STATE'
        );
    }
}
