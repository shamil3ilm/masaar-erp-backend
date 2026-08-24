<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\ValidationException;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Creates invoices from an existing document: a quotation, a sales order, or
 * an invoice being credited.
 *
 * Each method assembles the header and lines, then hands them to
 * InvoiceService::create() so every invoice is built the same way.
 */
class InvoiceConversionService
{
    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Create a credit note for an invoice.
     */
    public function createCreditNote(Invoice $originalInvoice, array $lines, ?string $reason = null): Invoice
    {
        if ($originalInvoice->isCreditNote()) {
            throw new \InvalidArgumentException('Cannot create credit note for a credit note.');
        }

        $allowedStatuses = [
            Invoice::STATUS_SENT,
            Invoice::STATUS_PARTIAL,
            Invoice::STATUS_PAID,
            Invoice::STATUS_OVERDUE,
        ];

        if (!in_array($originalInvoice->status, $allowedStatuses, true)) {
            throw new ValidationException(
                'Credit notes can only be created for sent, partial, paid, or overdue invoices.'
            );
        }

        $creditedTotal = \App\Models\Sales\InvoiceLine::whereIn(
                'invoice_id',
                $originalInvoice->creditNotes()
                    ->whereNotIn('status', [Invoice::STATUS_VOIDED])
                    ->select('id')
            )->sum('total');

        if (bccomp((string) $creditedTotal, (string) $originalInvoice->total, 4) >= 0) {
            throw new ValidationException('Invoice is already fully credited.');
        }

        $data = [
            'invoice_type' => Invoice::TYPE_CREDIT_NOTE,
            'original_invoice_id' => $originalInvoice->id,
            'customer_id' => $originalInvoice->customer_id,
            'invoice_date' => now(),
            'due_date' => now(),
            'currency_code' => $originalInvoice->currency_code,
            'exchange_rate' => $originalInvoice->exchange_rate,
            'branch_id' => $originalInvoice->branch_id,
            'place_of_supply' => $originalInvoice->place_of_supply,
            'notes' => $reason ?? "Credit note for invoice {$originalInvoice->invoice_number}",
            'reference' => $originalInvoice->invoice_number,
        ];

        return $this->invoices->create($data, $lines);
    }

    /**
     * Convert quotation to invoice.
     */
    public function createFromQuotation(Quotation $quotation, array $data = []): Invoice
    {
        if (!$quotation->canBeConverted()) {
            throw new \InvalidArgumentException('Quotation must be accepted before conversion.');
        }

        $existing = Invoice::where('quotation_id', $quotation->id)
            ->whereNotIn('status', [Invoice::STATUS_VOIDED])
            ->first();

        if ($existing) {
            throw new ValidationException(
                'An invoice has already been created from this quotation.'
            );
        }

        $lines = $quotation->lines->map(fn($line) => [
            'product_id' => $line->product_id,
            'variant_id' => $line->variant_id,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_id' => $line->unit_id,
            'unit_price' => $line->unit_price,
            'discount_type' => $line->discount_type,
            'discount_value' => $line->discount_value,
            'tax_category_id' => $line->tax_category_id,
        ])->toArray();

        $invoice = $this->invoices->create([
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'invoice_date' => now(),
            'branch_id' => $quotation->branch_id,
            'currency_code' => $quotation->currency_code,
            'exchange_rate' => $quotation->exchange_rate,
            'discount_type' => $quotation->discount_type,
            'discount_value' => $quotation->discount_value,
            'salesperson_id' => $quotation->salesperson_id,
            'notes' => $quotation->notes,
            'terms_and_conditions' => $quotation->terms_and_conditions,
            'reference' => $quotation->reference,
        ], $lines);

        $quotation->transitionTo(Quotation::STATUS_CONVERTED);

        return $invoice;
    }

    /**
     * Convert sales order to invoice.
     */
    public function createFromSalesOrder(SalesOrder $order, ?array $lineQuantities = null): Invoice
    {
        if (!$order->canBeInvoiced()) {
            throw new \InvalidArgumentException('Sales order cannot be invoiced in current status.');
        }

        $lines = $order->lines
            ->filter(fn($line) => $line->getRemainingToInvoice() > 0)
            ->map(function ($line) use ($lineQuantities) {
                $quantity = $lineQuantities[$line->id] ?? $line->getRemainingToInvoice();

                return [
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'description' => $line->description,
                    'quantity' => $quantity,
                    'unit_id' => $line->unit_id,
                    'unit_price' => $line->unit_price,
                    'discount_type' => $line->discount_type,
                    'discount_value' => $line->discount_value,
                    'tax_category_id' => $line->tax_category_id,
                    'warehouse_id' => $line->warehouse_id,
                ];
            })->toArray();

        if (empty($lines)) {
            throw new \InvalidArgumentException('No items available to invoice.');
        }

        // Wrap the entire operation — invoice creation, line quantity updates, and order
        // status update — in a single transaction so a failure in any step rolls all back.
        return DB::transaction(function () use ($order, $lines) {
            $invoice = $this->invoices->create([
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'invoice_date' => now(),
                'branch_id' => $order->branch_id,
                'currency_code' => $order->currency_code,
                'exchange_rate' => $order->exchange_rate,
                'discount_type' => $order->discount_type,
                'discount_value' => $order->discount_value,
                'salesperson_id' => $order->salesperson_id,
                'notes' => $order->notes,
                'reference' => $order->reference,
            ], $lines);

            // Update invoiced quantities on order lines
            foreach ($invoice->lines as $invoiceLine) {
                if ($invoiceLine->product_id) {
                    $orderLine = $order->lines()
                        ->where('product_id', $invoiceLine->product_id)
                        ->where('variant_id', $invoiceLine->variant_id)
                        ->first();

                    if ($orderLine) {
                        $orderLine->increment('quantity_invoiced', $invoiceLine->quantity);
                    }
                }
            }

            // Update order status
            $progress = $order->fresh()->getFulfillmentProgress();
            if ($progress['invoice_percentage'] >= 100) {
                $order->update(['status' => SalesOrder::STATUS_INVOICED]);
            }

            return $invoice;
        });
    }
}
