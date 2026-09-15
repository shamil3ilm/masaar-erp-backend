<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Inventory\BatchWhereUsedService;
use App\Services\Inventory\InventoryBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BatchWhereUsedController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly BatchWhereUsedService $service,
        private readonly InventoryBatchService $batches,
    ) {}

    public function getForBatch(Request $request, string $batchId): JsonResponse
    {
        $batch = $this->batches->findOrFail((int) Auth::user()->organization_id, $batchId);

        $records = $this->service->getForBatch(
            $batch->id,
            $request->only(['usage_type', 'from_date', 'to_date'])
        );

        return $this->success($records, 'Where-used records retrieved.');
    }

    public function record(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inventory_batch_id' => ['required', 'integer', $this->ownedBy('inventory_batches')],
            'usage_type'         => 'required|in:work_order,process_order,sales_invoice,stock_transfer,adjustment',
            'reference_id'       => 'required|integer',
            'reference_number'   => 'nullable|string|max:100',
            'product_id'         => ['nullable', 'integer', $this->ownedBy('products')],
            'quantity_used'      => 'required|numeric|min:0.0001',
            'used_at'            => 'nullable|date',
            'warehouse_id'       => ['nullable', 'integer', $this->ownedBy('warehouses')],
        ]);

        $record = $this->service->record(array_merge($validated, [
            'organization_id' => Auth::user()->organization_id,
        ]));

        return $this->created($record, 'Where-used record created.');
    }

    public function whereUsedTree(string $batchId): JsonResponse
    {
        $batch = $this->batches->findOrFail((int) Auth::user()->organization_id, $batchId);

        $tree = $this->service->getWhereUsedTree($batch->id);

        return $this->success($tree, 'Where-used tree retrieved.');
    }

    public function searchByReference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'usage_type'   => 'required|in:work_order,process_order,sales_invoice,stock_transfer,adjustment',
            'reference_id' => 'required|integer',
        ]);

        $records = $this->service->searchByReference(
            $validated['usage_type'],
            (int) $validated['reference_id']
        );

        return $this->success($records, 'Records retrieved.');
    }
}
