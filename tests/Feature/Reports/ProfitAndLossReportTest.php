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
 * The income statement over postings the test writes: the figure per account,
 * the period boundaries, the margin, and which accounts and entry statuses
 * reach the statement.
 */
class ProfitAndLossReportTest extends TestCase
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

    public function test_income_nets_its_credits_expenses_net_their_debits_and_the_margin_follows(): void
    {
        $this->sell('2026-03-10', '10000.0000');
        $this->sell('2026-03-20', '5000.0000');
        // A credit note against the income account: income is net of it.
        $this->postEntry($this->organization->id, '2026-03-21', [
            [$this->sales, '500.0000', '0.0000'],
            [$this->cash, '0.0000', '500.0000'],
        ]);
        $this->spend('2026-03-15', '3000.0000');

        $report = $this->profitAndLoss('2026-03-01', '2026-03-31');

        $this->assertSame('14500.0000', $this->money($report['income']['total']));
        $this->assertSame('14500.0000', $this->money($this->lineFor($report['income']['breakdown'], '4000')['amount']));
        $this->assertSame('3000.0000', $this->money($report['expenses']['total']));
        $this->assertSame('3000.0000', $this->money($this->lineFor($report['expenses']['breakdown'], '5000')['amount']));
        $this->assertSame('11500.0000', $this->money($report['net_profit']));
        $this->assertSame('79.3103', $this->money($report['profit_margin']));
    }

    public function test_another_organizations_trading_stays_out_of_the_statement(): void
    {
        $other = Organization::factory()->create();
        $theirCash = $this->accountFor($other->id, '1000-X', 'asset', 'cash');
        $theirSales = $this->accountFor($other->id, '4000-X', 'income', 'sales');

        $this->sell('2026-03-10', '400.0000');
        $this->postEntry($other->id, '2026-03-10', [
            [$theirCash, '9000.0000', '0.0000'],
            [$theirSales, '0.0000', '9000.0000'],
        ]);

        $report = $this->profitAndLoss('2026-03-01', '2026-03-31');

        $this->assertSame(['4000'], array_column($report['income']['breakdown'], 'account_code'));
        $this->assertSame('400.0000', $this->money($report['income']['total']));
    }

    public function test_the_period_holds_its_first_and_last_day_and_nothing_either_side(): void
    {
        $this->sell('2026-02-28', '1.0000');
        $this->sell('2026-03-01', '100.0000');
        $this->sell('2026-03-31', '20.0000');
        $this->sell('2026-04-01', '7.0000');

        $report = $this->profitAndLoss('2026-03-01', '2026-03-31');

        $this->assertSame('120.0000', $this->money($report['income']['total']));
    }

    public function test_a_period_with_no_trading_reports_zeroes_rather_than_nulls(): void
    {
        $this->sell('2026-04-10', '900.0000');

        $report = $this->profitAndLoss('2026-03-01', '2026-03-31');

        $this->assertSame('0.0000', $this->money($report['income']['total']));
        $this->assertSame([], $report['income']['breakdown']);
        $this->assertSame('0.0000', $this->money($report['expenses']['total']));
        $this->assertSame([], $report['expenses']['breakdown']);
        $this->assertSame('0.0000', $this->money($report['net_profit']));
        // No income means no denominator, so the margin is reported as zero
        // rather than dividing by it.
        $this->assertSame('0.0000', $this->money($report['profit_margin']));
    }

    public function test_only_posted_entries_reach_the_statement(): void
    {
        $this->sell('2026-03-10', '250.0000');
        $this->sell('2026-03-11', '900.0000', ['status' => JournalEntry::STATUS_DRAFT]);
        $this->sell('2026-03-12', '800.0000', ['status' => JournalEntry::STATUS_VOIDED]);

        $this->assertSame('250.0000', $this->money($this->profitAndLoss('2026-03-01', '2026-03-31')['income']['total']));
    }

    public function test_deactivating_an_account_does_not_erase_what_was_posted_to_it(): void
    {
        // Posting checks is_active when the entry is written, so an account can
        // only go inactive after its history exists. The trial balance keeps
        // that history, and a statement that drops it stops agreeing with it.
        $this->sell('2026-03-10', '1000.0000');
        $this->spend('2026-03-11', '400.0000');

        $this->rent->update(['is_active' => false]);
        $this->sales->update(['is_active' => false]);

        $report = $this->profitAndLoss('2026-03-01', '2026-03-31');

        $this->assertSame('1000.0000', $this->money($report['income']['total']));
        $this->assertSame('400.0000', $this->money($report['expenses']['total']));
        $this->assertSame('600.0000', $this->money($report['net_profit']));
    }

    public function test_a_foreign_currency_entry_reaches_the_statement_in_the_base_currency(): void
    {
        $this->sell('2026-03-10', '100.0000', ['currency_code' => 'USD', 'exchange_rate' => '3.75000000']);

        $this->assertSame('375.0000', $this->money($this->profitAndLoss('2026-03-01', '2026-03-31')['income']['total']));
    }

    private function sell(string $date, string $amount, array $attributes = []): void
    {
        $this->postEntry($this->organization->id, $date, [
            [$this->cash, $amount, '0.0000'],
            [$this->sales, '0.0000', $amount],
        ], $attributes);
    }

    private function spend(string $date, string $amount, array $attributes = []): void
    {
        $this->postEntry($this->organization->id, $date, [
            [$this->rent, $amount, '0.0000'],
            [$this->cash, '0.0000', $amount],
        ], $attributes);
    }

    private function profitAndLoss(string $start, string $end): array
    {
        return $this->apiGet("/reports/financial/profit-loss?start_date={$start}&end_date={$end}")
            ->assertOk()
            ->json('data');
    }
}
