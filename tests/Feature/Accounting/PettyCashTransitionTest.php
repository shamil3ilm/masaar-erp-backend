<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Finance\PettyCashFund;
use App\Models\Finance\PettyCashReplenishment;
use App\Models\Finance\PettyCashVoucher;
use App\Services\Accounting\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Posting a voucher and disbursing a replenishment change the fund balance
 * once, from the balance on the locked fund, and a voucher is booked in the
 * ledger with its posting or not posted at all.
 */
class PettyCashTransitionTest extends TestCase
{
    use AssertsRejection, BuildsLedger, RefreshDatabase, TestHelpers;

    private PettyCashService $service;
    private PettyCashFund $fund;
    private Account $cash;
    private Account $supplies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->cash = $this->ledgerAccount('1050', 'Petty Cash', Account::TYPE_ASSET, Account::SUBTYPE_CASH);
        $this->supplies = $this->ledgerAccount('6100', 'Office Supplies', Account::TYPE_EXPENSE, Account::SUBTYPE_OPERATING_EXPENSE);

        $this->fund = PettyCashFund::create([
            'organization_id' => $this->organization->id,
            'name' => 'Front desk',
            'custodian_id' => $this->user->id,
            'account_id' => $this->cash->id,
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'currency_code' => 'SAR',
            'is_active' => true,
        ]);

        $this->service = app(PettyCashService::class);
    }

    public function test_a_second_post_from_a_stale_voucher_is_rejected(): void
    {
        $voucher = $this->approvedVoucher(100);
        $stale = PettyCashVoucher::findOrFail($voucher->id);

        $this->service->postVoucher($voucher);

        $this->assertRejected(fn () => $this->service->postVoucher($stale));
        $this->assertEquals(900, (float) $this->fund->fresh()->current_balance);
    }

    public function test_a_payment_is_booked_from_the_fund_account_to_the_voucher_account(): void
    {
        $this->service->postVoucher($this->approvedVoucher(100));

        $lines = JournalEntry::with('lines')->sole()->lines;

        $this->assertEquals(100, (float) $lines->firstWhere('account_id', $this->supplies->id)->debit);
        $this->assertEquals(100, (float) $lines->firstWhere('account_id', $this->cash->id)->credit);
    }

    public function test_posting_rolls_back_when_the_voucher_cannot_be_booked(): void
    {
        $voucher = $this->approvedVoucher(100);
        $this->closeFiscalYear();

        $this->assertRejected(fn () => $this->service->postVoucher($voucher));

        $this->assertEquals(1000, (float) $this->fund->fresh()->current_balance);
        $this->assertSame(PettyCashVoucher::STATUS_APPROVED, $voucher->fresh()->status);
    }

    public function test_a_second_disbursement_from_a_stale_replenishment_is_rejected(): void
    {
        $replenishment = $this->approvedReplenishment(200);
        $stale = PettyCashReplenishment::findOrFail($replenishment->id);

        $this->service->disburseReplenishment($replenishment);

        $this->assertRejected(fn () => $this->service->disburseReplenishment($stale));
        $this->assertEquals(1200, (float) $this->fund->fresh()->current_balance);
    }

    public function test_a_disbursement_adds_to_the_balance_on_the_locked_fund(): void
    {
        $replenishment = $this->approvedReplenishment(200)->load('fund');

        $this->service->postVoucher($this->approvedVoucher(100));
        $this->service->disburseReplenishment($replenishment);

        $this->assertEquals(1100, (float) $this->fund->fresh()->current_balance);
    }

    private function approvedVoucher(float $amount): PettyCashVoucher
    {
        return PettyCashVoucher::create([
            'fund_id' => $this->fund->id,
            'voucher_number' => 'PCV-'.fake()->unique()->numerify('#####'),
            'voucher_date' => now()->toDateString(),
            'transaction_type' => PettyCashVoucher::TYPE_PAYMENT,
            'amount' => $amount,
            'description' => 'Stationery',
            'account_id' => $this->supplies->id,
            'status' => PettyCashVoucher::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);
    }

    private function approvedReplenishment(float $amount): PettyCashReplenishment
    {
        return PettyCashReplenishment::create([
            'fund_id' => $this->fund->id,
            'replenishment_date' => now()->toDateString(),
            'amount' => $amount,
            'requested_by' => $this->user->id,
            'status' => PettyCashReplenishment::STATUS_APPROVED,
        ]);
    }
}
