<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Core\NumberSequence;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lists, creates and edits sales orders and moves them through their states.
 *
 * Each change to an existing order re-reads it under a row lock and checks its
 * status there, so two requests acting on the same order cannot both pass a
 * guard that only one of them should. An order embeds only the reference
 * columns of its customer; the contact's tax number and email leave only
 * through ContactResource.
 */
class SalesOrderService
{
    /**
     * Sales orders of the current organization with their customer,
     * salesperson and warehouse, newest first, narrowed by the filters that are set.
     *
     * @param  array{customer_id?: mixed, status?: mixed, from_date?: mixed, to_date?: mixed, salesperson_id?: mixed, warehouse_id?: mixed, search?: mixed}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return SalesOrder::with(['customer:'.implode(',', Contact::REFERENCE_COLUMNS), 'salesperson', 'warehouse'])
            ->latest('order_date')
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->forCustomer((int) $id))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('order_date', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('order_date', '<=', $v))
            ->when($filters['salesperson_id'] ?? null, fn ($q, $id) => $q->where('salesperson_id', (int) $id))
            ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', (int) $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);
    }

    /**
     * Create a draft sales order with its lines, numbered in the branch's sequence.
     *
     * @param  array<string, mixed>  $data  validated order fields with a 'lines' list
     */
    public function create(array $data, User $user, ?int $branchId): SalesOrder
    {
        return DB::transaction(function () use ($data, $user, $branchId): SalesOrder {
            $organizationId = $user->organization_id;
            $orderNumber = NumberSequence::getNext($organizationId, 'sales_order', $branchId);

            $customer = Contact::find($data['customer_id']);

            $salesOrder = SalesOrder::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'order_number' => $orderNumber,
                'customer_id' => $data['customer_id'],
                'customer_name' => $customer?->getDisplayName() ?? $customer?->company_name ?? 'Customer',
                'customer_email' => $customer?->email,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency_code' => $data['currency_code'] ?? $user->organization->base_currency ?? 'SAR',
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'salesperson_id' => $data['salesperson_id'] ?? $user->id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => SalesOrder::STATUS_DRAFT,
                'created_by' => $user->id,
            ]);

            $this->createLines($salesOrder, $data['lines']);

            $salesOrder->recalculateTotals();
            $salesOrder->load('lines');

            return $salesOrder;
        });
    }

    /**
     * Create the draft sales order for an accepted quotation, copying its
     * amounts and lines. Runs inside the caller's transaction.
     */
    public function createFromQuotation(Quotation $quotation): SalesOrder
    {
        $soNumber = NumberSequence::getNext(
            $quotation->organization_id,
            'sales_order',
            $quotation->branch_id
        );

        $salesOrder = SalesOrder::create([
            'organization_id' => $quotation->organization_id,
            'branch_id' => $quotation->branch_id,
            'order_number' => $soNumber,
            'customer_id' => $quotation->customer_id,
            'customer_name' => $quotation->customer_name ?: ($quotation->customer?->getDisplayName() ?? 'Customer'),
            'customer_email' => $quotation->customer_email,
            'order_date' => now(),
            'currency_code' => $quotation->currency_code,
            'exchange_rate' => $quotation->exchange_rate,
            'subtotal' => $quotation->subtotal,
            'discount_type' => $quotation->discount_type,
            'discount_value' => $quotation->discount_value,
            'discount_amount' => $quotation->discount_amount,
            'tax_amount' => $quotation->tax_amount,
            'total' => $quotation->total,
            'salesperson_id' => $quotation->salesperson_id,
            'notes' => $quotation->notes,
            'reference' => $quotation->quotation_number,
            'quotation_id' => $quotation->id,
            'status' => SalesOrder::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        foreach ($quotation->lines as $line) {
            SalesOrderLine::create([
                'sales_order_id' => $salesOrder->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'quantity_delivered' => 0,
                'quantity_invoiced' => 0,
                'unit_price' => $line->unit_price,
                'discount_amount' => $line->discount_amount,
                'tax_rate' => $line->tax_rate,
                'tax_amount' => $line->tax_amount,
                'subtotal' => $line->subtotal,
                'total' => $line->total,
                'line_order' => $line->line_order,
            ]);
        }

        $salesOrder->recalculateTotals();

        return $salesOrder;
    }

    /**
     * An order with its lines, the reference columns of its customer and
     * invoices, its salesperson, warehouse and quotation.
     */
    public function loadDetails(SalesOrder $salesOrder): SalesOrder
    {
        return $salesOrder->load([
            'customer:'.implode(',', Contact::REFERENCE_COLUMNS),
            'lines.product',
            'lines.variant',
            'lines.warehouse',
            'salesperson',
            'warehouse',
            'quotation',
            'invoices' => fn ($query) => $query->select([...Invoice::REFERENCE_COLUMNS, 'sales_order_id']),
        ]);
    }

    /**
     * @param  string  $action  past participle for the message, such as "updated"
     *
     * @throws \InvalidArgumentException when the order is not a draft
     */
    public function assertDraft(SalesOrder $salesOrder, string $action): void
    {
        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            throw new \InvalidArgumentException("Only draft sales orders can be {$action}.");
        }
    }

    /**
     * Update a draft order. Lines, when given, replace the existing ones, and a
     * changed customer refreshes the name and email copied onto the order.
     *
     * The status is checked on the locked row, so an order confirmed by a
     * concurrent request is not changed through a copy loaded before that.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException when the order is no longer a draft
     */
    public function update(SalesOrder $salesOrder, array $data): SalesOrder
    {
        return $salesOrder->lockForTransition(function (SalesOrder $salesOrder) use ($data): SalesOrder {
            $this->assertDraft($salesOrder, 'updated');

            $orderData = collect($data)->except('lines')->toArray();

            if (isset($data['customer_id']) && $data['customer_id'] !== $salesOrder->customer_id) {
                $customer = Contact::find($data['customer_id']);
                $orderData['customer_name'] = $customer?->getDisplayName() ?? $customer?->company_name ?? 'Customer';
                $orderData['customer_email'] = $customer?->email;
            }

            $salesOrder->update($orderData);

            if (isset($data['lines'])) {
                $salesOrder->lines()->delete();
                $this->createLines($salesOrder, $data['lines']);
                $salesOrder->recalculateTotals();
            }

            return $salesOrder->load(['customer:'.implode(',', Contact::REFERENCE_COLUMNS), 'lines', 'salesperson', 'warehouse']);
        });
    }

    /**
     * Delete a draft order and its lines, checked on the locked row.
     *
     * @throws \InvalidArgumentException when the order is no longer a draft
     */
    public function delete(SalesOrder $salesOrder): void
    {
        $salesOrder->lockForTransition(function (SalesOrder $salesOrder): void {
            $this->assertDraft($salesOrder, 'deleted');

            $salesOrder->lines()->delete();
            $salesOrder->delete();
        });
    }

    /**
     * @throws \InvalidArgumentException when the order is not a draft or has no lines
     */
    public function assertConfirmable(SalesOrder $salesOrder): void
    {
        $this->assertDraft($salesOrder, 'confirmed');

        if ($salesOrder->lines()->count() === 0) {
            throw new \InvalidArgumentException('Sales order must have at least one line item.');
        }
    }

    /**
     * Confirm a draft order that has lines, checked on the locked row. The
     * caller runs the customer's credit check first.
     *
     * @throws \InvalidArgumentException when the order is not a draft or has no lines
     */
    public function confirm(SalesOrder $salesOrder): SalesOrder
    {
        return $salesOrder->lockForTransition(function (SalesOrder $salesOrder): SalesOrder {
            $this->assertConfirmable($salesOrder);

            $salesOrder->transitionTo(SalesOrder::STATUS_CONFIRMED);

            return $salesOrder->load(['customer:'.implode(',', Contact::REFERENCE_COLUMNS), 'lines', 'salesperson']);
        });
    }

    /**
     * Cancel an order that is not yet delivered, checked on the locked row.
     *
     * @throws \InvalidArgumentException when the order is delivered, invoiced or already cancelled
     */
    public function cancel(SalesOrder $salesOrder): SalesOrder
    {
        return $salesOrder->lockForTransition(function (SalesOrder $salesOrder): SalesOrder {
            $allowedStatuses = [
                SalesOrder::STATUS_DRAFT,
                SalesOrder::STATUS_CONFIRMED,
                SalesOrder::STATUS_PROCESSING,
                SalesOrder::STATUS_PARTIALLY_DELIVERED,
            ];

            if (! in_array($salesOrder->status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException('Sales order cannot be cancelled in its current status.');
            }

            $salesOrder->transitionTo(SalesOrder::STATUS_CANCELLED);

            return $salesOrder->load(['customer:'.implode(',', Contact::REFERENCE_COLUMNS), 'lines', 'salesperson']);
        });
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function createLines(SalesOrder $salesOrder, array $lines): void
    {
        foreach ($lines as $order => $lineData) {
            SalesOrderLine::create([
                'sales_order_id' => $salesOrder->id,
                'product_id' => $lineData['product_id'] ?? null,
                'variant_id' => $lineData['variant_id'] ?? null,
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'quantity_delivered' => 0,
                'quantity_invoiced' => 0,
                'unit_id' => $lineData['unit_id'] ?? null,
                'unit_price' => $lineData['unit_price'],
                'discount_type' => $lineData['discount_type'] ?? null,
                'discount_value' => $lineData['discount_value'] ?? 0,
                'tax_rate' => $lineData['tax_rate'] ?? 0,
                'tax_category_id' => $lineData['tax_category_id'] ?? null,
                'warehouse_id' => $lineData['warehouse_id'] ?? null,
                'line_order' => $order + 1,
            ]);
        }
    }
}
