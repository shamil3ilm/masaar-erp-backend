<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\Inventory\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Inventory\MaterialValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialValuationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly MaterialValuationService $service
    ) {}

    /**
     * GET /inventory/valuation/inventory-value?warehouse_id=
     */
    public function inventoryValue(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $orgId       = $this->organizationId($request);
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $data = $this->service->calculateInventoryValue($orgId, $warehouseId);

        return $this->success($data, 'Inventory value calculated.');
    }

    /**
     * POST /inventory/valuation/revalue
     * Body: { product_id, new_unit_cost }
     */
    public function revalue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'    => ['required', 'integer', 'min:1', $this->ownedBy('products')],
            'new_unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $orgId = $this->organizationId($request);

        $this->service->revalueInventory(
            orgId:       $orgId,
            productId:   (int) $validated['product_id'],
            newUnitCost: (float) $validated['new_unit_cost']
        );

        return $this->success(null, 'Inventory revaluation posted successfully.');
    }

    /**
     * GET /inventory/valuation/variance-report?from=&to=
     */
    public function varianceReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $validated['to']   ?? now()->toDateString();

        $report = $this->service->varianceReport(
            $this->organizationId($request),
            $from,
            $to,
            $request->integer('per_page', 25)
        );
        $paginator = $report['paginator'];

        return $this->success([
            'from'    => $from,
            'to'      => $to,
            'entries' => $report['entries'],
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Variance report generated.');
    }
}
