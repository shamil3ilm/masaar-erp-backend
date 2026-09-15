<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceOrderSettlement;
use App\Services\Maintenance\MaintenanceOrderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MaintenanceSettlementController extends Controller
{
    use ValidatesOwnedRows;

    /**
     * The table a receiver id must be a row of, by receiver type. A WBS
     * receiver has no table in this application to check it against.
     */
    private const RECEIVER_TABLES = [
        MaintenanceOrderSettlement::RECEIVER_COST_CENTER => 'cost_centers',
        MaintenanceOrderSettlement::RECEIVER_ASSET => 'fixed_assets',
        MaintenanceOrderSettlement::RECEIVER_ORDER => 'maintenance_orders',
    ];

    public function __construct(
        private readonly MaintenanceOrderSettlementService $settlementService,
    ) {}

    public function costLines(Request $request, int $orderId): JsonResponse
    {
        $order = $this->settlementService->findOrderOrFail($this->organizationId($request), $orderId);
        $perPage = min((int) $request->input('per_page', 20), 100);

        return $this->paginated($this->settlementService->paginateCostLines($order, $perPage));
    }

    public function addCostLine(Request $request, int $orderId): JsonResponse
    {
        $order = $this->settlementService->findOrderOrFail($this->organizationId($request), $orderId);

        $validated = $request->validate([
            'cost_element_id' => ['nullable', 'integer', $this->ownedBy('cost_elements')],
            'cost_type' => 'required|in:labor,material,external,overhead',
            'quantity' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'posting_date' => 'nullable|date',
            'vendor_id' => ['nullable', 'integer', $this->ownedBy('contacts')],
            'employee_id' => ['nullable', 'integer', $this->ownedBy('employees')],
        ]);

        if (empty($validated['total_cost']) && (empty($validated['quantity']) || empty($validated['unit_cost']))) {
            return $this->error(
                'Either total_cost or both quantity and unit_cost are required.',
                'VALIDATION_ERROR',
                422,
            );
        }

        return $this->created($this->settlementService->recordCost($order, $validated));
    }

    public function totalCost(Request $request, int $orderId): JsonResponse
    {
        $order = $this->settlementService->findOrderOrFail($this->organizationId($request), $orderId);

        return $this->success([
            'maintenance_order_id' => $order->id,
            ...$this->settlementService->getTotalCost($order),
        ]);
    }

    public function settle(Request $request, int $orderId): JsonResponse
    {
        $order = $this->settlementService->findOrderOrFail($this->organizationId($request), $orderId);

        $validated = $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.receiver_type' => 'required|in:cost_center,asset,order,wbs',
            'rules.*.receiver_id' => 'required|integer|min:1',
            'rules.*.percentage' => 'required|numeric|min:0.01|max:100',
            ...$this->receiverRules($request),
        ]);

        $total = array_sum(array_column($validated['rules'], 'percentage'));
        if (abs($total - 100) > 0.01) {
            return $this->error(
                'Settlement rule percentages must sum to 100. Got: '.$total,
                'INVALID_PERCENTAGE',
                422,
            );
        }

        try {
            $settlements = $this->settlementService->settle($order, $validated['rules'], $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'SETTLEMENT_REFUSED', 422);
        }

        return $this->created($settlements);
    }

    public function settlementHistory(Request $request, int $orderId): JsonResponse
    {
        $order = $this->settlementService->findOrderOrFail($this->organizationId($request), $orderId);

        return $this->success($this->settlementService->getSettlementHistory($order));
    }

    public function unsettledOrders(Request $request): JsonResponse
    {
        return $this->success($this->settlementService->getUnsettledOrders($this->organizationId($request)));
    }

    /**
     * One owned-row rule per settlement rule whose receiver type has a table.
     *
     * @return array<string, array<int, mixed>>
     */
    private function receiverRules(Request $request): array
    {
        $rules = [];

        foreach ((array) $request->input('rules', []) as $index => $rule) {
            $type = is_array($rule) ? ($rule['receiver_type'] ?? null) : null;

            if (is_string($type) && isset(self::RECEIVER_TABLES[$type])) {
                $rules["rules.{$index}.receiver_id"] = ['required', 'integer', 'min:1', $this->ownedBy(self::RECEIVER_TABLES[$type])];
            }
        }

        return $rules;
    }
}
