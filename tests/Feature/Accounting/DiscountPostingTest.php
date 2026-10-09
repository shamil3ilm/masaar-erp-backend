<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Exceptions\ApiException;
use App\Models\Accounting\Account;
use App\Models\Purchase\Bill;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Services\Accounting\JournalEntryFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A document carrying a discount has to reach the ledger.
 *
 * It could not. Receivable was debited with the total, which is net of the
 * discount, while the lines were credited with their subtotals, which are
 * gross of it - so the entry was short by exactly the discount and
 * JournalService refuses an entry that does not balance. The bill side was
 * the mirror image.
 *
 * Nothing caught it because nothing posted a discounted document with both
 * halves real: the orchestrator tests mock the factory, and the factory tests
 * mock the service that does the balancing. These use both.
 *
 * The allowance is now posted as its own line - debited against revenue for a
 * sale, credited against expense for a purchase - which is what an allowance
 * is in double entry rather than an amount quietly netted off.
 */
class DiscountPostingTest extends TestCase
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

        $this->configureAccounts();
    }

    public function test_a_discounted_invoice_posts_and_balances(): void
    {
        $invoice = $this->invoiceWithDiscount();

        $entry = app(JournalEntryFactory::class)->forInvoice($invoice);

        $this->assertBalanced($entry);

        // The allowance is its own line, not netted into revenue.
        $this->assertSame(
            $invoice->discount_amount,
            $this->debitTo($entry, (string) config('erp.default_accounts.sales_discount')),
            'The discount was not debited to the sales discount account.'
        );
    }

    public function test_a_discounted_bill_posts_and_balances(): void
    {
        $bill = $this->billWithDiscount();

        $entry = app(JournalEntryFactory::class)->forBill($bill);

        $this->assertNotNull($entry);
        $this->assertBalanced($entry);

        $this->assertSame(
            $bill->discount_amount,
            $this->creditTo($entry, (string) config('erp.default_accounts.purchase_discount')),
            'The discount was not credited to the purchase discount account.'
        );
    }

    /**
     * A document with no discount posts exactly as it did before, with no
     * allowance line, so the change costs nothing where it does not apply.
     */
    public function test_an_undiscounted_invoice_gains_no_line(): void
    {
        $invoice = $this->invoiceWithDiscount(discount: null);

        $entry = app(JournalEntryFactory::class)->forInvoice($invoice);

        $this->assertBalanced($entry);
        $this->assertSame(
            '0.0000',
            $this->debitTo($entry, (string) config('erp.default_accounts.sales_discount')),
            'An allowance line was written for a document with no allowance.'
        );
    }

    /**
     * And without an account to post it to, the refusal says which key to set
     * rather than complaining about a missing account_id on an unnamed line.
     */
    public function test_an_unconfigured_account_is_reported_clearly(): void
    {
        config(['erp.default_accounts.sales_discount' => null]);

        $invoice = $this->invoiceWithDiscount();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/ERP_ACCOUNT_SALES_DISCOUNT/');

        app(JournalEntryFactory::class)->forInvoice($invoice);
    }

    private function assertBalanced(Model $entry): void
    {
        $debits = '0';
        $credits = '0';

        foreach ($entry->lines as $line) {
            $debits = bcadd($debits, (string) $line->debit, 4);
            $credits = bcadd($credits, (string) $line->credit, 4);
        }

        $this->assertSame(
            0,
            bccomp($debits, $credits, 4),
            "The entry does not balance: debits {$debits} against credits {$credits}."
        );
    }

    private function debitTo(Model $entry, string $accountId): string
    {
        return $this->sumFor($entry, $accountId, 'debit');
    }

    private function creditTo(Model $entry, string $accountId): string
    {
        return $this->sumFor($entry, $accountId, 'credit');
    }

    private function sumFor(Model $entry, string $accountId, string $column): string
    {
        $total = '0';

        foreach ($entry->lines as $line) {
            if ((string) $line->account_id === $accountId) {
                $total = bcadd($total, (string) $line->{$column}, 4);
            }
        }

        return bcadd($total, '0', 4);
    }

    private function invoiceWithDiscount(?string $discount = '10'): Invoice
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'currency_code' => 'SAR',
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'discount_type' => $discount === null ? null : 'percentage',
            'discount_value' => $discount ?? 0,
            'amount_paid' => 0,
            'exchange_rate' => 1,
        ]);

        $this->addLines($invoice);
        $invoice->recalculateTotals();

        return $invoice->fresh()->load('lines.product', 'customer');
    }

    private function billWithDiscount(?string $discount = '10'): Bill
    {
        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'currency_code' => 'SAR',
            'supplier_id' => $this->supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'discount_type' => $discount === null ? null : 'percentage',
            'discount_value' => $discount ?? 0,
            'amount_paid' => 0,
            'exchange_rate' => 1,
        ]);

        $this->addLines($bill);
        $bill->recalculateTotals();

        return $bill->fresh()->load('lines.product', 'supplier');
    }

    private function addLines(Model $document): void
    {
        foreach ([
            ['description' => 'Widget', 'quantity' => '7', 'unit_price' => '1.2345', 'tax_rate' => '5'],
            ['description' => 'Crate', 'quantity' => '2', 'unit_price' => '100', 'tax_rate' => '15'],
        ] as $line) {
            $document->lines()->create($line);
        }
    }

    /**
     * A chart with the accounts a posting needs, including the two an
     * allowance goes to.
     */
    private function configureAccounts(): void
    {
        $accounts = [];

        foreach ([
            'receivable' => ['1100', 'Accounts Receivable', 'asset'],
            'payable' => ['2100', 'Accounts Payable', 'liability'],
            'sales' => ['4000', 'Sales Revenue', 'income'],
            'expense' => ['5000', 'Cost of Sales', 'expense'],
            'tax_payable' => ['2200', 'VAT Payable', 'liability'],
            'tax_receivable' => ['1200', 'Input VAT', 'asset'],
            'sales_discount' => ['4100', 'Sales Discounts', 'income'],
            'purchase_discount' => ['5100', 'Purchase Discounts', 'expense'],
        ] as $key => [$code, $name, $type]) {
            $accounts["erp.default_accounts.{$key}"] = Account::factory()->create([
                'organization_id' => $this->organization->id,
                'code' => $code,
                'name' => $name,
                'account_type' => $type,
            ])->id;
        }

        config($accounts);
    }
}
