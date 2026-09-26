<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * The cash flow statement over movements the test posts: the opening balance,
 * the section each movement falls in, the period boundaries, and the totals a
 * busy period has to add up to.
 */
class CashFlowReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private Account $bank;

    private Account $sales;

    private Account $equipment;

    private Account $capital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.reports.view']);

        $this->bank = $this->accountFor($this->organization->id, '1010', 'asset', 'bank');
        $this->sales = $this->accountFor($this->organization->id, '4000', 'income', 'sales');
        $this->equipment = $this->accountFor($this->organization->id, '1500', 'asset', 'fixed_asset');
        $this->capital = $this->accountFor($this->organization->id, '3000', 'equity', 'capital');
    }

    public function test_each_section_carries_its_movements_and_the_closing_balance_follows(): void
    {
        $this->receive('2026-02-20', '5000.0000', $this->capital, null);

        $this->receive('2026-03-01', '1200.0000', $this->sales, 'App\\Models\\Sales\\Invoice');
        $this->spend('2026-03-10', '300.0000', $this->sales, 'App\\Models\\Purchase\\Bill');
        $this->spend('2026-03-15', '2000.0000', $this->equipment, 'App\\Models\\Accounting\\FixedAsset');
        $this->receive('2026-03-31', '800.0000', $this->capital, null);

        $report = $this->cashFlow('2026-03-01', '2026-03-31');

        $this->assertSame('5000.0000', $this->money($report['opening_balance']));
        $this->assertSame('900.0000', $this->money($report['operating_activities']['total']));
        $this->assertSame('-2000.0000', $this->money($report['investing_activities']['total']));
        $this->assertSame('800.0000', $this->money($report['financing_activities']['total']));
        $this->assertSame('-300.0000', $this->money($report['net_cash_change']));
        $this->assertSame('4700.0000', $this->money($report['closing_balance']));

        $this->assertCount(2, $report['operating_activities']['items']);
        $this->assertCount(1, $report['investing_activities']['items']);
        $this->assertCount(1, $report['financing_activities']['items']);
    }

    public function test_another_organizations_cash_is_neither_in_the_opening_balance_nor_the_movements(): void
    {
        $other = Organization::factory()->create();
        $theirBank = $this->accountFor($other->id, '1010-X', 'asset', 'bank');
        $theirCapital = $this->accountFor($other->id, '3000-X', 'equity', 'capital');

        $this->receive('2026-02-01', '100.0000', $this->capital, null);
        $this->receive('2026-03-05', '50.0000', $this->sales, 'App\\Models\\Sales\\Invoice');

        $this->postEntry($other->id, '2026-02-01', [
            [$theirBank, '90000.0000', '0.0000'],
            [$theirCapital, '0.0000', '90000.0000'],
        ]);
        $this->postEntry($other->id, '2026-03-05', [
            [$theirBank, '7000.0000', '0.0000'],
            [$theirCapital, '0.0000', '7000.0000'],
        ]);

        $report = $this->cashFlow('2026-03-01', '2026-03-31');

        $this->assertSame('100.0000', $this->money($report['opening_balance']));
        $this->assertSame('50.0000', $this->money($report['net_cash_change']));
        $this->assertSame('150.0000', $this->money($report['closing_balance']));
    }

    public function test_the_period_holds_its_first_and_last_day_and_the_day_before_opens_it(): void
    {
        $this->receive('2026-02-28', '10.0000', $this->capital, null);
        $this->receive('2026-03-01', '100.0000', $this->capital, null);
        $this->receive('2026-03-31', '20.0000', $this->capital, null);
        $this->receive('2026-04-01', '7.0000', $this->capital, null);

        $report = $this->cashFlow('2026-03-01', '2026-03-31');

        $this->assertSame('10.0000', $this->money($report['opening_balance']));
        $this->assertSame('120.0000', $this->money($report['net_cash_change']));
        $this->assertSame('130.0000', $this->money($report['closing_balance']));
    }

    public function test_a_period_with_no_movement_reports_zeroes_and_carries_the_opening_balance(): void
    {
        $this->receive('2026-02-10', '400.0000', $this->capital, null);

        $report = $this->cashFlow('2026-03-01', '2026-03-31');

        $this->assertSame('400.0000', $this->money($report['opening_balance']));
        $this->assertSame([], $report['operating_activities']['items']);
        $this->assertSame('0.0000', $this->money($report['operating_activities']['total']));
        $this->assertSame('0.0000', $this->money($report['investing_activities']['total']));
        $this->assertSame('0.0000', $this->money($report['financing_activities']['total']));
        $this->assertSame('0.0000', $this->money($report['net_cash_change']));
        $this->assertSame('400.0000', $this->money($report['closing_balance']));
    }

    public function test_only_posted_entries_move_the_cash(): void
    {
        $this->receive('2026-03-05', '100.0000', $this->capital, null);
        $this->receive('2026-03-06', '900.0000', $this->capital, null, ['status' => JournalEntry::STATUS_DRAFT]);
        $this->receive('2026-03-07', '800.0000', $this->capital, null, ['status' => JournalEntry::STATUS_VOIDED]);

        $this->assertSame('100.0000', $this->money($this->cashFlow('2026-03-01', '2026-03-31')['net_cash_change']));
    }

    public function test_a_period_past_the_listing_cap_still_totals_every_movement(): void
    {
        // The statement lists at most 2000 movements. The totals are what the
        // reader banks on, so they have to cover the ones past the cap too.
        $entry = $this->postEntry($this->organization->id, '2026-03-10', [
            [$this->bank, '1.0000', '0.0000'],
            [$this->capital, '0.0000', '1.0000'],
        ]);

        $this->appendCashLines($entry->id, 2100, '1.0000');

        $report = $this->cashFlow('2026-03-01', '2026-03-31');

        $this->assertCount(2000, $report['financing_activities']['items']);
        $this->assertSame('2101.0000', $this->money($report['financing_activities']['total']));
        $this->assertSame('2101.0000', $this->money($report['net_cash_change']));
        $this->assertSame('2101.0000', $this->money($report['closing_balance']));
    }

    /** Cash in: the bank account is debited and $against credited. */
    private function receive(string $date, string $amount, Account $against, ?string $sourceType, array $attributes = []): void
    {
        $this->postEntry($this->organization->id, $date, [
            [$this->bank, $amount, '0.0000'],
            [$against, '0.0000', $amount],
        ], ['source_type' => $sourceType, ...$attributes]);
    }

    /** Cash out: $against is debited and the bank account credited. */
    private function spend(string $date, string $amount, Account $against, ?string $sourceType, array $attributes = []): void
    {
        $this->postEntry($this->organization->id, $date, [
            [$against, $amount, '0.0000'],
            [$this->bank, '0.0000', $amount],
        ], ['source_type' => $sourceType, ...$attributes]);
    }

    /** $count further bank debits on $entryId, written straight to the table. */
    private function appendCashLines(int $entryId, int $count, string $amount): void
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'journal_entry_id' => $entryId,
                'account_id' => $this->bank->id,
                'debit' => $amount,
                'credit' => '0.0000',
                'base_debit' => $amount,
                'base_credit' => '0.0000',
                'line_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('journal_entry_lines')->insert($chunk);
        }
    }

    private function cashFlow(string $start, string $end): array
    {
        return $this->apiGet("/reports/financial/cash-flow?start_date={$start}&end_date={$end}")
            ->assertOk()
            ->json('data');
    }
}
