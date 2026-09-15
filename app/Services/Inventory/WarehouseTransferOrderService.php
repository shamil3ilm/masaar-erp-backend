<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\WarehouseTransferOrder;
use App\Models\Inventory\WarehouseTransferOrderItem;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseTransferOrderService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a warehouse transfer order with its items.
     */
    public function create(array $data): WarehouseTransferOrder
    {
        return DB::transaction(function () use ($data): WarehouseTransferOrder {
            $orgId = $data['organization_id'] ?? auth()->user()?->organization_id;

            $order = WarehouseTransferOrder::create([
                'organization_id'      => $orgId,
                'to_number'            => $this->numberGenerator->generate(WarehouseTransferOrder::NUMBER_SEQUENCE, WarehouseTransferOrder::NUMBER_FORMAT, $orgId),
                'warehouse_id'         => $data['warehouse_id'],
                'movement_type'        => $data['movement_type'] ?? WarehouseTransferOrder::MOVEMENT_INTERNAL,
                'source_document_type' => $data['source_document_type'] ?? null,
                'source_document_ref'  => $data['source_document_ref'] ?? null,
                'source_location_id'   => $data['source_location_id'] ?? null,
                'dest_location_id'     => $data['dest_location_id'] ?? null,
                'assigned_to'          => $data['assigned_to'] ?? null,
                'status'               => WarehouseTransferOrder::STATUS_CREATED,
                'created_by'           => auth()->id(),
            ]);

            foreach ($data['items'] ?? [] as $itemData) {
                WarehouseTransferOrderItem::create([
                    'transfer_order_id'    => $order->id,
                    'product_id'           => $itemData['product_id'],
                    'variant_id'           => $itemData['variant_id'] ?? null,
                    'source_location_id'   => $itemData['source_location_id'] ?? $data['source_location_id'] ?? null,
                    'dest_location_id'     => $itemData['dest_location_id'] ?? $data['dest_location_id'] ?? null,
                    'requested_quantity'   => $itemData['requested_quantity'],
                    'transferred_quantity' => 0,
                    'status'               => WarehouseTransferOrderItem::STATUS_OPEN,
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * Transfer orders of the current organization with their warehouse,
     * locations and assignee, newest first. Each filter applies when its value
     * is truthy.
     *
     * @param  array{warehouse_id?: mixed, status?: mixed, movement_type?: mixed, from_date?: mixed, to_date?: mixed}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return WarehouseTransferOrder::with(['warehouse', 'sourceLocation', 'destLocation', 'assignee'])
            ->when($filters['warehouse_id'] ?? null, fn ($q, $v) => $q->forWarehouse((int) $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['movement_type'] ?? null, fn ($q, $v) => $q->where('movement_type', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Change the header of an order still in created status, checked on the
     * locked row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WarehouseTransferOrder $order, array $data): WarehouseTransferOrder
    {
        return $order->lockForTransition(function (WarehouseTransferOrder $order) use ($data): WarehouseTransferOrder {
            if (! $order->isEditable()) {
                throw new \InvalidArgumentException('Transfer order can only be edited when in created status.');
            }

            $order->update($data);

            return $order->fresh();
        });
    }

    /**
     * Soft-delete an order that is created or in progress, checked on the
     * locked row so a confirmed order is never removed.
     */
    public function delete(WarehouseTransferOrder $order): void
    {
        $order->lockForTransition(function (WarehouseTransferOrder $order): void {
            if (! $order->canCancel()) {
                throw new \InvalidArgumentException('Only created or in-progress transfer orders can be deleted.');
            }

            $order->delete();
        });
    }

    /**
     * Start a transfer order — transition to in_progress, checked on the
     * locked row.
     */
    public function startTransfer(WarehouseTransferOrder $order): WarehouseTransferOrder
    {
        return $order->lockForTransition(function (WarehouseTransferOrder $order): WarehouseTransferOrder {
            if (!$order->canStart()) {
                throw new \InvalidArgumentException(
                    "Cannot start transfer order with status '{$order->status}'."
                );
            }

            $order->update(['status' => WarehouseTransferOrder::STATUS_IN_PROGRESS]);

            return $order->fresh();
        });
    }

    /**
     * Confirm actual quantities transferred, update stock_levels, and transition to confirmed.
     *
     * Runs on the locked order: a second confirm waits, then finds the order
     * confirmed and is refused, so stock is moved once.
     *
     * @param  array<int, array{item_id: int, transferred_quantity: float}>  $quantities
     */
    public function confirmTransfer(WarehouseTransferOrder $order, array $quantities): WarehouseTransferOrder
    {
        return $order->lockForTransition(function (WarehouseTransferOrder $order) use ($quantities): WarehouseTransferOrder {
            if (!$order->canConfirm()) {
                throw new \InvalidArgumentException(
                    "Cannot confirm transfer order with status '{$order->status}'."
                );
            }

            $quantityMap = collect($quantities)->keyBy('item_id');

            foreach ($order->items as $item) {
                $entry             = $quantityMap->get($item->id);
                $transferredQty    = $entry !== null ? (float) $entry['transferred_quantity'] : 0.0;

                if ($transferredQty <= 0) {
                    $item->update(['status' => WarehouseTransferOrderItem::STATUS_CANCELLED]);
                    continue;
                }

                // Deduct from source location stock
                $this->adjustStock(
                    $order->warehouse_id,
                    $item->product_id,
                    $item->variant_id,
                    $item->source_location_id,
                    -$transferredQty
                );

                // Add to destination location stock
                $this->adjustStock(
                    $order->warehouse_id,
                    $item->product_id,
                    $item->variant_id,
                    $item->dest_location_id,
                    $transferredQty
                );

                $status = $transferredQty >= (float) $item->requested_quantity
                    ? WarehouseTransferOrderItem::STATUS_TRANSFERRED
                    : WarehouseTransferOrderItem::STATUS_PARTIALLY_TRANSFERRED;

                $item->update([
                    'transferred_quantity' => $transferredQty,
                    'status'               => $status,
                ]);
            }

            $order->update([
                'status'       => WarehouseTransferOrder::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            return $order->fresh(['items']);
        });
    }

    /**
     * Cancel a transfer order, checked on the locked row.
     */
    public function cancel(WarehouseTransferOrder $order): WarehouseTransferOrder
    {
        return $order->lockForTransition(function (WarehouseTransferOrder $order): WarehouseTransferOrder {
            if (!$order->canCancel()) {
                throw new \InvalidArgumentException(
                    "Cannot cancel transfer order with status '{$order->status}'."
                );
            }

            $order->update(['status' => WarehouseTransferOrder::STATUS_CANCELLED]);

            return $order->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function adjustStock(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        ?int $locationId,
        float $delta
    ): void {
        $stockLevel = StockLevel::withoutGlobalScope('organization')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($stockLevel === null) {
            if ($delta > 0) {
                // Create new stock level record for destination
                $orgId = auth()->user()?->organization_id;
                StockLevel::create([
                    'organization_id' => $orgId,
                    'warehouse_id'    => $warehouseId,
                    'product_id'      => $productId,
                    'variant_id'      => $variantId,
                    'location_id'     => $locationId,
                    'quantity'        => $delta,
                ]);
            } else {
                Log::warning("WarehouseTransferOrderService: stock level not found for deduction", [
                    'warehouse_id' => $warehouseId,
                    'product_id'   => $productId,
                    'location_id'  => $locationId,
                ]);
            }
            return;
        }

        $newQty = max(0.0, (float) $stockLevel->quantity + $delta);
        $stockLevel->update([
            'quantity'    => $newQty,
            'total_value' => $newQty * (float) $stockLevel->average_cost,
        ]);
    }
}
