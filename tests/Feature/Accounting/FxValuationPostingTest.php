<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FxForward;
use App\Models\Accounting\FxValuation;
use App\Models\Accounting\JournalEntry;
use App\Models\System\Setting;
use App\Services\Accounting\FxDerivativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Marking an FX forward to market books the period's fair-value movement as a
 * balanced entry.
 *
 * A forward carried in the general ledger is journalled or the valuation is
 * refused; a forward not carried there books nothing, which is the same way
 * the realised settlement decides.
 */
class FxValuationPostingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private Account $derivative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.fx.manage']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod('2025-03-31');

        $this->derivative = $this->ledgerAccount('1810', 'FX Derivative', Account::TYPE_ASSET, Account::SUBTYPE_OTHER_ASSET);
    }

    public function test_an_unrealised_gain_credits_the_mapped_unrealised_gain_account(): void
    {
        $gainAccount = $this->ledgerAccount('4920', 'Unrealised FX Gain', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);
        Setting::set('accounting', 'fx_unrealised_gain_account_id', $gainAccount->id, null, $this->organization->id);

        // (3.76 - 3.75) x 100,000 notional is an unrealised gain of 1,000.
        $valuation = app(FxDerivativeService::class)
            ->recordValuation($this->forward(), Carbon::parse('2025-03-31'), 3.76);

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame(0, bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $this->derivative->id)->debit, '1000', 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $gainAccount->id)->credit, '1000', 4));
        $this->assertSame($entry->id, $valuation->journal_entry_id);
    }

    public function test_an_unrealised_loss_debits_the_mapped_unrealised_loss_account(): void
    {
        $lossAccount = $this->ledgerAccount('5920', 'Unrealised FX Loss', Account::TYPE_EXPENSE, Account::SUBTYPE_OTHER_EXPENSE);
        Setting::set('accounting', 'fx_unrealised_loss_account_id', $lossAccount->id, null, $this->organization->id);

        // (3.74 - 3.75) x 100,000 notional is an unrealised loss of 1,000.
        app(FxDerivativeService::class)
            ->recordValuation($this->forward(), Carbon::parse('2025-03-31'), 3.74);

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(0, bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $lossAccount->id)->debit, '1000', 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $this->derivative->id)->credit, '1000', 4));
    }

    public function test_a_valuation_without_the_mapped_account_is_refused_and_records_nothing(): void
    {
        $refusal = null;

        try {
            app(FxDerivativeService::class)
                ->recordValuation($this->forward(), Carbon::parse('2025-03-31'), 3.76);
        } catch (RuntimeException $e) {
            $refusal = $e;
        }

        $this->assertNotNull($refusal, 'Valuing without a mapped unrealised gain account must be refused.');
        $this->assertStringContainsString('fx_unrealised_gain_account_id', $refusal->getMessage());

        // The old code kept the valuation and skipped the entry, which left a
        // fair value recorded that the ledger never heard about.
        $this->assertSame(0, FxValuation::count());
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
    }

    public function test_a_forward_not_carried_in_the_ledger_is_valued_without_an_entry(): void
    {
        $valuation = app(FxDerivativeService::class)->recordValuation(
            $this->forward(['derivative_asset_account_id' => null]),
            Carbon::parse('2025-03-31'),
            3.76,
        );

        $this->assertSame(0, bccomp((string) $valuation->fair_value, '1000', 4));
        $this->assertNull($valuation->journal_entry_id);
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
    }

    public function test_a_forward_with_its_own_unrealised_account_uses_it_over_the_mapping(): void
    {
        $contractAccount = $this->ledgerAccount('4921', 'Contract FX Result', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);

        app(FxDerivativeService::class)->recordValuation(
            $this->forward(['unrealised_gain_loss_account_id' => $contractAccount->id]),
            Carbon::parse('2025-03-31'),
            3.76,
        );

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $contractAccount->id)->credit, '1000', 4));
    }

    public function test_a_second_valuation_books_only_the_movement_since_the_first(): void
    {
        $gainAccount = $this->ledgerAccount('4920', 'Unrealised FX Gain', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);
        Setting::set('accounting', 'fx_unrealised_gain_account_id', $gainAccount->id, null, $this->organization->id);

        $service = app(FxDerivativeService::class);
        $forward = $this->forward();

        $service->recordValuation($forward, Carbon::parse('2025-03-31'), 3.76);
        $second = $service->recordValuation($forward->fresh(), Carbon::parse('2025-06-30'), 3.765);

        $this->assertSame(0, bccomp((string) $second->fair_value, '1500', 4));
        $this->assertSame(0, bccomp((string) $second->fair_value_change, '500', 4));

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->findOrFail($second->journal_entry_id);
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $gainAccount->id)->credit, '500', 4));
    }

    /** @param  array<string, mixed>  $overrides */
    private function forward(array $overrides = []): FxForward
    {
        return FxForward::create(array_merge([
            'organization_id' => $this->organization->id,
            'contract_number' => 'FWD-2025-0002',
            'buy_currency' => 'USD',
            'sell_currency' => 'SAR',
            'notional_amount' => 100000.0000,
            'forward_rate' => 3.75,
            'trade_date' => '2025-01-01',
            'maturity_date' => '2025-06-30',
            'purpose' => 'hedge',
            'status' => 'active',
            'derivative_asset_account_id' => $this->derivative->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }
}
