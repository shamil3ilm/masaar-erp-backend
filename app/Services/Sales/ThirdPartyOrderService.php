<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\Sales\ThirdPartyOrder;
use App\Services\Core\NumberGeneratorService;
use App\Support\TaxMath;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Third-party (drop shipment) orders: a vendor ships a sales order's goods
 * straight to the customer. The vendor is embedded by its reference columns
 * only, never with its tax number or email.
 */
class ThirdPartyOrderService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ThirdPartyOrder::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        return $query->with(['salesOrder', $this->vendorReference(), 'purchaseOrder', 'lines.product'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): ThirdPartyOrder
    {
        return DB::transaction(function () use ($data): ThirdPartyOrder {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $order = ThirdPartyOrder::create($data);

            foreach ($lines as $line) {
                $order->lines()->create(array_merge($line, [
                    'organization_id' => $order->organization_id,
                ]));
            }

            return $order->load(['salesOrder', $this->vendorReference(), 'lines.product']);
        });
    }

    /**
     * An order of the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function orderOf(int $id): ThirdPartyOrder
    {
        return ThirdPartyOrder::findOrFail($id);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function orderDetails(int $id): ThirdPartyOrder
    {
        return ThirdPartyOrder::with(['salesOrder', $this->vendorReference(), 'purchaseOrder', 'lines.product'])
            ->findOrFail($id);
    }

    public function update(ThirdPartyOrder $order, array $data): ThirdPartyOrder
    {
        $order->update($data);
        return $order->fresh(['salesOrder', $this->vendorReference(), 'purchaseOrder', 'lines.product']);
    }

    /**
     * Create the draft purchase order that asks the vendor to ship the
     * order's lines, at the vendor price when there is one.
     *
     * The third-party order is re-read under a lock and checked there, so two
     * requests cannot both create a purchase order for it. The purchase order,
     * its lines and the link back are written in one transaction.
     *
     * @throws BusinessRuleException when the order is not pending or already has a purchase order
     */
    public function createPurchaseOrder(ThirdPartyOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $order = ThirdPartyOrder::query()->lockForUpdate()->with('lines.product')->findOrFail($order->id);

            if (! $order->canCreatePO()) {
                throw new BusinessRuleException(
                    'Cannot create PO: order is not in pending status or PO already exists.',
                    'INVALID_STATUS'
                );
            }

            $vendor = Contact::findOrFail($order->vendor_id);

            $po = PurchaseOrder::create([
                'organization_id' => $order->organization_id,
                'supplier_id' => $vendor->id,
                'supplier_name' => $vendor->getDisplayName(),
                'order_number' => $this->numberGenerator->generate('PO', null, $order->organization_id),
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $order->estimated_delivery_date,
                'status' => 'draft',
                'notes' => 'Drop shipment for Third-Party Order #' . $order->id,
                'currency_code' => 'SAR',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            $total = '0';

            foreach ($order->lines as $index => $line) {
                $unitPrice = (string) ($line->vendor_price ?? $line->unit_price);
                $amounts = TaxMath::line((string) $line->quantity, $unitPrice, '0');

                $po->lines()->create([
                    'product_id' => $line->product_id,
                    'description' => $line->product?->name ?? 'Drop shipment item',
                    'quantity' => $line->quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'subtotal' => $amounts['subtotal'],
                    'total' => $amounts['total'],
                    'line_order' => $index + 1,
                ]);

                $total = bcadd($total, $amounts['total'], TaxMath::SCALE);
            }

            $po->update(['subtotal' => $total, 'total' => $total]);

            $order->update([
                'purchase_order_id' => $po->id,
                'status' => ThirdPartyOrder::STATUS_PO_CREATED,
            ]);

            return $po->fresh();
        });
    }

    public function confirmShipment(ThirdPartyOrder $order, array $data): ThirdPartyOrder
    {
        $order->update([
            'status' => ThirdPartyOrder::STATUS_SHIPPED,
            'shipping_confirmation' => $data['shipping_confirmation'] ?? null,
            'vendor_reference' => $data['vendor_reference'] ?? $order->vendor_reference,
            'estimated_delivery_date' => $data['estimated_delivery_date'] ?? $order->estimated_delivery_date,
        ]);

        return $order->fresh();
    }

    public function confirmDelivery(ThirdPartyOrder $order, string $date): ThirdPartyOrder
    {
        $order->update([
            'status' => ThirdPartyOrder::STATUS_DELIVERED,
            'actual_delivery_date' => $date,
        ]);

        return $order->fresh();
    }

    public function cancel(ThirdPartyOrder $order): ThirdPartyOrder
    {
        $order->update(['status' => ThirdPartyOrder::STATUS_CANCELLED]);
        return $order->fresh();
    }

    private function vendorReference(): string
    {
        return 'vendor:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
