<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\SubcontractComponent;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Manufacturing\SubcontractOrderLine;
use App\Models\Manufacturing\SubcontractReceipt;
use App\Models\Manufacturing\SubcontractReceiptLine;
use App\Models\Manufacturing\SubcontractTransfer;
use App\Models\Manufacturing\SubcontractTransferLine;
use App\Models\Sales\Contact;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Subcontract orders: material transfers to the vendor and receipts back.
 *
 * Every status change runs on the locked order and re-checks the status there,
 * with its components or lines locked, so two requests cannot both transfer
 * the same components or receive against an order closed meanwhile. Transfers,
 * receipts, components and lines carry no organization column; they are
 * reached only through an order of the caller's organization. The vendor is
 * embedded by its reference columns.
 */
class SubcontractingService
{
    /** Columns an order list may be sorted by. */
    public const SORT_COLUMNS = ['order_number', 'status', 'issued_date', 'created_at'];

    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return SubcontractOrder::with([$this->vendorReference(), 'branch'])
            ->withCount(['lines', 'components', 'transfers', 'receipts'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['contact_id'] ?? null, fn ($q, $id) => $q->forVendor($id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('order_number', 'like', "%{$s}%"))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('issued_date', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('issued_date', '<=', $d))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * The order with its vendor, lines and components for display.
     */
    public function withDetails(SubcontractOrder $order): SubcontractOrder
    {
        return $order->loadMissing([
            $this->vendorReference(),
            'branch',
            'lines.product',
            'lines.variant',
            'lines.unit',
            'components.product',
            'components.variant',
            'components.unit',
            'components.warehouse',
            'createdBy',
        ]);
    }

    public function paginateTransfers(SubcontractOrder $order, ?string $transferType, int $perPage): LengthAwarePaginator
    {
        return SubcontractTransfer::where('order_id', $order->id)
            ->with(['warehouse', 'lines.product', 'createdBy'])
            ->when($transferType, fn ($q, $t) => $q->where('transfer_type', $t))
            ->orderBy('transfer_date', 'desc')
            ->paginate($perPage);
    }

    public function paginateReceipts(SubcontractOrder $order, int $perPage): LengthAwarePaginator
    {
        return SubcontractReceipt::where('order_id', $order->id)
            ->with(['warehouse', 'lines.product', 'createdBy'])
            ->orderBy('receipt_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * A transfer of one of the organization's orders, with its lines.
     */
    public function transferWithDetails(SubcontractTransfer $transfer): SubcontractTransfer
    {
        SubcontractOrder::findOrFail($transfer->order_id);

        return $transfer->loadMissing(['order', 'warehouse', 'lines.product', 'lines.unit', 'createdBy']);
    }

    /**
     * A receipt of one of the organization's orders, with its lines.
     */
    public function receiptWithDetails(SubcontractReceipt $receipt): SubcontractReceipt
    {
        SubcontractOrder::findOrFail($receipt->order_id);

        return $receipt->loadMissing(['order', 'warehouse', 'lines.product', 'lines.unit', 'createdBy']);
    }

    /**
     * Create a new subcontract order with its lines and components.
     */
    public function createOrder(array $data): SubcontractOrder
    {
        return DB::transaction(function () use ($data) {
            $order = SubcontractOrder::create([
                'organization_id'       => auth()->user()->organization_id,
                'order_number'          => $this->numberGenerator->generate('SCO'),
                'contact_id'            => $data['contact_id'],
                'status'                => SubcontractOrder::STATUS_DRAFT,
                'issued_date'           => $data['issued_date'] ?? null,
                'expected_receipt_date' => $data['expected_receipt_date'] ?? null,
                'currency_code'         => $data['currency_code'] ?? 'USD',
                'service_charge'        => $data['service_charge'] ?? 0,
                'notes'                 => $data['notes'] ?? null,
                'purchase_order_id'     => $data['purchase_order_id'] ?? null,
                'branch_id'             => $data['branch_id'] ?? null,
                'created_by'            => auth()->id(),
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                SubcontractOrderLine::create([
                    'order_id'             => $order->id,
                    'product_id'           => $line['product_id'],
                    'variant_id'           => $line['variant_id'] ?? null,
                    'ordered_quantity'     => $line['ordered_quantity'],
                    'received_quantity'    => 0,
                    'unit_id'              => $line['unit_id'],
                    'unit_service_charge'  => $line['unit_service_charge'] ?? 0,
                    'total_service_charge' => bcmul(
                        (string) ($line['unit_service_charge'] ?? 0),
                        (string) $line['ordered_quantity'],
                        4
                    ),
                    'scrap_quantity'       => 0,
                ]);
            }

            foreach ($data['components'] ?? [] as $component) {
                SubcontractComponent::create([
                    'order_id'             => $order->id,
                    'product_id'           => $component['product_id'],
                    'variant_id'           => $component['variant_id'] ?? null,
                    'required_quantity'    => $component['required_quantity'],
                    'transferred_quantity' => 0,
                    'unit_id'              => $component['unit_id'],
                    'warehouse_id'         => $component['warehouse_id'],
                ]);
            }

            return $order->fresh(['lines', 'components']);
        });
    }

    /**
     * Update the header of a draft order.
     */
    public function update(SubcontractOrder $order, array $data): SubcontractOrder
    {
        return $order->lockForTransition(function (SubcontractOrder $order) use ($data): SubcontractOrder {
            if (!$order->isDraft()) {
                throw new \InvalidArgumentException('Only draft orders can be updated.');
            }

            $order->update($data);

            return $order->fresh();
        });
    }

    /**
     * Mark order as sent to vendor (status transition: draft → sent).
     */
    public function sendToVendor(SubcontractOrder $order): SubcontractOrder
    {
        return $order->lockForTransition(function (SubcontractOrder $order): SubcontractOrder {
            if (!$order->isDraft()) {
                throw new \InvalidArgumentException('Only draft orders can be sent to a vendor.');
            }

            $order->update(['status' => SubcontractOrder::STATUS_SENT]);

            return $order->fresh();
        });
    }

    /**
     * Transfer raw materials to the vendor and issue them from stock.
     *
     * $items = [
     *   ['component_id' => int, 'quantity' => float, 'batch_number' => ?string],
     *   ...
     * ]
     */
    public function transferMaterialsToVendor(SubcontractOrder $order, array $items): SubcontractTransfer
    {
        return $order->lockForTransition(function (SubcontractOrder $order) use ($items): SubcontractTransfer {
            if (!in_array($order->status, [
                SubcontractOrder::STATUS_SENT,
                SubcontractOrder::STATUS_MATERIAL_TRANSFERRED,
                SubcontractOrder::STATUS_IN_PROCESS,
            ], true)) {
                throw new \InvalidArgumentException(
                    'Materials can only be transferred when the order is in sent, material_transferred, or in_process status.'
                );
            }

            $components = SubcontractComponent::whereIn('id', array_column($items, 'component_id'))
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $component = $components->get((int) $item['component_id'])
                    ?? throw new \InvalidArgumentException('Component does not belong to this order.');

                $qty = (float) $item['quantity'];
                if ($qty <= 0) {
                    throw new \InvalidArgumentException('Transfer quantity must be positive.');
                }

                if ($qty > $component->getRemainingQuantity()) {
                    throw new \InvalidArgumentException(
                        "Transfer quantity exceeds remaining required quantity for component {$component->id}."
                    );
                }
            }

            // The first component's warehouse is recorded as the transfer's source warehouse.
            $transfer = SubcontractTransfer::create([
                'order_id'      => $order->id,
                'transfer_date' => now()->toDateString(),
                'transfer_type' => SubcontractTransfer::TYPE_OUTWARD,
                'warehouse_id'  => $components->first()->warehouse_id,
                'created_by'    => auth()->id(),
            ]);

            foreach ($items as $item) {
                $component = $components->get((int) $item['component_id']);
                $qty       = (float) $item['quantity'];

                SubcontractTransferLine::create([
                    'transfer_id'       => $transfer->id,
                    'product_id'        => $component->product_id,
                    'variant_id'        => $component->variant_id,
                    'component_line_id' => $component->id,
                    'quantity'          => $qty,
                    'unit_id'           => $component->unit_id,
                    'batch_number'      => $item['batch_number'] ?? null,
                ]);

                $this->stockService->recordMovement(
                    productId: $component->product_id,
                    warehouseId: $component->warehouse_id,
                    movementType: 'subcontract_transfer_out',
                    direction: 'out',
                    quantity: $qty,
                    unitCost: 0.0,
                    referenceType: SubcontractOrder::class,
                    referenceId: $order->id,
                    notes: "Subcontract material transfer for order {$order->order_number}",
                );

                $component->update([
                    'transferred_quantity' => bcadd((string) $component->transferred_quantity, (string) $qty, 4),
                ]);
            }

            $allTransferred = $order->components()->get()->every(
                fn (SubcontractComponent $c) => $c->isFullyTransferred()
            );

            $order->update([
                'status' => $allTransferred
                    ? SubcontractOrder::STATUS_IN_PROCESS
                    : SubcontractOrder::STATUS_MATERIAL_TRANSFERRED,
            ]);

            return $transfer->fresh(['lines']);
        });
    }

    /**
     * Record goods received from the vendor and receive the accepted quantity into stock.
     *
     * $receiptData = [
     *   'warehouse_id' => int,
     *   'receipt_date' => date string,
     *   'notes'        => ?string,
     *   'lines'        => [
     *     ['order_line_id' => int, 'quantity_received' => float, 'quantity_rejected' => float,
     *      'unit_cost' => float, 'batch_number' => ?string, 'expiry_date' => ?string],
     *     ...
     *   ],
     * ]
     */
    public function receiveFromVendor(SubcontractOrder $order, array $receiptData): SubcontractReceipt
    {
        return $order->lockForTransition(function (SubcontractOrder $order) use ($receiptData): SubcontractReceipt {
            if (!$order->canReceive()) {
                throw new \InvalidArgumentException(
                    'Order must be in material_transferred or in_process status to receive goods.'
                );
            }

            $receipt = SubcontractReceipt::create([
                'order_id'     => $order->id,
                'receipt_date' => $receiptData['receipt_date'] ?? now()->toDateString(),
                'warehouse_id' => $receiptData['warehouse_id'],
                'status'       => SubcontractReceipt::STATUS_DRAFT,
                'notes'        => $receiptData['notes'] ?? null,
                'created_by'   => auth()->id(),
            ]);

            $orderLines = SubcontractOrderLine::whereIn('id', array_column($receiptData['lines'], 'order_line_id'))
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($receiptData['lines'] as $lineData) {
                $orderLine = $orderLines->get((int) $lineData['order_line_id'])
                    ?? throw new \InvalidArgumentException('Order line does not belong to this subcontract order.');

                $qtyReceived = (float) ($lineData['quantity_received'] ?? 0);
                $qtyRejected = (float) ($lineData['quantity_rejected'] ?? 0);
                $unitCost    = (float) ($lineData['unit_cost'] ?? 0);

                SubcontractReceiptLine::create([
                    'receipt_id'        => $receipt->id,
                    'order_line_id'     => $orderLine->id,
                    'product_id'        => $orderLine->product_id,
                    'quantity_received' => $qtyReceived,
                    'quantity_rejected' => $qtyRejected,
                    'unit_id'           => $orderLine->unit_id,
                    'unit_cost'         => $unitCost,
                    'total_cost'        => (float) bcmul((string) $qtyReceived, (string) $unitCost, 4),
                    'batch_number'      => $lineData['batch_number'] ?? null,
                    'expiry_date'       => $lineData['expiry_date'] ?? null,
                ]);

                $acceptedQty = $qtyReceived - $qtyRejected;

                if ($acceptedQty > 0) {
                    $this->stockService->recordMovement(
                        productId: $orderLine->product_id,
                        warehouseId: (int) $receiptData['warehouse_id'],
                        movementType: 'subcontract_receipt',
                        direction: 'in',
                        quantity: $acceptedQty,
                        unitCost: $unitCost,
                        referenceType: SubcontractOrder::class,
                        referenceId: $order->id,
                        notes: "Subcontract receipt for order {$order->order_number}",
                    );
                }

                $orderLine->update([
                    'received_quantity' => bcadd((string) $orderLine->received_quantity, (string) $qtyReceived, 4),
                    'scrap_quantity'    => bcadd((string) $orderLine->scrap_quantity, (string) $qtyRejected, 4),
                ]);
            }

            $receipt->update(['status' => SubcontractReceipt::STATUS_POSTED]);

            $allReceived = $order->lines()->get()->every(
                fn (SubcontractOrderLine $l) => $l->isFullyReceived()
            );

            $order->update([
                'status' => $allReceived
                    ? SubcontractOrder::STATUS_RECEIVED
                    : SubcontractOrder::STATUS_IN_PROCESS,
            ]);

            return $receipt->fresh(['lines.product']);
        });
    }

    /**
     * Close the subcontract order: book outstanding line quantities as scrap and mark it closed.
     */
    public function closeOrder(SubcontractOrder $order): SubcontractOrder
    {
        return $order->lockForTransition(function (SubcontractOrder $order): SubcontractOrder {
            if (!$order->canClose()) {
                throw new \InvalidArgumentException(
                    'Order must be in received or in_process status to be closed.'
                );
            }

            foreach ($order->lines()->lockForUpdate()->get() as $line) {
                $remaining = $line->getRemainingQuantity();
                if ($remaining > 0) {
                    $line->update([
                        'scrap_quantity' => bcadd((string) $line->scrap_quantity, (string) $remaining, 4),
                        'received_quantity' => $line->ordered_quantity,
                    ]);
                }
            }

            $order->update(['status' => SubcontractOrder::STATUS_CLOSED]);

            return $order->fresh(['lines', 'components']);
        });
    }

    /**
     * Cancel a draft or sent subcontract order.
     */
    public function cancel(SubcontractOrder $order): SubcontractOrder
    {
        return $order->lockForTransition(function (SubcontractOrder $order): SubcontractOrder {
            if (!$order->canBeCancelled()) {
                throw new \InvalidArgumentException('Only draft or sent orders can be cancelled.');
            }

            $order->update(['status' => SubcontractOrder::STATUS_CANCELLED]);

            return $order->fresh();
        });
    }

    private function vendorReference(): string
    {
        return 'vendor:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
