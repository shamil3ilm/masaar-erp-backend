<?php

declare(strict_types=1);

namespace Tests\Feature\Aml;

use App\Jobs\RunAmlTransactionScreeningJob;
use App\Models\Accounting\Account;
use App\Models\Aml\AmlTransactionFlag;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Services\Aml\AmlMonitoringService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The wiring between a business transaction and its AML screening: which
 * document queues a screening, how many, what the screening is told, and what
 * a screening that fails does to the document that triggered it.
 *
 * What each control detects is covered by AmlMonitoringServiceTest; these
 * tests only prove the screening is reached, reached once, and cannot reach
 * back into the invoice or the payment.
 */
class AmlTransactionScreeningWiringTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    private Account $arAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->setUpPaymentAccounts();
    }

    // ----------------------------------------------------------------
    // Invoice
    // ----------------------------------------------------------------

    public function test_creating_an_invoice_queues_one_screening(): void
    {
        Queue::fake();

        $this->createInvoice('25000');

        Queue::assertPushed(RunAmlTransactionScreeningJob::class, 1);
        $this->assertQueuedAfterCommit();
    }

    public function test_the_screening_of_an_invoice_flags_that_invoice_for_its_own_organization(): void
    {
        // No Queue::fake: the queue connection is sync under test, so the job
        // dispatched by create() runs and the flag it leaves is the proof that
        // the screening was told which transaction it was screening.
        $invoice = $this->createInvoice('25000');

        $flag = AmlTransactionFlag::withoutGlobalScopes()->sole();

        $this->assertSame('invoice', $flag->transaction_type);
        $this->assertSame($invoice->id, $flag->transaction_id);
        $this->assertSame($this->organization->id, $flag->organization_id);
        $this->assertSame($this->customer->id, $flag->contact_id);
        $this->assertSame('25000.0000', $flag->amount);
        $this->assertSame('SAR', $flag->currency);
        $this->assertSame(AmlTransactionFlag::THRESHOLD_BREACH, $flag->flag_reason);
    }

    public function test_an_invoice_is_screened_only_once_its_callers_transaction_commits(): void
    {
        // afterCommit() holds the screening back until the caller's own
        // transaction commits, so a caller that rolls back - a sales order
        // conversion that fails further on - leaves no flag against an invoice
        // that does not exist.
        DB::transaction(function (): void {
            $this->createInvoice('25000');

            $this->assertSame(
                0,
                AmlTransactionFlag::withoutGlobalScopes()->count(),
                'The invoice was screened while its caller could still roll it back.',
            );
        });

        $this->assertSame(1, AmlTransactionFlag::withoutGlobalScopes()->count());
    }

    public function test_a_screening_that_fails_leaves_the_invoice_and_reports_the_failure(): void
    {
        Log::spy();
        $this->screeningThrows('invoice screening exploded');

        $invoice = $this->createInvoice('25000');

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        // The job records the failure, and the dispatch site records that the
        // screening of this invoice did not happen.
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'RunAmlTransactionScreeningJob failed'
                && $context['transaction_type'] === 'invoice'
                && $context['transaction_id'] === $invoice->id
                && $context['error'] === 'invoice screening exploded'
        );
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'AML screening dispatch failed for invoice'
                && $context['invoice_id'] === $invoice->id
        );
    }

    // ----------------------------------------------------------------
    // Payment
    // ----------------------------------------------------------------

    public function test_completing_a_payment_queues_one_screening_and_recording_it_does_not(): void
    {
        Queue::fake();

        $payment = app(PaymentService::class)->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
            'amount' => '25000',
            'currency_code' => 'SAR',
        ]);

        // A pending payment has not reached the ledger and is not screened.
        Queue::assertNotPushed(RunAmlTransactionScreeningJob::class);

        app(PaymentService::class)->complete($payment);

        Queue::assertPushed(RunAmlTransactionScreeningJob::class, 1);
        $this->assertQueuedAfterCommit();
    }

    public function test_completing_a_payment_twice_screens_it_once(): void
    {
        Queue::fake();

        $payment = $this->pendingPayment('25000');

        app(PaymentService::class)->complete($payment);

        try {
            app(PaymentService::class)->complete($payment->fresh());
            $this->fail('A completed payment was completed a second time.');
        } catch (\InvalidArgumentException) {
            // expected: only a pending payment can be completed
        }

        Queue::assertPushed(RunAmlTransactionScreeningJob::class, 1);
    }

    public function test_the_screening_of_a_payment_flags_that_payment_for_its_own_organization(): void
    {
        $payment = $this->pendingPayment('25000');

        app(PaymentService::class)->complete($payment);

        $flag = AmlTransactionFlag::withoutGlobalScopes()->sole();

        $this->assertSame('payment', $flag->transaction_type);
        $this->assertSame($payment->id, $flag->transaction_id);
        $this->assertSame($this->organization->id, $flag->organization_id);
        $this->assertSame($this->customer->id, $flag->contact_id);
        $this->assertSame('25000.0000', $flag->amount);
        $this->assertSame('SAR', $flag->currency);
        $this->assertSame(AmlTransactionFlag::THRESHOLD_BREACH, $flag->flag_reason);
    }

    public function test_a_screening_that_fails_leaves_the_payment_completed_and_reports_the_failure(): void
    {
        Log::spy();
        $this->screeningThrows('payment screening exploded');

        $payment = $this->pendingPayment('25000');

        $completed = app(PaymentService::class)->complete($payment);

        $this->assertSame(PaymentReceived::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->journal_entry_id);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'RunAmlTransactionScreeningJob failed'
                && $context['transaction_type'] === 'payment'
                && $context['transaction_id'] === $payment->id
                && $context['error'] === 'payment screening exploded'
        );
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'AML screening dispatch failed for payment'
                && $context['payment_id'] === $payment->id
        );
    }

    // ----------------------------------------------------------------
    // The job on a worker
    // ----------------------------------------------------------------

    public function test_the_job_screens_the_organization_it_names_with_no_authenticated_user(): void
    {
        // A queue worker has no authenticated user, so the organization the
        // flag lands on can only come from the payload.
        $other = Organization::factory()->create();
        auth()->forgetGuards();

        (new RunAmlTransactionScreeningJob('payment', 4242, 25000.0, 'AED', $other->id, null))
            ->handle(app(AmlMonitoringService::class));

        $flag = AmlTransactionFlag::withoutGlobalScopes()->sole();

        $this->assertSame($other->id, $flag->organization_id);
        $this->assertSame(4242, $flag->transaction_id);
        $this->assertSame('AED', $flag->currency);
        $this->assertNull($flag->contact_id);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * The queued screening is held until the surrounding transaction commits
     * and is addressed to a queue of its own, so a worker picks it up and a
     * rollback leaves nothing behind. The fake records a push whether or not
     * it was deferred, so the deferral is read off the job itself.
     */
    private function assertQueuedAfterCommit(): void
    {
        Queue::assertPushed(
            RunAmlTransactionScreeningJob::class,
            fn (RunAmlTransactionScreeningJob $job): bool => $job->afterCommit === true
                && $job->queue === 'aml-monitoring',
        );
    }

    private function createInvoice(string $unitPrice): Invoice
    {
        return app(InvoiceService::class)->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'SAR',
        ], [
            ['description' => 'Consulting', 'quantity' => '1', 'unit_price' => $unitPrice, 'tax_rate' => '0'],
        ]);
    }

    private function pendingPayment(string $amount): PaymentReceived
    {
        return PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_PENDING,
            'amount' => $amount,
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'base_amount' => $amount,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Replaces the monitoring service with one that fails, so the screening
     * fails the way a down sanctions list or a locked table would.
     */
    private function screeningThrows(string $message): void
    {
        $this->mock(AmlMonitoringService::class, function ($mock) use ($message): void {
            $mock->shouldReceive('screenTransaction')->andThrow(new \RuntimeException($message));
            $mock->shouldIgnoreMissing();
        });
    }

    private function setUpPaymentAccounts(): void
    {
        $this->arAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_RECEIVABLE,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'is_system' => true,
            'currency_code' => null,
        ]);

        $bankAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_BANK,
            'code' => '1010',
            'name' => 'Cash at Bank',
            'is_system' => true,
            'currency_code' => null,
        ]);

        Config::set('erp.default_accounts.cash', $bankAccount->id);
        Config::set('erp.default_accounts.receivable', $this->arAccount->id);

        $this->customer->update(['receivable_account_id' => $this->arAccount->id]);
    }
}
