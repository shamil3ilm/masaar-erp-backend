<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\Sales\InvoiceConversionService;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A converted document charges what its source charged: the target lines carry
 * the source's tax rate and discount, so their amounts follow from the same
 * figures.
 */
class SalesConversionLineFieldsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.quotations.convert',
            'sales.invoices.create',
        ]);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'payment_terms' => 30,
        ]);

        $this->product = Product::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'track_inventory' => false,
        ]);
    }

    public function test_converting_a_quotation_to_a_sales_order_carries_the_line_discount_and_tax_rate(): void
    {
        $quotation = Quotation::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'status' => Quotation::STATUS_ACCEPTED,
            'created_by' => $this->user->id,
        ]);

        $quotationLine = QuotationLine::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'description' => 'Consulting',
            'quantity' => 2,
            'unit_price' => 100,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 15,
        ]);
        $quotation->recalculateTotals();

        $result = app(QuotationService::class)->convert($quotation, 'sales_order');

        $orderLine = SalesOrder::findOrFail($result['id'])->lines()->firstOrFail();

        $this->assertSame('percentage', $orderLine->discount_type);
        $this->assertSame(0, bccomp((string) $orderLine->discount_value, '10', 4));
        $this->assertSame(0, bccomp((string) $orderLine->discount_amount, '20', 4));
        $this->assertSame(0, bccomp((string) $orderLine->tax_rate, '15', 4));
        $this->assertSame(0, bccomp((string) $orderLine->subtotal, '180', 4));
        $this->assertSame(0, bccomp((string) $orderLine->tax_amount, '27', 4));
        $this->assertSame(0, bccomp((string) $orderLine->total, '207', 4));

        $quotationLine->refresh();
        $order = SalesOrder::findOrFail($result['id']);
        $this->assertSame(0, bccomp((string) $order->subtotal, (string) $quotationLine->subtotal, 4));
        $this->assertSame(0, bccomp((string) $order->tax_amount, (string) $quotationLine->tax_amount, 4));
        $this->assertSame(0, bccomp((string) $order->total, (string) $quotation->fresh()->total, 4));
    }

    public function test_converting_a_sales_order_to_an_invoice_carries_the_line_tax_rate_and_discount(): void
    {
        $order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'status' => SalesOrder::STATUS_DELIVERED,
        ]);

        $orderLine = SalesOrderLine::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $this->product->id,
            'description' => 'Consulting',
            'quantity' => 2,
            'quantity_delivered' => 2,
            'quantity_invoiced' => 0,
            'unit_price' => 100,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 15,
        ]);
        $order->recalculateTotals();

        $invoice = app(InvoiceConversionService::class)->createFromSalesOrder($order->fresh('lines'));

        $invoiceLine = $invoice->lines()->firstOrFail();

        $this->assertSame(0, bccomp((string) $invoiceLine->tax_rate, '15', 4));
        $this->assertSame('percentage', $invoiceLine->discount_type);
        $this->assertSame(0, bccomp((string) $invoiceLine->discount_value, '10', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->discount_amount, '20', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->subtotal, '180', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->tax_amount, '27', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->total, '207', 4));

        $orderLine->refresh();
        $this->assertSame(0, bccomp((string) $invoice->subtotal, (string) $orderLine->subtotal, 4));
        $this->assertSame(0, bccomp((string) $invoice->tax_amount, (string) $orderLine->tax_amount, 4));
        $this->assertSame(0, bccomp((string) $invoice->total, (string) $order->fresh()->total, 4));
    }

    public function test_converting_a_quotation_to_an_invoice_carries_the_line_tax_rate_and_discount(): void
    {
        $quotation = Quotation::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'status' => Quotation::STATUS_ACCEPTED,
            'created_by' => $this->user->id,
        ]);

        QuotationLine::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'description' => 'Consulting',
            'quantity' => 2,
            'unit_price' => 100,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 15,
        ]);
        $quotation->recalculateTotals();

        $invoice = app(InvoiceConversionService::class)->createFromQuotation($quotation->fresh('lines'));

        $invoiceLine = $invoice->lines()->firstOrFail();

        $this->assertSame(0, bccomp((string) $invoiceLine->tax_rate, '15', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->discount_amount, '20', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->tax_amount, '27', 4));
        $this->assertSame(0, bccomp((string) $invoiceLine->total, '207', 4));
        $this->assertSame(0, bccomp((string) $invoice->total, (string) $quotation->fresh()->total, 4));
    }
}
