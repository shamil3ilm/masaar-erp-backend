<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\BulkSaleBatch;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesReturn;
use App\Services\Purchase\VendorCreditNoteService;
use App\Services\Sales\BulkSaleService;
use App\Services\Sales\CreditNoteService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The totals each document stores from its lines.
 *
 * Invoices, bills, quotations, sales orders and purchase orders sum their
 * lines at four decimals and take the document discount off the taxable
 * amount: the discount is shared across the tax rates the lines carry, in
 * proportion to each rate's net, and each rate is taxed on what is left of
 * its own net. So the document's VAT falls with the discount, and it is not
 * the sum of the lines' VAT. Sales credit notes and sales returns work at two
 * decimals; vendor credit notes and bulk sales at four.
 */
class DocumentTotalsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([]);
        $this->actingAs($this->user);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);
        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
        ]);
    }

    public function test_an_invoice_shares_its_percentage_discount_across_its_tax_rates(): void
    {
        $invoice = Invoice::factory()->create($this->header() + [
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'amount_paid' => 0,
            'exchange_rate' => 1,
        ]);

        $this->addLines($invoice);
        $invoice->recalculateTotals();

        // 19.8353 off 198.3535, shared 0.8353 to the 5% net and 19.0000 to
        // the 15% one: 7.5182 at 5% is 0.3759, 171.0000 at 15% is 25.6500.
        $this->assertTotals($invoice->fresh(), subtotal: '198.3535', tax: '26.0259', discount: '19.8353', total: '204.5441');
        $this->assertSame('204.5441', $invoice->fresh()->base_total);
        $this->assertSame('204.5441', $invoice->fresh()->amount_due);
    }

    public function test_a_bill_shares_its_fixed_discount_across_its_tax_rates(): void
    {
        $bill = Bill::factory()->create($this->header() + [
            'supplier_id' => $this->supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'discount_type' => 'fixed',
            'discount_value' => '5',
            'amount_paid' => 0,
            'exchange_rate' => 2,
        ]);

        $this->addLines($bill);
        $bill->recalculateTotals();

        // 5.0000 shared 0.2106 / 4.7894: 8.1429 at 5% is 0.4071, 185.2106 at
        // 15% is 27.7816.
        $this->assertTotals($bill->fresh(), subtotal: '198.3535', tax: '28.1887', discount: '5.0000', total: '221.5422');
        $this->assertSame('443.0844', $bill->fresh()->base_total);
    }

    public function test_a_single_rate_document_taxes_the_whole_net_less_the_whole_discount(): void
    {
        $order = SalesOrder::factory()->create($this->header() + [
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        foreach (['100', '200'] as $price) {
            $order->lines()->create([
                'description' => 'Desk',
                'quantity' => '1',
                'unit_price' => $price,
                'tax_rate' => '15',
            ]);
        }

        $order->recalculateTotals();

        // One rate takes the whole allowance: 270.0000 at 15% is 40.5000.
        $this->assertTotals($order->fresh(), subtotal: '300.0000', tax: '40.5000', discount: '30.0000', total: '310.5000');
    }

    public function test_a_zero_rated_line_takes_its_share_of_the_discount_and_bears_no_tax(): void
    {
        $order = SalesOrder::factory()->create($this->header() + [
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'discount_type' => 'fixed',
            'discount_value' => '50',
        ]);

        foreach (['15', '0'] as $rate) {
            $order->lines()->create([
                'description' => 'Book',
                'quantity' => '1',
                'unit_price' => '100',
                'tax_rate' => $rate,
            ]);
        }

        $order->recalculateTotals();

        // Half the allowance belongs to the zero-rated net, so 75.0000 of the
        // standard-rated net is taxed - not 50.0000 of it, which would be
        // 7.5000 of VAT.
        $this->assertTotals($order->fresh(), subtotal: '200.0000', tax: '11.2500', discount: '50.0000', total: '161.2500');
    }

    public function test_a_discount_larger_than_the_net_leaves_no_taxable_amount(): void
    {
        $quotation = Quotation::factory()->create($this->header() + [
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'discount_type' => 'fixed',
            'discount_value' => '500',
        ]);

        $quotation->lines()->create([
            'description' => 'Lamp',
            'quantity' => '1',
            'unit_price' => '100',
            'tax_rate' => '15',
        ]);

        $quotation->recalculateTotals();

        // Held at the subtotal, so the base is nothing rather than negative
        // and the discount never turns into tax owed back.
        $this->assertTotals($quotation->fresh(), subtotal: '100.0000', tax: '0.0000', discount: '100.0000', total: '0.0000');
    }

    public function test_a_document_with_no_discount_charges_each_rate_its_whole_net(): void
    {
        $order = SalesOrder::factory()->create($this->header() + [
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        $this->addLines($order);
        $order->recalculateTotals();

        // 8.3535 at 5% is 0.4177 and 190.0000 at 15% is 28.5000, the figures
        // the two lines stored themselves.
        $this->assertTotals($order->fresh(), subtotal: '198.3535', tax: '28.9177', discount: '0.0000', total: '227.2712');
    }

    /** @return array<string, array{class-string<Model>, array<string, mixed>}> */
    public static function orderDocuments(): array
    {
        return [
            'quotation' => [Quotation::class, ['status' => 'draft']],
            'sales order' => [SalesOrder::class, ['status' => 'draft']],
            'purchase order' => [PurchaseOrder::class, ['status' => 'draft']],
        ];
    }

    #[DataProvider('orderDocuments')]
    public function test_an_order_document_shares_its_percentage_discount_across_its_tax_rates(string $class, array $attributes): void
    {
        $party = $class === PurchaseOrder::class
            ? ['supplier_id' => $this->supplier->id]
            : ['customer_id' => $this->customer->id];

        $document = $class::factory()->create($this->header() + $party + $attributes + [
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $this->addLines($document);
        $document->recalculateTotals();

        $this->assertTotals($document->fresh(), subtotal: '198.3535', tax: '26.0259', discount: '19.8353', total: '204.5441');
    }

    public function test_a_sales_credit_note_rounds_each_items_tax_at_two_decimals(): void
    {
        $note = app(CreditNoteService::class)->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'credit_note_type' => CreditNote::TYPE_SALES,
            'credit_note_date' => now()->toDateString(),
            'currency_code' => 'SAR',
            'reason' => 'Returned',
            'items' => $this->twoDecimalItems('quantity'),
        ], $this->user->id);

        // 14.99 at 7.125% is 1.0680375, which rounds half up to 1.07.
        $items = $note->items()->orderBy('id')->get();
        $this->assertSame(['100.00', '15.00', '115.00'], [$items[0]->subtotal, $items[0]->tax_amount, $items[0]->total]);
        $this->assertSame(['14.99', '1.07', '16.06'], [$items[1]->subtotal, $items[1]->tax_amount, $items[1]->total]);

        $note = $note->fresh();
        $this->assertSame(['114.99', '16.07', '131.06', '131.06'], [$note->subtotal, $note->tax_amount, $note->total, $note->available_amount]);
    }

    public function test_a_sales_return_rounds_each_items_tax_at_two_decimals(): void
    {
        $return = app(SalesReturnService::class)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'return_date' => now()->toDateString(),
            'return_type' => SalesReturn::TYPE_REFUND,
            'currency_code' => 'SAR',
            'reason_notes' => 'Wrong size',
            'items' => $this->twoDecimalItems('quantity_returned'),
        ], $this->user->id);

        $items = $return->items()->orderBy('id')->get();
        $this->assertSame(['100.00', '15.00', '115.00'], [$items[0]->subtotal, $items[0]->tax_amount, $items[0]->total]);
        $this->assertSame(['14.99', '1.07', '16.06'], [$items[1]->subtotal, $items[1]->tax_amount, $items[1]->total]);
        $this->assertSame(0, bccomp((string) $return->total, '131.06', 2), "total is {$return->total}");
    }

    public function test_a_vendor_credit_note_rounds_at_four_decimals_on_create_and_update(): void
    {
        $service = app(VendorCreditNoteService::class);
        $lines = [
            ['description' => 'Returned widget', 'quantity' => '7', 'unit_price' => '1.2345', 'tax_rate' => '5'],
            ['description' => 'Returned crate', 'quantity' => '2', 'unit_price' => '100', 'tax_rate' => '7.125'],
        ];

        $note = $service->create([
            'organization_id' => $this->organization->id,
            'vendor_id' => $this->supplier->id,
            'issue_date' => now()->toDateString(),
            'credit_date' => now()->toDateString(),
        ], $lines);

        $this->assertVendorCreditNote($note);

        $updated = $service->update($note->fresh(), ['notes' => 'Recounted'], $lines);

        $this->assertVendorCreditNote($updated);
    }

    public function test_a_bulk_sale_item_takes_its_discount_off_before_tax(): void
    {
        $batch = BulkSaleBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => BulkSaleBatch::STATUS_DRAFT,
        ]);

        $batch = app(BulkSaleService::class)->addItems($batch, [[
            'customer_id' => $this->customer->id,
            'description' => 'Bulk chairs',
            'quantity' => '7',
            'unit_price' => '1.2345',
            'discount_amount' => '0.2880',
            'tax_rate' => '5',
        ]]);

        // Computed at four decimals (0.4177 and 8.7712) and read back through
        // the item's decimal:2 casts.
        $item = $batch->items->first();
        $this->assertSame(['0.42', '8.77'], [$item->tax_amount, $item->total_amount]);
    }

    private function assertVendorCreditNote(Model $note): void
    {
        // 8.6415 at 5% is 0.432075, which rounds half up to 0.4321.
        $lines = $note->lines()->orderBy('id')->get();
        $this->assertSame(['0.4321', '9.0736'], [$lines[0]->tax_amount, $lines[0]->line_total]);
        $this->assertSame(['14.2500', '214.2500'], [$lines[1]->tax_amount, $lines[1]->line_total]);

        $fresh = $note->fresh();
        $this->assertSame(['208.6415', '14.6821', '223.3236'], [$fresh->subtotal, $fresh->tax_amount, $fresh->total_amount]);
    }

    /** @return array<string, mixed> */
    private function header(): array
    {
        return [
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'currency_code' => 'SAR',
        ];
    }

    /** One line with a percentage discount and one with a fixed discount, as LineTotalsTest pins them. */
    private function addLines(Model $document): void
    {
        $document->lines()->create([
            'description' => 'Widget',
            'quantity' => '7',
            'unit_price' => '1.2345',
            'discount_type' => 'percentage',
            'discount_value' => '3.3333',
            'tax_rate' => '5',
        ]);
        $document->lines()->create([
            'description' => 'Crate',
            'quantity' => '2',
            'unit_price' => '100',
            'discount_type' => 'fixed',
            'discount_value' => '10',
            'tax_rate' => '15',
        ]);
    }

    /** @return list<array<string, string>> */
    private function twoDecimalItems(string $quantityKey): array
    {
        return [
            ['description' => 'Chair', $quantityKey => '3', 'unit_price' => '33.335', 'tax_rate' => '15'],
            ['description' => 'Stool', $quantityKey => '1.5', 'unit_price' => '9.999', 'tax_rate' => '7.125'],
        ];
    }

    private function assertTotals(Model $document, string $subtotal, string $tax, string $discount, string $total): void
    {
        $this->assertSame(
            [$subtotal, $tax, $discount, $total],
            [$document->subtotal, $document->tax_amount, $document->discount_amount, $document->total],
        );
    }
}
