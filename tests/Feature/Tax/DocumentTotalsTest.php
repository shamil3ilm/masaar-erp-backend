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
 * lines at four decimals and take the document discount off after tax, so
 * the discount does not reduce the VAT. Sales credit notes and sales returns
 * work at two decimals; vendor credit notes and bulk sales at four.
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

    public function test_an_invoice_takes_its_percentage_discount_off_after_tax(): void
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

        $this->assertTotals($invoice->fresh(), subtotal: '198.3535', tax: '28.9176', discount: '19.8353', total: '207.4358');
        $this->assertSame('207.4358', $invoice->fresh()->base_total);
        $this->assertSame('207.4358', $invoice->fresh()->amount_due);
    }

    public function test_a_bill_takes_its_fixed_discount_off_after_tax(): void
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

        $this->assertTotals($bill->fresh(), subtotal: '198.3535', tax: '28.9176', discount: '5.0000', total: '222.2711');
        $this->assertSame('444.5422', $bill->fresh()->base_total);
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
    public function test_an_order_document_takes_its_percentage_discount_off_after_tax(string $class, array $attributes): void
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

        $this->assertTotals($document->fresh(), subtotal: '198.3535', tax: '28.9176', discount: '19.8353', total: '207.4358');
    }

    public function test_a_sales_credit_note_truncates_each_item_at_two_decimals(): void
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

        $items = $note->items()->orderBy('id')->get();
        $this->assertSame(['100.00', '15.00', '115.00'], [$items[0]->subtotal, $items[0]->tax_amount, $items[0]->total]);
        $this->assertSame(['14.99', '1.06', '16.05'], [$items[1]->subtotal, $items[1]->tax_amount, $items[1]->total]);

        $note = $note->fresh();
        $this->assertSame(['114.99', '16.06', '131.05', '131.05'], [$note->subtotal, $note->tax_amount, $note->total, $note->available_amount]);
    }

    public function test_a_sales_return_truncates_each_item_at_two_decimals(): void
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
        $this->assertSame(['14.99', '1.06', '16.05'], [$items[1]->subtotal, $items[1]->tax_amount, $items[1]->total]);
        $this->assertSame(0, bccomp((string) $return->total, '131.05', 2), "total is {$return->total}");
    }

    public function test_a_vendor_credit_note_truncates_at_four_decimals_on_create_and_update(): void
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

        // Computed at four decimals (0.4176 and 8.7711) and read back through
        // the item's decimal:2 casts.
        $item = $batch->items->first();
        $this->assertSame(['0.42', '8.77'], [$item->tax_amount, $item->total_amount]);
    }

    private function assertVendorCreditNote(Model $note): void
    {
        $lines = $note->lines()->orderBy('id')->get();
        $this->assertSame(['0.4320', '9.0735'], [$lines[0]->tax_amount, $lines[0]->line_total]);
        $this->assertSame(['14.2500', '214.2500'], [$lines[1]->tax_amount, $lines[1]->line_total]);

        $fresh = $note->fresh();
        $this->assertSame(['208.6415', '14.6820', '223.3235'], [$fresh->subtotal, $fresh->tax_amount, $fresh->total_amount]);
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
