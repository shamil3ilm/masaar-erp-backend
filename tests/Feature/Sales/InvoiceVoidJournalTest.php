<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Sending an invoice records its journal entry as a draft. Voiding the invoice
 * voids that entry through JournalService::voidSourceEntry(), which discards a
 * draft and voids a posted entry.
 */
class InvoiceVoidJournalTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.send',
            'sales.invoices.void',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'payment_terms' => 30,
        ]);

        Config::set('erp.default_accounts.receivable',
            $this->ledgerAccount('1100', 'Accounts Receivable', Account::TYPE_ASSET, Account::SUBTYPE_RECEIVABLE)->id);
        Config::set('erp.default_accounts.sales',
            $this->ledgerAccount('4000', 'Sales Revenue', Account::TYPE_INCOME, Account::SUBTYPE_SALES)->id);
        Config::set('erp.default_accounts.tax_payable',
            $this->ledgerAccount('2100', 'VAT Output', Account::TYPE_LIABILITY, 'tax_payable')->id);
    }

    public function test_voiding_a_sent_invoice_voids_its_draft_journal_entry(): void
    {
        $invoiceId = $this->apiPost('/sales/invoices', [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 15],
            ],
        ])->assertStatus(201)->json('data.id');

        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        $entry = JournalEntry::findOrFail(Invoice::findOrFail($invoiceId)->journal_entry_id);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $entry->status);

        $this->apiPost("/sales/invoices/{$invoiceId}/void", ['reason' => 'Issued to the wrong customer'])
            ->assertStatus(200);

        $this->assertSame(Invoice::STATUS_VOIDED, Invoice::findOrFail($invoiceId)->status);
        $this->assertSame(JournalEntry::STATUS_VOIDED, $entry->fresh()->status);
    }
}
