<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * The trial balance over a chart the test posts itself: the figure per account,
 * the as-of boundary, the entry statuses that count, and the base-currency
 * amounts the rest of the statements are built from.
 */
class TrialBalanceReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private Account $cash;

    private Account $sales;

    private Account $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.reports.view']);

        $this->cash = $this->accountFor($this->organization->id, '1000', 'asset', 'cash');
        $this->sales = $this->accountFor($this->organization->id, '4000', 'income', 'sales');
        $this->rent = $this->accountFor($this->organization->id, '5000', 'expense', 'operating_expense');
    }

    public function test_each_account_carries_its_net_balance_on_its_normal_side(): void
    {
        $this->postEntry($this->organization->id, '2026-03-01', [
            [$this->cash, '1500.0000', '0.0000'],
            [$this->sales, '0.0000', '1500.0000'],
        ]);
        $this->postEntry($this->organization->id, '2026-03-31', [
            [$this->rent, '400.0000', '0.0000'],
            [$this->cash, '0.0000', '400.0000'],
        ]);

        $report = $this->trialBalance('2026-03-31');

        $cash = $this->lineFor($report['lines'], '1000');
        $this->assertSame('1100.0000', $this->money($cash['debit']));
        $this->assertSame('0.0000', $this->money($cash['credit']));

        $sales = $this->lineFor($report['lines'], '4000');
        $this->assertSame('0.0000', $this->money($sales['debit']));
        $this->assertSame('1500.0000', $this->money($sales['credit']));

        $rent = $this->lineFor($report['lines'], '5000');
        $this->assertSame('400.0000', $this->money($rent['debit']));
        $this->assertSame('0.0000', $this->money($rent['credit']));

        $this->assertSame('1500.0000', $this->money($report['totals']['debit']));
        $this->assertSame('1500.0000', $this->money($report['totals']['credit']));
        $this->assertTrue($report['is_balanced']);
    }

    public function test_another_organizations_postings_stay_out_of_the_total(): void
    {
        $other = Organization::factory()->create();
        $theirCash = $this->accountFor($other->id, '1000-X', 'asset', 'cash');
        $theirSales = $this->accountFor($other->id, '4000-X', 'income', 'sales');

        $this->postEntry($this->organization->id, '2026-03-10', [
            [$this->cash, '250.0000', '0.0000'],
            [$this->sales, '0.0000', '250.0000'],
        ]);
        $this->postEntry($other->id, '2026-03-10', [
            [$theirCash, '9000.0000', '0.0000'],
            [$theirSales, '0.0000', '9000.0000'],
        ]);

        $report = $this->trialBalance('2026-03-31');

        $this->assertSame(['1000', '4000'], array_column($report['lines'], 'account_code'));
        $this->assertSame('250.0000', $this->money($report['totals']['debit']));
        $this->assertSame('250.0000', $this->money($report['totals']['credit']));
    }

    public function test_the_as_of_date_takes_that_days_postings_and_stops_there(): void
    {
        $this->postEntry($this->organization->id, '2026-01-15', [
            [$this->cash, '100.0000', '0.0000'],
            [$this->sales, '0.0000', '100.0000'],
        ]);
        $this->postEntry($this->organization->id, '2026-03-31', [
            [$this->cash, '30.0000', '0.0000'],
            [$this->sales, '0.0000', '30.0000'],
        ]);
        $this->postEntry($this->organization->id, '2026-04-01', [
            [$this->cash, '7.0000', '0.0000'],
            [$this->sales, '0.0000', '7.0000'],
        ]);

        // A trial balance has no lower bound: everything up to the date counts.
        $this->assertSame('130.0000', $this->money($this->trialBalance('2026-03-31')['totals']['debit']));
        $this->assertSame('137.0000', $this->money($this->trialBalance('2026-04-01')['totals']['debit']));
    }

    public function test_an_empty_ledger_reports_zero_rather_than_null(): void
    {
        $report = $this->trialBalance('2026-03-31');

        $this->assertSame([], $report['lines']);
        $this->assertSame('0.0000', $this->money($report['totals']['debit']));
        $this->assertSame('0.0000', $this->money($report['totals']['credit']));
        $this->assertTrue($report['is_balanced']);
    }

    public function test_only_posted_entries_count_and_a_posted_reversal_cancels_its_original(): void
    {
        $original = $this->postEntry($this->organization->id, '2026-03-05', [
            [$this->cash, '600.0000', '0.0000'],
            [$this->sales, '0.0000', '600.0000'],
        ]);

        // A reversal is its own posted entry, so it nets the original off.
        $this->postEntry($this->organization->id, '2026-03-06', [
            [$this->cash, '0.0000', '600.0000'],
            [$this->sales, '600.0000', '0.0000'],
        ], ['reversal_of_id' => $original->id]);

        $this->postEntry($this->organization->id, '2026-03-07', [
            [$this->cash, '999.0000', '0.0000'],
            [$this->sales, '0.0000', '999.0000'],
        ], ['status' => JournalEntry::STATUS_DRAFT]);

        $this->postEntry($this->organization->id, '2026-03-08', [
            [$this->cash, '777.0000', '0.0000'],
            [$this->sales, '0.0000', '777.0000'],
        ], ['status' => JournalEntry::STATUS_VOIDED]);

        $report = $this->trialBalance('2026-03-31');

        // An account whose postings net off keeps its line, carrying zeroes:
        // it had activity in the period, so the trial balance still lists it.
        $this->assertSame(['1000', '4000'], array_column($report['lines'], 'account_code'));
        $this->assertSame('0.0000', $this->money($this->lineFor($report['lines'], '1000')['debit']));
        $this->assertSame('0.0000', $this->money($this->lineFor($report['lines'], '4000')['credit']));
        $this->assertSame('0.0000', $this->money($report['totals']['debit']));
        $this->assertSame('0.0000', $this->money($report['totals']['credit']));
    }

    public function test_a_foreign_currency_entry_is_reported_in_the_base_currency(): void
    {
        // 100 USD at 3.75 is 375 SAR. The balance sheet and the P&L both read
        // the base columns, so a trial balance kept in the entry currency
        // cannot be reconciled against either.
        $this->postEntry($this->organization->id, '2026-03-10', [
            [$this->cash, '100.0000', '0.0000'],
            [$this->sales, '0.0000', '100.0000'],
        ], ['currency_code' => 'USD', 'exchange_rate' => '3.75000000']);

        $report = $this->trialBalance('2026-03-31');

        $this->assertSame('375.0000', $this->money($this->lineFor($report['lines'], '1000')['debit']));
        $this->assertSame('375.0000', $this->money($this->lineFor($report['lines'], '4000')['credit']));
        $this->assertSame('375.0000', $this->money($report['totals']['debit']));
    }

    private function trialBalance(string $asOfDate): array
    {
        return $this->apiGet("/reports/financial/trial-balance?as_of_date={$asOfDate}")
            ->assertOk()
            ->json('data');
    }
}
