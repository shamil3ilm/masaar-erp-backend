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
 * The balance sheet over a ledger the test posts itself: the figure per
 * account, which bucket each account lands in, the current year's earnings
 * carried into equity, and whether the sheet balances.
 */
class BalanceSheetReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    /** @var array<string, Account> */
    private array $account = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.reports.view']);

        foreach ([
            ['1010', 'asset', 'bank'],
            ['1200', 'asset', 'receivable'],
            ['1300', 'asset', 'other_asset'],
            ['1500', 'asset', 'fixed_asset'],
            ['2000', 'liability', 'payable'],
            ['2100', 'liability', 'tax_payable'],
            ['2500', 'liability', 'other_liability'],
            ['3000', 'equity', 'capital'],
            ['4000', 'income', 'sales'],
            ['5000', 'expense', 'operating_expense'],
        ] as [$code, $type, $subType]) {
            $this->account[$code] = $this->accountFor($this->organization->id, $code, $type, $subType);
        }
    }

    public function test_a_years_trading_leaves_the_sheet_balanced_with_earnings_in_equity(): void
    {
        $this->tradingYear();

        $report = $this->balanceSheet('2026-03-31');

        $this->assertSame('71000.0000', $this->money($this->lineFor($report['assets']['current_assets'], '1010')['amount']));
        $this->assertSame('20000.0000', $this->money($this->lineFor($report['assets']['current_assets'], '1200')['amount']));
        $this->assertSame('2000.0000', $this->money($this->lineFor($report['assets']['other_assets'], '1300')['amount']));
        $this->assertSame('40000.0000', $this->money($this->lineFor($report['assets']['fixed_assets'], '1500')['amount']));
        $this->assertSame('133000.0000', $this->money($report['assets']['total']));

        $this->assertSame('18000.0000', $this->money($report['liabilities']['total']));

        $this->assertSame('100000.0000', $this->money($this->lineFor($report['equity']['items'], '3000')['amount']));
        // Income 20000 less expenses 5000 since the start of the year.
        $this->assertSame('15000.0000', $this->money($this->lineFor($report['equity']['items'], 'RE')['amount']));
        $this->assertSame('115000.0000', $this->money($report['equity']['total']));

        $this->assertSame('133000.0000', $this->money($report['total_liabilities_and_equity']));
        $this->assertTrue($report['is_balanced']);
    }

    public function test_tax_and_card_balances_sit_with_the_current_liabilities(): void
    {
        $this->tradingYear();

        $report = $this->balanceSheet('2026-03-31');

        $current = array_column($report['liabilities']['current_liabilities'], 'account_code');
        sort($current);

        $this->assertSame(['2000', '2100'], $current);
        $this->assertSame(['2500'], array_column($report['liabilities']['long_term_liabilities'], 'account_code'));
    }

    public function test_another_organizations_ledger_is_not_on_the_sheet(): void
    {
        $other = Organization::factory()->create();
        $theirCash = $this->accountFor($other->id, '1010-X', 'asset', 'bank');
        $theirCapital = $this->accountFor($other->id, '3000-X', 'equity', 'capital');

        $this->entry('2026-03-10', '1010', '3000', '500.0000');
        $this->postEntry($other->id, '2026-03-10', [
            [$theirCash, '77000.0000', '0.0000'],
            [$theirCapital, '0.0000', '77000.0000'],
        ]);

        $report = $this->balanceSheet('2026-03-31');

        $this->assertSame(['1010'], array_column($report['assets']['current_assets'], 'account_code'));
        $this->assertSame('500.0000', $this->money($report['assets']['total']));
        $this->assertSame('500.0000', $this->money($report['equity']['total']));
    }

    public function test_the_as_of_date_takes_that_days_postings_and_stops_there(): void
    {
        $this->entry('2026-03-31', '1010', '3000', '300.0000');
        $this->entry('2026-04-01', '1010', '3000', '9.0000');

        $this->assertSame('300.0000', $this->money($this->balanceSheet('2026-03-31')['assets']['total']));
        $this->assertSame('309.0000', $this->money($this->balanceSheet('2026-04-01')['assets']['total']));
    }

    public function test_an_empty_ledger_reports_zeroes_and_still_balances(): void
    {
        $report = $this->balanceSheet('2026-03-31');

        $this->assertSame([], $report['assets']['current_assets']);
        $this->assertSame([], $report['assets']['fixed_assets']);
        $this->assertSame('0.0000', $this->money($report['assets']['total']));
        $this->assertSame('0.0000', $this->money($report['liabilities']['total']));
        // Equity always carries the current year's earnings, zero and all.
        $this->assertSame('0.0000', $this->money($this->lineFor($report['equity']['items'], 'RE')['amount']));
        $this->assertSame('0.0000', $this->money($report['equity']['total']));
        $this->assertTrue($report['is_balanced']);
    }

    public function test_only_posted_entries_reach_the_sheet(): void
    {
        $this->entry('2026-03-10', '1010', '3000', '500.0000');
        $this->entry('2026-03-11', '1010', '3000', '900.0000', ['status' => JournalEntry::STATUS_DRAFT]);
        $this->entry('2026-03-12', '1010', '3000', '800.0000', ['status' => JournalEntry::STATUS_VOIDED]);

        $this->assertSame('500.0000', $this->money($this->balanceSheet('2026-03-31')['assets']['total']));
    }

    public function test_deactivating_an_account_does_not_erase_its_balance(): void
    {
        $this->entry('2026-03-10', '1010', '3000', '500.0000');
        $this->account['1010']->update(['is_active' => false]);

        $report = $this->balanceSheet('2026-03-31');

        $this->assertSame('500.0000', $this->money($this->lineFor($report['assets']['current_assets'], '1010')['amount']));
        $this->assertTrue($report['is_balanced']);
    }

    public function test_an_unclosed_prior_year_leaves_the_sheet_out_of_balance(): void
    {
        // Equity is only ever credited with earnings since the start of the
        // as-of year, so last year's profit reaches the sheet only through a
        // year-end entry that moves it into a retained-earnings account.
        // Whether this report should reach back further, or whether an
        // organisation is simply expected to close its year, is not settled
        // here: this pins what it does today.
        $this->entry('2025-11-30', '1010', '4000', '8000.0000');
        $this->entry('2026-03-10', '1010', '3000', '500.0000');

        $report = $this->balanceSheet('2026-03-31');

        $this->assertSame('8500.0000', $this->money($report['assets']['total']));
        $this->assertSame('500.0000', $this->money($report['equity']['total']));
        $this->assertFalse($report['is_balanced']);
    }

    /** A capital contribution, a sale, an expense, a loan, VAT and two purchases. */
    private function tradingYear(): void
    {
        $this->entry('2026-01-01', '1010', '3000', '100000.0000');
        $this->entry('2026-02-10', '1200', '4000', '20000.0000');
        $this->entry('2026-03-15', '5000', '2000', '5000.0000');
        $this->entry('2026-03-20', '1010', '2500', '10000.0000');
        $this->entry('2026-03-25', '1010', '2100', '3000.0000');
        $this->entry('2026-03-31', '1500', '1010', '40000.0000');
        $this->entry('2026-03-31', '1300', '1010', '2000.0000');
    }

    private function entry(string $date, string $debitCode, string $creditCode, string $amount, array $attributes = []): void
    {
        $this->postEntry($this->organization->id, $date, [
            [$this->account[$debitCode], $amount, '0.0000'],
            [$this->account[$creditCode], '0.0000', $amount],
        ], $attributes);
    }

    private function balanceSheet(string $asOfDate): array
    {
        return $this->apiGet("/reports/financial/balance-sheet?as_of_date={$asOfDate}")
            ->assertOk()
            ->json('data');
    }
}
