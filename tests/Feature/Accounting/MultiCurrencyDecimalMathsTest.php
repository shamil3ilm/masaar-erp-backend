<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Currency;
use App\Models\Accounting\CurrencyRevaluation;
use App\Models\Accounting\CurrencyRevaluationItem;
use App\Models\Accounting\ExchangeRate;
use App\Models\Accounting\ForexGainLossEntry;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\MultiCurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Multi-currency arithmetic works in decimal strings at the scale each column
 * holds, the way revalue() does.
 *
 * The figures here are the ones a double cannot state: a balance the amount
 * columns hold, a rate the rate columns hold, and a product whose last
 * ten-thousandth a float rounds away. A hyperinflated currency reaches all
 * three in ordinary use, which is why the columns are as wide as they are.
 */
class MultiCurrencyDecimalMathsTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private Account $foreignAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.multi-currency.manage']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod('2025-06-30');

        Currency::firstOrCreate(
            ['code' => 'ZWL'],
            ['name' => 'Zimbabwe Dollar', 'symbol' => 'Z', 'decimal_places' => 2],
        );

        $this->foreignAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_RECEIVABLE,
            'code' => '1215',
            'name' => 'Trade Receivables ZWL',
            'currency_code' => 'ZWL',
            'is_active' => true,
            'is_header' => false,
        ]);
    }

    public function test_the_auto_run_balance_keeps_a_total_a_float_cannot_state(): void
    {
        // Eight postings of 12,500,000,000,000 make a balance of one hundred
        // trillion, which the amount columns hold to four decimals and a float
        // can only write as 1.0E+14.
        $this->postLedgerBalance(array_fill(0, 8, '12500000000000.0000'));
        $this->previousRevaluation('0.50000000');
        $this->publishRate('0.50000100');

        $revaluation = app(MultiCurrencyService::class)
            ->autoRun($this->organization->id, '2025-06-30', 'SAR');

        // The rate moved by one hundred-thousandth, so the balance gained
        // 100,000,000 exactly.
        $this->assertSame('100000000.0000', (string) $revaluation->total_unrealized_gain);
        $this->assertSame('100000000.0000', (string) $revaluation->net_gain_loss);
        $this->assertEqualsWithDelta(1.0e14, (float) $revaluation->items->sole()->foreign_currency_balance, 0.0001);
    }

    public function test_the_auto_run_previous_rate_keeps_the_decimals_the_rate_column_holds(): void
    {
        // A rate of 0.00000012 fits the eight decimals the rate columns hold,
        // but a float can only write it as 1.2E-7.
        $this->postLedgerBalance(['1000000000.0000']);
        $this->previousRevaluation('0.00000012');
        $this->publishRate('0.00000015');

        $revaluation = app(MultiCurrencyService::class)
            ->autoRun($this->organization->id, '2025-06-30', 'SAR');

        $this->assertSame('0.00000012', (string) $revaluation->old_rate);
        $this->assertSame('0.00000015', (string) $revaluation->new_rate);
        $this->assertSame('30.0000', (string) $revaluation->total_unrealized_gain);
    }

    public function test_converting_truncates_at_the_scale_the_amount_columns_hold(): void
    {
        $this->publishRate('0.00000015');

        // 33,333,333.3333 at 0.00000015 is 4.999999999995, which is 4.9999 at
        // the four decimals the amount columns hold. A float carries the tail
        // into whatever the caller stores the result in, and rounding it lands
        // a ten-thousandth above what the ledger would post.
        $converted = app(MultiCurrencyService::class)
            ->convert(33333333.3333, 'ZWL', 'SAR', $this->organization->id, '2025-06-30');

        $this->assertSame('4.9999', $converted);
    }

    public function test_converting_without_a_rate_returns_nothing(): void
    {
        $this->assertNull(
            app(MultiCurrencyService::class)
                ->convert(1000.0, 'ZWL', 'SAR', $this->organization->id, '2025-06-30')
        );
    }

    public function test_the_stored_totals_are_the_decimal_sum_of_the_items(): void
    {
        $revaluation = $this->draftRevaluation();

        // Fifteen gains of 823,045,260.0823 come to 12,345,678,901.2345. A
        // float sums them to a value it can only write as 12345678901.235,
        // half a thousandth of a currency unit more than the items carry, and
        // the totals are what the revaluation reports.
        foreach (range(1, 15) as $ignored) {
            $this->item($revaluation, '823045260.0823');
        }

        $this->item($revaluation, '-0.2345');

        $revaluation->recalculateTotals();

        // Asserted on the revaluation the totals were written to rather than on
        // a re-read: SQLite holds a decimal column as a float, so reading one
        // back costs the digits this is about.
        $this->assertSame('12345678901.2345', (string) $revaluation->total_unrealized_gain);
        $this->assertSame('0.2345', (string) $revaluation->total_unrealized_loss);
        $this->assertSame('12345678901.0000', (string) $revaluation->net_gain_loss);
    }

    public function test_the_forex_report_totals_a_result_a_float_cannot_state(): void
    {
        // One hundred trillion fits the amount column and a float can only
        // write it as 1.0E+14, which is not a figure bcmath will take.
        $this->forexEntry('100000000000000.0000');
        $this->forexEntry('-2.5000');

        $report = app(MultiCurrencyService::class)
            ->getExchangeGainLossReport($this->organization->id);

        $this->assertSame(2, $report['summary']['count']);
        $this->assertEqualsWithDelta(1.0e14, $report['summary']['total_gains'], 0.0001);
        $this->assertEqualsWithDelta(2.5, $report['summary']['total_losses'], 0.0001);
        $this->assertEqualsWithDelta(99999999999997.5, $report['summary']['net_gain_loss'], 0.0001);
    }

    public function test_a_revaluation_with_no_items_totals_nothing(): void
    {
        $revaluation = $this->draftRevaluation();

        $revaluation->recalculateTotals();

        $this->assertSame('0.0000', (string) $revaluation->total_unrealized_gain);
        $this->assertSame('0.0000', (string) $revaluation->total_unrealized_loss);
        $this->assertSame('0.0000', (string) $revaluation->net_gain_loss);
    }

    /**
     * Post a journal entry debiting the foreign-currency account with each of
     * the given amounts, against a base-currency account.
     *
     * @param  list<string>  $debits
     */
    private function postLedgerBalance(array $debits): void
    {
        $offset = $this->ledgerAccount('2100', 'Trade Payables', Account::TYPE_LIABILITY, Account::SUBTYPE_PAYABLE);

        $entry = JournalEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'entry_date' => '2025-06-30',
            'currency_code' => 'ZWL',
            'exchange_rate' => '1.00000000',
            'status' => JournalEntry::STATUS_POSTED,
            'created_by' => $this->user->id,
        ]);

        $total = '0.0000';

        foreach ($debits as $order => $debit) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->foreignAccount->id,
                'description' => 'Opening balance',
                'debit' => $debit,
                'credit' => '0.0000',
                'line_order' => $order,
            ]);

            $total = bcadd($total, $debit, 4);
        }

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $offset->id,
            'description' => 'Opening balance offset',
            'debit' => '0.0000',
            'credit' => $total,
            'line_order' => count($debits),
        ]);
    }

    private function previousRevaluation(string $rate): CurrencyRevaluation
    {
        return CurrencyRevaluation::create([
            'organization_id' => $this->organization->id,
            'revaluation_number' => 'REVAL-2025-000001',
            'revaluation_date' => '2025-03-31',
            'currency_code' => 'ZWL',
            'base_currency' => 'SAR',
            'old_rate' => $rate,
            'new_rate' => $rate,
            'status' => CurrencyRevaluation::STATUS_POSTED,
            'created_by' => $this->user->id,
        ]);
    }

    private function draftRevaluation(): CurrencyRevaluation
    {
        return CurrencyRevaluation::create([
            'organization_id' => $this->organization->id,
            'revaluation_number' => 'REVAL-2025-000002',
            'revaluation_date' => '2025-06-30',
            'currency_code' => 'ZWL',
            'base_currency' => 'SAR',
            'old_rate' => '1.00000000',
            'new_rate' => '1.00010000',
            'status' => CurrencyRevaluation::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);
    }

    private function item(CurrencyRevaluation $revaluation, string $gainLoss): CurrencyRevaluationItem
    {
        return CurrencyRevaluationItem::create([
            'revaluation_id' => $revaluation->id,
            'account_id' => $this->foreignAccount->id,
            'account_type' => 'receivable',
            'foreign_currency_balance' => '0.0000',
            'old_base_amount' => '0.0000',
            'new_base_amount' => $gainLoss,
            'gain_loss_amount' => $gainLoss,
        ]);
    }

    private function forexEntry(string $gainLoss): ForexGainLossEntry
    {
        return ForexGainLossEntry::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'entry_type' => ForexGainLossEntry::TYPE_REALIZED,
            'transaction_type' => ForexGainLossEntry::TRANSACTION_PAYMENT,
            'source_type' => 'PaymentReceived',
            'source_id' => 1,
            'foreign_currency' => 'ZWL',
            'base_currency' => 'SAR',
            'foreign_amount' => '1000.0000',
            'original_rate' => '1.00000000',
            'settlement_rate' => '1.00000000',
            'gain_loss_amount' => $gainLoss,
            'transaction_date' => '2025-06-30',
        ]);
    }

    private function publishRate(string $rate): ExchangeRate
    {
        return ExchangeRate::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'from_currency' => 'ZWL',
            'to_currency' => 'SAR',
            'rate' => $rate,
            'rate_date' => '2025-06-30',
        ]);
    }
}
