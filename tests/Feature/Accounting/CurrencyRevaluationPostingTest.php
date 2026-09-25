<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CurrencyRevaluation;
use App\Models\Accounting\JournalEntry;
use App\Models\System\Setting;
use App\Services\Accounting\MultiCurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Posting a currency revaluation books the net unrealised movement against the
 * account the organization mapped for that side. The mapping is read for what
 * it is rather than guessed from an account's name, and without it nothing is
 * posted.
 */
class CurrencyRevaluationPostingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private Account $receivable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.currency.manage']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod('2025-06-30');

        $this->receivable = $this->ledgerAccount('1210', 'Trade Receivables USD', Account::TYPE_ASSET, Account::SUBTYPE_RECEIVABLE);
    }

    public function test_a_net_unrealised_gain_credits_the_mapped_unrealised_gain_account(): void
    {
        $gainAccount = $this->ledgerAccount('4920', 'Unrealised FX Gain', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);
        Setting::set('accounting', 'fx_unrealised_gain_account_id', $gainAccount->id, null, $this->organization->id);

        // 100,000 USD revalued from 3.7500 to 3.7800 is an unrealised gain of 3,000.
        $revaluation = app(MultiCurrencyService::class)->postRevaluation($this->revaluation('3.78000000'));

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame(0, bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $this->receivable->id)->debit, '3000', 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $gainAccount->id)->credit, '3000', 4));

        $this->assertSame(CurrencyRevaluation::STATUS_POSTED, $revaluation->status);
        $this->assertSame($entry->id, $revaluation->journal_entry_id);
    }

    public function test_a_net_unrealised_loss_debits_the_mapped_unrealised_loss_account(): void
    {
        $lossAccount = $this->ledgerAccount('5920', 'Unrealised FX Loss', Account::TYPE_EXPENSE, Account::SUBTYPE_OTHER_EXPENSE);
        Setting::set('accounting', 'fx_unrealised_loss_account_id', $lossAccount->id, null, $this->organization->id);

        // 100,000 USD revalued from 3.7500 to 3.7400 is an unrealised loss of 1,000.
        app(MultiCurrencyService::class)->postRevaluation($this->revaluation('3.74000000'));

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(0, bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $lossAccount->id)->debit, '1000', 4));
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $this->receivable->id)->credit, '1000', 4));
    }

    public function test_an_account_named_like_a_forex_account_is_not_posted_to_without_the_mapping(): void
    {
        // What the name search used to find, and what the mapping now has to
        // name before anything is posted to it.
        $named = $this->ledgerAccount('4930', 'Forex Exchange Difference', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);
        $revaluation = $this->revaluation('3.78000000');

        $refusal = null;

        try {
            app(MultiCurrencyService::class)->postRevaluation($revaluation);
        } catch (InvalidArgumentException $e) {
            $refusal = $e;
        }

        $this->assertNotNull($refusal, 'Posting without a mapped unrealised gain account must be refused.');
        $this->assertStringContainsString('fx_unrealised_gain_account_id', $refusal->getMessage());

        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
        $this->assertSame(0, $named->journalLines()->count());
        $this->assertSame(CurrencyRevaluation::STATUS_DRAFT, $revaluation->fresh()->status);
    }

    public function test_the_realised_mapping_does_not_stand_in_for_the_unrealised_one(): void
    {
        $realisedGain = $this->ledgerAccount('4910', 'Realised FX Gain', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);
        Setting::set('accounting', 'fx_gain_account_id', $realisedGain->id, null, $this->organization->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/fx_unrealised_gain_account_id/');

        app(MultiCurrencyService::class)->postRevaluation($this->revaluation('3.78000000'));
    }

    public function test_the_account_the_caller_names_is_used_over_the_mapping(): void
    {
        $chosen = $this->ledgerAccount('4940', 'Period-end FX', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);

        app(MultiCurrencyService::class)->postRevaluation($this->revaluation('3.78000000'), $chosen->id);

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->sole();
        $this->assertSame(0, bccomp((string) $entry->lines->firstWhere('account_id', $chosen->id)->credit, '3000', 4));
    }

    public function test_a_revaluation_that_nets_to_nothing_needs_no_mapping_and_books_nothing(): void
    {
        $revaluation = app(MultiCurrencyService::class)->postRevaluation($this->revaluation('3.75000000'));

        $this->assertSame(CurrencyRevaluation::STATUS_POSTED, $revaluation->status);
        $this->assertNull($revaluation->journal_entry_id);
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
    }

    /** A draft revaluation of 100,000 USD held in one receivable account. */
    private function revaluation(string $newRate): CurrencyRevaluation
    {
        return app(MultiCurrencyService::class)->revalue([
            'organization_id' => $this->organization->id,
            'revaluation_date' => '2025-06-30',
            'currency_code' => 'USD',
            'base_currency' => 'SAR',
            'old_rate' => '3.75000000',
            'new_rate' => $newRate,
            'status' => CurrencyRevaluation::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ], [
            [
                'account_id' => $this->receivable->id,
                'account_type' => 'asset',
                'foreign_currency_balance' => '100000.0000',
            ],
        ]);
    }
}
