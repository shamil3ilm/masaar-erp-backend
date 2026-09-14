<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Accounting\Account;
use App\Models\Core\RecurringProfile;
use App\Models\Core\RecurringProfileLog;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use App\Orchestrators\Core\RunRecurringProfilesOrchestrator;
use App\Services\Core\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * A recurring run creates its document in one transaction. Auto-sending then
 * goes through the service that sends the document, after that transaction
 * has committed, and a failure to create the document is still logged.
 */
class RecurringTransactionRunTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private RecurringTransactionService $service;
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        Config::set('erp.default_accounts.receivable',
            $this->ledgerAccount('1100', 'Accounts Receivable', Account::TYPE_ASSET, Account::SUBTYPE_RECEIVABLE)->id);
        Config::set('erp.default_accounts.sales',
            $this->ledgerAccount('4000', 'Sales Revenue', Account::TYPE_INCOME, Account::SUBTYPE_SALES)->id);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->service = app(RecurringTransactionService::class);
    }

    public function test_an_auto_sent_invoice_is_posted_with_its_journal_entry(): void
    {
        $profile = $this->invoiceProfile($this->sourceInvoice(1000), autoSend: true);

        $result = app(RunRecurringProfilesOrchestrator::class)->run($profile);

        $invoice = Invoice::findOrFail($result['document_id']);
        $this->assertSame(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertCount(1, $invoice->lines);
    }

    public function test_an_invoice_that_cannot_be_sent_stays_a_draft_and_the_failure_is_recorded(): void
    {
        $profile = $this->invoiceProfile($this->sourceInvoice(1000), autoSend: true);
        Config::set('erp.default_accounts.receivable', null);

        $result = app(RunRecurringProfilesOrchestrator::class)->run($profile);

        $this->assertSame(Invoice::STATUS_DRAFT, Invoice::findOrFail($result['document_id'])->status);
        $this->assertNotNull(RecurringProfileLog::findOrFail($result['log_id'])->error_message);
    }

    public function test_a_run_that_fails_is_logged(): void
    {
        $source = $this->sourceInvoice(1000);
        $profile = $this->invoiceProfile($source, autoSend: false);
        $source->lines()->delete();
        $source->forceDelete();

        try {
            $this->service->processProfile($profile);
            $this->fail('The run was expected to fail.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, RecurringProfileLog::where('status', RecurringProfileLog::STATUS_FAILED)->count());
        $this->assertSame(0, (int) $profile->fresh()->occurrences_count);
    }

    private function sourceInvoice(float $amount): Invoice
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'SAR',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'base_total' => $amount,
            'amount_due' => $amount,
            'status' => Invoice::STATUS_SENT,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Monthly retainer',
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'subtotal' => $amount,
            'total' => $amount,
        ]);

        return $invoice;
    }

    private function invoiceProfile(Invoice $source, bool $autoSend): RecurringProfile
    {
        return RecurringProfile::factory()->create([
            'organization_id' => $this->organization->id,
            'profile_type' => RecurringProfile::TYPE_INVOICE,
            'source_type' => Invoice::class,
            'source_id' => $source->id,
            'frequency' => 'monthly',
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_date' => now()->toDateString(),
            'end_date' => null,
            'max_occurrences' => null,
            'occurrences_count' => 0,
            'auto_send' => $autoSend,
            'reminder_days_before' => 3,
            'notify_on_creation' => false,
            'status' => RecurringProfile::STATUS_ACTIVE,
            'created_by' => $this->user->id,
        ]);
    }
}
