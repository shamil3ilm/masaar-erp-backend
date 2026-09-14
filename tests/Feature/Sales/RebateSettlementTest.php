<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\RebateAccrual;
use App\Models\Sales\RebateMaster;
use App\Services\Sales\RebateSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Settling a rebate with its accounts configured records the settlement in the
 * general ledger: the accrual is cleared against the expense account.
 */
class RebateSettlementTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    public function test_settling_a_rebate_records_its_journal_entry_and_settles_the_accruals(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $accrualAccount = $this->ledgerAccount('2400', 'Rebate Accrual', Account::TYPE_LIABILITY, Account::SUBTYPE_OTHER_LIABILITY);
        $expenseAccount = $this->ledgerAccount('6100', 'Rebate Expense', Account::TYPE_EXPENSE, Account::SUBTYPE_OPERATING_EXPENSE);
        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $rebate = RebateMaster::create([
            'organization_id' => $this->organization->id,
            'name' => 'Volume rebate',
            'contact_id' => $customer->id,
            'rebate_type' => 'percentage',
            'calculation_base' => 'invoice_value',
            'rebate_rate' => 2,
            'accrual_method' => 'on_invoice',
            'valid_from' => now()->subMonth()->toDateString(),
            'accrual_account_id' => $accrualAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'status' => RebateMaster::STATUS_ACTIVE,
        ]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'currency_code' => 'SAR',
        ]);
        $accrual = RebateAccrual::create([
            'rebate_master_id' => $rebate->id,
            'invoice_id' => $invoice->id,
            'accrual_date' => now()->subDay()->toDateString(),
            'invoice_amount' => 5000,
            'rebate_amount' => 100,
            'status' => RebateAccrual::STATUS_POSTED,
        ]);

        $result = app(RebateSettlementService::class)->settle($rebate, now(), 'credit_note', $this->user->id);

        $lines = JournalEntry::with('lines')->findOrFail($result['journal_entry_id'])->lines;
        $this->assertEquals(100, (float) $lines->firstWhere('account_id', $accrualAccount->id)->debit);
        $this->assertEquals(100, (float) $lines->firstWhere('account_id', $expenseAccount->id)->credit);

        $accrual = $accrual->fresh();
        $this->assertSame(RebateAccrual::STATUS_SETTLED, $accrual->status);
        $this->assertEquals($result['journal_entry_id'], $accrual->journal_entry_id);
    }
}
