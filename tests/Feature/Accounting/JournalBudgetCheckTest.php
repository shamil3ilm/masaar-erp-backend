<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\JournalEntry;
use App\Models\Budget\Budget;
use App\Models\Budget\BudgetLine;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Posting a debit to an expense account for a cost center is held to the
 * approved budget line for that account and cost center.
 */
class JournalBudgetCheckTest extends TestCase
{
    use AssertsRejection, BuildsLedger, RefreshDatabase, TestHelpers;

    private JournalService $journals;

    private Account $expense;

    private Account $cash;

    private CostCenter $costCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->expense = $this->ledgerAccount('6400', 'Repairs', Account::TYPE_EXPENSE, Account::SUBTYPE_OPERATING_EXPENSE);
        $this->cash = $this->ledgerAccount('1010', 'Cash', Account::TYPE_ASSET, Account::SUBTYPE_CASH);
        $this->costCenter = CostCenter::factory()->create(['organization_id' => $this->organization->id]);

        $budget = Budget::create([
            'organization_id' => $this->organization->id,
            'name' => 'Operations',
            'status' => Budget::STATUS_ACTIVE,
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
        ]);
        BudgetLine::create([
            'budget_id' => $budget->id,
            'account_id' => $this->expense->id,
            'cost_center_id' => $this->costCenter->id,
            'name' => 'Repairs',
            'total_amount' => 100,
        ]);

        $this->journals = app(JournalService::class);
    }

    public function test_a_debit_over_the_budget_is_refused(): void
    {
        $this->assertRejected(fn () => $this->postRepair(150));

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_a_debit_within_the_budget_is_posted(): void
    {
        $entry = $this->postRepair(80);

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
    }

    private function postRepair(float $amount): JournalEntry
    {
        return $this->journals->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Pump repair',
        ], [
            ['account_id' => $this->expense->id, 'debit' => $amount, 'credit' => 0, 'cost_center_id' => $this->costCenter->id],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => $amount],
        ]);
    }
}
