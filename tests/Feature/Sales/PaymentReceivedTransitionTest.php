<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Contact;
use App\Models\Sales\CustomerCredit;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentAllocation;
use App\Models\Sales\PaymentReceived;
use App\Services\Accounting\JournalService;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Each payment transition runs on the locked row: a second call made with an
 * instance loaded before the first ran is rejected, balances are adjusted from
 * the locked invoice rather than the caller's copy, and a journal failure
 * rolls the transition back.
 */
class PaymentReceivedTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private PaymentService $service;
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.payments.view',
            'sales.payments.create',
            'sales.payments.delete',
        ]);
        $this->setUpOpenFiscalPeriod();

        $receivable = $this->account('1100', 'Accounts Receivable', Account::SUBTYPE_RECEIVABLE);
        $bank = $this->account('1010', 'Cash at Bank', Account::SUBTYPE_BANK);

        Config::set('erp.default_accounts.cash', $bank->id);
        Config::set('erp.default_accounts.receivable', $receivable->id);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'receivable_account_id' => $receivable->id,
        ]);

        $this->service = app(PaymentService::class);
    }

    public function test_a_second_complete_on_a_stale_payment_is_rejected_and_posts_one_journal(): void
    {
        $payment = $this->pendingPayment(500);
        $stale = PaymentReceived::findOrFail($payment->id);

        $this->service->complete($payment);

        $this->assertRejected(fn () => $this->service->complete($stale));
        $this->assertSame(1, $this->journalsFor($payment)->count());
    }

    public function test_a_second_void_on_a_stale_payment_is_rejected(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400);
        $this->service->allocate($payment, $invoice, 400);
        $payment = $this->service->complete($payment);
        $stale = PaymentReceived::with('allocations.invoice')->findOrFail($payment->id);

        $this->service->void($payment, 'duplicate');

        $this->assertRejected(fn () => $this->service->void($stale, 'duplicate again'));
        $this->assertInvoiceBalance($invoice, paid: '0', due: '1000', status: Invoice::STATUS_SENT);
        $this->assertSame(1, substr_count((string) $payment->fresh()->notes, 'Voided:'));
        $this->assertSame(JournalEntry::STATUS_VOIDED, JournalEntry::findOrFail($payment->journal_entry_id)->status);
    }

    public function test_void_keeps_a_payment_recorded_on_the_invoice_meanwhile(): void
    {
        $invoice = $this->invoice(1000);
        $first = $this->pendingPayment(400);
        $this->service->allocate($first, $invoice, 400);
        $stale = PaymentReceived::with('allocations.invoice')->findOrFail($first->id);

        $this->service->allocate($this->pendingPayment(300), $invoice, 300);
        $this->service->void($stale, 'wrong customer');

        $this->assertInvoiceBalance($invoice, paid: '300', due: '700', status: Invoice::STATUS_PARTIAL);
    }

    public function test_void_rolls_back_when_the_journal_cannot_be_voided(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400);
        $this->service->allocate($payment, $invoice, 400);
        $payment = $this->service->complete($payment);
        JournalEntry::whereKey($payment->journal_entry_id)->update(['status' => JournalEntry::STATUS_VOIDED]);

        $this->assertRejected(fn () => $this->service->void($payment, 'reason'));

        $this->assertSame(PaymentReceived::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame(1, PaymentAllocation::where('payment_received_id', $payment->id)->count());
        $this->assertInvoiceBalance($invoice, paid: '400', due: '600', status: Invoice::STATUS_PARTIAL);
    }

    public function test_a_second_bounce_on_a_stale_payment_is_rejected(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400, PaymentReceived::METHOD_CHEQUE);
        $this->service->allocate($payment, $invoice, 400);
        $payment = $this->service->complete($payment);
        app(JournalService::class)->postEntry($payment->journalEntry);
        $stale = PaymentReceived::findOrFail($payment->id);

        $this->service->recordBounce($payment, 'insufficient funds');

        $this->assertRejected(fn () => $this->service->recordBounce($stale, 'insufficient funds'));
        $this->assertInvoiceBalance($invoice, paid: '0', due: '1000', status: Invoice::STATUS_SENT);
        $this->assertSame(1, JournalEntry::where('reversal_of_id', $payment->journal_entry_id)->count());
    }

    public function test_bounce_rolls_back_when_the_journal_cannot_be_reversed(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400, PaymentReceived::METHOD_CHEQUE);
        $this->service->allocate($payment, $invoice, 400);
        $payment = $this->service->complete($payment);
        JournalEntry::whereKey($payment->journal_entry_id)->update(['status' => JournalEntry::STATUS_VOIDED]);

        $this->assertRejected(fn () => $this->service->recordBounce($payment, 'insufficient funds'));

        $this->assertSame(PaymentReceived::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertInvoiceBalance($invoice, paid: '400', due: '600', status: Invoice::STATUS_PARTIAL);
    }

    public function test_a_bounced_payment_cannot_be_voided_and_reversed_again(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400, PaymentReceived::METHOD_CHEQUE);
        $this->service->allocate($payment, $invoice, 400);
        $payment = $this->service->complete($payment);
        $this->service->recordBounce($payment, 'insufficient funds');

        $this->assertRejected(fn () => $this->service->void($payment, 'cleanup'));

        $this->assertInvoiceBalance($invoice, paid: '0', due: '1000', status: Invoice::STATUS_SENT);
    }

    public function test_create_rolls_back_when_the_overpayment_credit_cannot_be_recorded(): void
    {
        $invoice = $this->invoice(1000);
        CustomerCredit::creating(function (): void {
            throw new \RuntimeException('credit ledger unavailable');
        });

        try {
            $this->service->create([
                'organization_id' => $this->organization->id,
                'branch_id' => $this->branch->id,
                'customer_id' => $this->customer->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
                'amount' => 1500,
                'currency_code' => 'SAR',
                'exchange_rate' => 1,
                'status' => PaymentReceived::STATUS_PENDING,
                'created_by' => $this->user->id,
            ], [['invoice_id' => $invoice->id, 'amount' => 1000]]);
            $this->fail('A failed overpayment credit must fail the payment.');
        } catch (\RuntimeException $e) {
            $this->assertSame('credit ledger unavailable', $e->getMessage());
        }

        $this->assertSame(0, PaymentReceived::count());
        $this->assertInvoiceBalance($invoice, paid: '0', due: '1000', status: Invoice::STATUS_SENT);
    }

    public function test_deallocate_keeps_a_payment_recorded_on_the_invoice_meanwhile(): void
    {
        $invoice = $this->invoice(1000);
        $first = $this->pendingPayment(400);
        $this->service->allocate($first, $invoice, 400);
        $allocation = PaymentAllocation::with('invoice')->where('payment_received_id', $first->id)->sole();

        $this->service->allocate($this->pendingPayment(300), $invoice, 300);
        $this->service->deallocate($allocation);

        $this->assertInvoiceBalance($invoice, paid: '300', due: '700', status: Invoice::STATUS_PARTIAL);
    }

    public function test_a_second_deallocate_of_the_same_allocation_is_rejected(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400);
        $this->service->allocate($payment, $invoice, 400);
        $allocation = PaymentAllocation::where('payment_received_id', $payment->id)->sole();

        $this->service->deallocate($allocation);

        $this->assertRejected(fn () => $this->service->deallocate($allocation));
        $this->assertInvoiceBalance($invoice, paid: '0', due: '1000', status: Invoice::STATUS_SENT);
    }

    public function test_deleting_a_payment_removes_its_allocations_and_credit(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->service->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
            'amount' => 500,
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'status' => PaymentReceived::STATUS_PENDING,
            'created_by' => $this->user->id,
        ], [['invoice_id' => $invoice->id, 'amount' => 400]]);

        $this->apiDelete("/sales/payments-received/{$payment->uuid}")->assertOk();

        $this->assertNull(PaymentReceived::find($payment->id));
        $this->assertSame(0, PaymentAllocation::where('payment_received_id', $payment->id)->count());
        $this->assertFalse((bool) CustomerCredit::where('source_id', $payment->id)->sole()->is_active);
        $this->assertInvoiceBalance($invoice, paid: '0', due: '1000', status: Invoice::STATUS_SENT);
    }

    public function test_deleting_a_payment_is_all_or_nothing(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->pendingPayment(400);
        $this->service->allocate($payment, $invoice, 400);
        PaymentReceived::deleting(function (): void {
            throw new \RuntimeException('delete failed');
        });

        $this->apiDelete("/sales/payments-received/{$payment->uuid}")->assertStatus(500);

        $this->assertSame(1, PaymentAllocation::where('payment_received_id', $payment->id)->count());
        $this->assertInvoiceBalance($invoice, paid: '400', due: '600', status: Invoice::STATUS_PARTIAL);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->fail('The transition was expected to be rejected.');
    }

    private function assertInvoiceBalance(Invoice $invoice, string $paid, string $due, string $status): void
    {
        $invoice = $invoice->fresh();

        $this->assertSame(0, bccomp((string) $invoice->amount_paid, $paid, 4), "amount_paid is {$invoice->amount_paid}");
        $this->assertSame(0, bccomp((string) $invoice->amount_due, $due, 4), "amount_due is {$invoice->amount_due}");
        $this->assertSame($status, $invoice->status);
    }

    private function journalsFor(PaymentReceived $payment)
    {
        return JournalEntry::where('source_type', PaymentReceived::class)->where('source_id', $payment->id);
    }

    private function invoice(float $total): Invoice
    {
        return Invoice::factory()->sent()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'total' => $total,
            'amount_due' => $total,
            'currency_code' => 'SAR',
            'due_date' => now()->addDays(30),
        ]);
    }

    private function pendingPayment(float $amount, string $method = PaymentReceived::METHOD_BANK_TRANSFER): PaymentReceived
    {
        return PaymentReceived::factory()->pending()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'payment_date' => now(),
            'payment_method' => $method,
            'amount' => $amount,
            'base_amount' => $amount,
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'created_by' => $this->user->id,
        ]);
    }

    private function account(string $code, string $name, string $subType): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => $subType,
            'code' => $code,
            'name' => $name,
            'is_system' => true,
            'currency_code' => null,
        ]);
    }
}
