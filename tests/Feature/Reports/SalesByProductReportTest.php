<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * Sales by product over invoices the test writes: the figure per product, the
 * period boundaries, the invoice statuses that count, and the organisation the
 * lines belong to.
 */
class SalesByProductReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private Contact $customer;

    private Product $widget;

    private Product $gadget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.reports.view']);

        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->widget = $this->product('Widget', '4.0000');
        $this->gadget = $this->product('Gadget', '1.0000');
    }

    public function test_each_product_carries_the_quantity_and_value_sold_in_the_period(): void
    {
        $this->sell('2026-03-05', [[$this->widget, '10.0000', '100.0000'], [$this->gadget, '3.0000', '30.0000']]);
        $this->sell('2026-03-20', [[$this->widget, '5.0000', '50.0000']]);

        $report = $this->salesByProduct('2026-03-01', '2026-03-31');
        $products = collect($report['products'])->keyBy('product_name');

        $this->assertSame('15.0000', $this->money($products['Widget']['quantity_sold']));
        $this->assertSame('150.0000', $this->money($products['Widget']['total']));
        $this->assertSame(2, $products['Widget']['invoice_count']);
        // 15 sold at a purchase price of 4 leaves 150 - 60.
        $this->assertSame('90.0000', $this->money($products['Widget']['gross_profit']));

        $this->assertSame('3.0000', $this->money($products['Gadget']['quantity_sold']));
        $this->assertSame('30.0000', $this->money($products['Gadget']['total']));

        $this->assertSame('180.0000', $this->money($report['summary']['total_sales']));
        $this->assertSame('18.0000', $this->money($report['summary']['total_quantity']));
    }

    public function test_the_period_holds_its_first_and_last_day_and_nothing_either_side(): void
    {
        $this->sell('2026-02-28', [[$this->widget, '1.0000', '1.0000']]);
        $this->sell('2026-03-01', [[$this->widget, '1.0000', '100.0000']]);
        $this->sell('2026-03-31', [[$this->widget, '1.0000', '20.0000']]);
        $this->sell('2026-04-01', [[$this->widget, '1.0000', '7.0000']]);

        $this->assertSame('120.0000', $this->money($this->salesByProduct('2026-03-01', '2026-03-31')['summary']['total_sales']));
    }

    public function test_only_an_issued_invoice_counts_as_a_sale(): void
    {
        $this->sell('2026-03-05', [[$this->widget, '1.0000', '10.0000']], Invoice::STATUS_SENT);
        $this->sell('2026-03-06', [[$this->widget, '1.0000', '20.0000']], Invoice::STATUS_PARTIAL);
        $this->sell('2026-03-07', [[$this->widget, '1.0000', '40.0000']], Invoice::STATUS_PAID);

        // A draft has not been issued and a voided invoice has been withdrawn.
        $this->sell('2026-03-08', [[$this->widget, '1.0000', '800.0000']], Invoice::STATUS_DRAFT);
        $this->sell('2026-03-09', [[$this->widget, '1.0000', '1600.0000']], Invoice::STATUS_VOIDED);
        // An overdue invoice was issued, yet the report leaves it out: whether
        // that is deliberate or an oversight in the status list is not settled
        // here, so this pins what it does today.
        $this->sell('2026-03-10', [[$this->widget, '1.0000', '3200.0000']], Invoice::STATUS_OVERDUE);

        $this->assertSame('70.0000', $this->money($this->salesByProduct('2026-03-01', '2026-03-31')['summary']['total_sales']));
    }

    public function test_another_organizations_sales_are_not_counted_here(): void
    {
        $other = Organization::factory()->create();
        $theirCustomer = Contact::factory()->create(['organization_id' => $other->id]);
        $theirProduct = Product::factory()->create([
            'organization_id' => $other->id,
            'name' => 'Widget',
            'type' => Product::TYPE_GOODS,
            'purchase_price' => '4.0000',
        ]);

        $this->sell('2026-03-05', [[$this->widget, '2.0000', '25.0000']]);

        $theirInvoice = Invoice::factory()->create([
            'organization_id' => $other->id,
            'customer_id' => $theirCustomer->id,
            'invoice_date' => '2026-03-05',
            'due_date' => '2026-04-05',
            'status' => Invoice::STATUS_SENT,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $theirInvoice->id,
            'product_id' => $theirProduct->id,
            'quantity' => '999.0000',
            'unit_price' => '9.0000',
            'subtotal' => '8991.0000',
            'tax_amount' => '0.0000',
            'total' => '8991.0000',
        ]);

        $report = $this->salesByProduct('2026-03-01', '2026-03-31');

        $this->assertCount(1, $report['products']);
        $this->assertSame('25.0000', $this->money($report['summary']['total_sales']));
    }

    public function test_a_period_with_no_sales_reports_zeroes_rather_than_nulls(): void
    {
        $report = $this->salesByProduct('2026-03-01', '2026-03-31');

        $this->assertSame([], $report['products']);
        $this->assertSame([], $report['by_category']);
        $this->assertSame(0, $report['summary']['product_count']);
        $this->assertSame('0.0000', $this->money($report['summary']['total_sales']));
        $this->assertSame('0.0000', $this->money($report['summary']['total_quantity']));
        $this->assertSame('0.0000', $this->money($report['summary']['total_gross_profit']));
        $this->assertSame('0.0000', $this->money($report['summary']['average_margin']));
    }

    private function product(string $name, string $purchasePrice): Product
    {
        return Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'type' => Product::TYPE_GOODS,
            'purchase_price' => $purchasePrice,
        ]);
    }

    /** @param  list<array{0: Product, 1: string, 2: string}>  $lines */
    private function sell(string $date, array $lines, string $status = Invoice::STATUS_SENT): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            // The report is scoped to the signed-in user's default branch, so
            // an invoice raised anywhere else in the organisation is left out.
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'invoice_date' => $date,
            'due_date' => $date,
            'status' => $status,
            'currency_code' => 'SAR',
            'exchange_rate' => '1.00000000',
        ]);

        foreach ($lines as [$product, $quantity, $total]) {
            InvoiceLine::factory()->create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'quantity' => $quantity,
                'unit_price' => bcdiv($total, $quantity, 4),
                'discount_amount' => '0.0000',
                'tax_rate' => '0.0000',
                'tax_amount' => '0.0000',
                'subtotal' => $total,
                'total' => $total,
            ]);
        }
    }

    private function salesByProduct(string $start, string $end): array
    {
        return $this->apiGet("/reports/sales/by-product?start_date={$start}&end_date={$end}")
            ->assertOk()
            ->json('data');
    }
}
