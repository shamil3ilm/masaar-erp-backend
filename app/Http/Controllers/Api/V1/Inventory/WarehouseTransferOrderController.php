<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\Inventory\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Inventory\WarehouseTransferOrder;
use App\Services\Inventory\WarehouseTransferOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseTransferOrderController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private WarehouseTransferOrderService $transferOrderService
    ) {}

    /**
     * List warehouse transfer orders.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->transferOrderService->list(
            $request->only(['warehouse_id', 'status', 'movement_type', 'from_date', 'to_date']),
            $request->integer('per_page', 15)
        );

        return $this->paginated($orders);
    }

    /**
     * Create a new warehouse transfer order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'          => ['required', $this->ownedBy('warehouses')],
            'movement_type'         => 'nullable|in:goods_receipt,goods_issue,internal_transfer,replenishment',
            'source_document_type'  => 'nullable|string|max:50',
            'source_document_ref'   => 'nullable|string|max:50',
            'source_location_id'    => ['nullable', $this->ownedLocation()],
            'dest_location_id'      => ['nullable', $this->ownedLocation()],
            'assigned_to'           => ['nullable', $this->ownedBy('users')],
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => ['required', $this->ownedBy('products')],
            'items.*.variant_id'    => ['nullable', $this->ownedVariant()],
            'items.*.source_location_id' => ['nullable', $this->ownedLocation()],
            'items.*.dest_location_id'   => ['nullable', $this->ownedLocation()],
            'items.*.requested_quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            $order = $this->transferOrderService->create($validated);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'CREATE_ERROR', 422);
        }

        return $this->success($order, 'Transfer order created.', 201);
    }

    /**
     * Show a single transfer order.
     */
    public function show(WarehouseTransferOrder $warehouseTransferOrder): JsonResponse
    {
        return $this->success(
            $warehouseTransferOrder->load(['warehouse', 'items.product', 'items.variant', 'sourceLocation', 'destLocation', 'assignee'])
        );
    }

    /**
     * Update a transfer order (only when status is created).
     */
    public function update(Request $request, WarehouseTransferOrder $warehouseTransferOrder): JsonResponse
    {
        if (!$warehouseTransferOrder->isEditable()) {
            return $this->error(
                'Transfer order can only be edited when in created status.',
                'STATE_ERROR',
                422
            );
        }

        $validated = $request->validate([
            'source_document_type' => 'nullable|string|max:50',
            'source_document_ref'  => 'nullable|string|max:50',
            'source_location_id'   => ['nullable', $this->ownedLocation()],
            'dest_location_id'     => ['nullable', $this->ownedLocation()],
            'assigned_to'          => ['nullable', $this->ownedBy('users')],
        ]);

        try {
            $order = $this->transferOrderService->update($warehouseTransferOrder, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'STATE_ERROR', 422);
        }

        return $this->success($order, 'Transfer order updated.');
    }

    /**
     * Soft-delete a transfer order.
     */
    public function destroy(WarehouseTransferOrder $warehouseTransferOrder): JsonResponse
    {
        try {
            $this->transferOrderService->delete($warehouseTransferOrder);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'STATE_ERROR', 422);
        }

        return $this->success(null, 'Transfer order deleted.');
    }

    /**
     * Transition transfer order to in_progress.
     */
    public function startTransfer(WarehouseTransferOrder $warehouseTransferOrder): JsonResponse
    {
        try {
            $order = $this->transferOrderService->startTransfer($warehouseTransferOrder);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'STATE_ERROR', 422);
        }

        return $this->success($order, 'Transfer order started.');
    }

    /**
     * Confirm actual transferred quantities and update stock levels.
     */
    public function confirmTransfer(Request $request, WarehouseTransferOrder $warehouseTransferOrder): JsonResponse
    {
        $validated = $request->validate([
            'quantities'                           => 'required|array|min:1',
            'quantities.*.item_id'                 => 'required|integer',
            'quantities.*.transferred_quantity'    => 'required|numeric|min:0',
        ]);

        try {
            $order = $this->transferOrderService->confirmTransfer(
                $warehouseTransferOrder,
                $validated['quantities']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'STATE_ERROR', 422);
        }

        return $this->success($order, 'Transfer order confirmed.');
    }

    /**
     * Cancel a transfer order.
     */
    public function cancel(WarehouseTransferOrder $warehouseTransferOrder): JsonResponse
    {
        try {
            $order = $this->transferOrderService->cancel($warehouseTransferOrder);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'STATE_ERROR', 422);
        }

        return $this->success($order, 'Transfer order cancelled.');
    }
}
