<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FxForward;
use App\Models\Accounting\JournalEntry;
use App\Models\System\Setting;
use App\Services\Accounting\FxDerivativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Settling an FX forward books its realised gain or loss as a balanced entry:
 * the derivative balance moves against the account the organization mapped for
 * the side the sign says. Without that mapping nothing is posted at all.
 */
class FxRealisedGainLossPostingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private Account $derivative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.fx.manage']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod('2025-06-30');

        $this->derivative = $this->ledgerAccount('1810', 'FX Derivative', Account::TYPE_ASSET, Account::SUBTYPE_OTHER_ASSET);
    }

    public function test_a_realised_gain_credits_the_mapped_gain_account_against_the_derivative_balance(): void
    {
        $gainAccount = $this->ledgerAccount('4910', 'FX Gain', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);
        Setting::set('accounting', 'fx_gain_account_id', $gainAccount->id, null, $this->organization->id);

        // (3.78 - 3.75) x 100,000 notional is a realised gain of 3,000.
        app(FxDerivativeService::class)->settle($this->forward(), 3.78, Carbon::parse('2025-06-30'));

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame(0, bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $this->derivative->id)->debit, '3000', 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $gainAccount->id)->credit, '3000', 4));
    }

    public function test_a_realised_loss_debits_the_mapped_loss_account_against_the_derivative_balance(): void
    {
        $lossAccount = $this->ledgerAccount('5910', 'FX Loss', Account::TYPE_EXPENSE, Account::SUBTYPE_OTHER_EXPENSE);
        Setting::set('accounting', 'fx_loss_account_id', $lossAccount->id, null, $this->organization->id);

        // (3.74 - 3.75) x 100,000 notional is a realised loss of 1,000.
        app(FxDerivativeService::class)->settle($this->forward(), 3.74, Carbon::parse('2025-06-30'));

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(0, bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $lossAccount->id)->debit, '1000', 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $this->derivative->id)->credit, '1000', 4));
    }

    public function test_settling_without_the_mapped_account_is_refused_and_settles_nothing(): void
    {
        $forward = $this->forward();

        $refusal = null;

        try {
            app(FxDerivativeService::class)->settle($forward, 3.78, Carbon::parse('2025-06-30'));
        } catch (InvalidArgumentException $e) {
            $refusal = $e;
        }

        $this->assertNotNull($refusal, 'Settling without a mapped gain account must be refused.');
        $this->assertStringContainsString('fx_gain_account_id', $refusal->getMessage());

        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
        $this->assertSame('active', $forward->fresh()->status);
    }

    public function test_a_forward_with_its_own_realised_account_uses_it_over_the_mapping(): void
    {
        $contractAccount = $this->ledgerAccount('4911', 'FX Result', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);

        app(FxDerivativeService::class)->settle(
            $this->forward(['realised_gain_loss_account_id' => $contractAccount->id]),
            3.78,
            Carbon::parse('2025-06-30'),
        );

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $contractAccount->id)->credit, '3000', 4));
    }

    /** @param  array<string, mixed>  $overrides */
    private function forward(array $overrides = []): FxForward
    {
        return FxForward::create(array_merge([
            'organization_id' => $this->organization->id,
            'contract_number' => 'FWD-2025-0001',
            'buy_currency' => 'USD',
            'sell_currency' => 'SAR',
            'notional_amount' => 100000,
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
