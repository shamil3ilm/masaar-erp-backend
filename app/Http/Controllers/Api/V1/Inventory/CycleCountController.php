<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Inventory\CycleCountService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CycleCountController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly CycleCountService $service) {}

    public function plans(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginatePlans($request->user()->organization_id, 20));
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_name'        => 'required|string|max:255',
            'warehouse_id'     => ['required', 'integer', $this->ownedBy('warehouses')],
            'count_frequency'  => 'required|in:A,B,C,custom',
            'products_per_day' => 'nullable|integer|min:1',
            'scheduled_date'   => 'nullable|date',
        ]);

        return $this->created($this->service->createPlan($request->user()->organization_id, $data));
    }

    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'      => ['required', 'integer', $this->ownedBy('cycle_count_plans')],
            'session_date' => 'required|date',
        ]);

        $plan    = $this->service->findPlanOrFail($request->user()->organization_id, (int) $data['plan_id']);
        $session = $this->service->createSession($plan, $request->user()->id, Carbon::parse($data['session_date']));

        return $this->created($session, 'Cycle count session created with ' . $session->lines->count() . ' lines');
    }

    public function showSession(Request $request, int $id): JsonResponse
    {
        $session = $this->service->findSessionOrFail(
            $request->user()->organization_id,
            $id,
            ['lines.product', 'lines.warehouseLocation']
        );

        return $this->success($session);
    }

    public function recordCount(Request $request, int $sessionId, int $lineId): JsonResponse
    {
        $data    = $request->validate(['counted_quantity' => 'required|numeric|min:0']);
        $session = $this->service->findSessionOrFail($request->user()->organization_id, $sessionId);

        try {
            $line = $this->service->recordCountInSession($session, $lineId, (float) $data['counted_quantity']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->success($line, 'Count recorded');
    }

    public function postAdjustments(Request $request, int $id): JsonResponse
    {
        $session = $this->service->findSessionOrFail($request->user()->organization_id, $id);

        try {
            $variances = $this->service->postSession($session);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->success([
            'variances' => $variances,
            'total_lines' => count($variances),
        ], 'Adjustments posted');
    }

    public function abcAnalysis(Request $request, int $warehouseId): JsonResponse
    {
        $result = $this->service->getAbcAnalysis($warehouseId);
        return $this->success($result);
    }
}
