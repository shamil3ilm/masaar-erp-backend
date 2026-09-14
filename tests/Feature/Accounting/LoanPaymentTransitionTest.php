<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Loan;
use App\Models\Accounting\LoanPayment;
use App\Models\Accounting\LoanSchedule;
use App\Services\Accounting\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * A loan payment is recorded on the locked loan together with its journal
 * entry: when the entry cannot be posted, the payment is not recorded.
 */
class LoanPaymentTransitionTest extends TestCase
{
    use AssertsRejection, BuildsLedger, RefreshDatabase, TestHelpers;

    private LoanService $service;
    private Account $loanAccount;
    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->loanAccount = $this->ledgerAccount('2300', 'Bank Loan', Account::TYPE_LIABILITY, Account::SUBTYPE_OTHER_LIABILITY);
        $this->bank = $this->ledgerAccount('1010', 'Main Bank', Account::TYPE_ASSET, Account::SUBTYPE_BANK);

        $this->service = app(LoanService::class);
    }

    public function test_a_payment_is_booked_in_the_ledger(): void
    {
        $payment = $this->service->recordPayment($this->activeLoan(1000), $this->paymentData(100), $this->user->id);

        $lines = JournalEntry::where('source_type', LoanPayment::class)
            ->where('source_id', $payment->id)
            ->with('lines')
            ->sole()
            ->lines;

        $this->assertEquals(100, (float) $lines->firstWhere('account_id', $this->loanAccount->id)->debit);
        $this->assertEquals(100, (float) $lines->firstWhere('account_id', $this->bank->id)->credit);
    }

    public function test_a_payment_rolls_back_when_its_journal_entry_cannot_be_posted(): void
    {
        $loan = $this->activeLoan(1000);
        $this->closeFiscalYear();

        $this->assertRejected(fn () => $this->service->recordPayment($loan, $this->paymentData(100), $this->user->id));

        $this->assertSame(0, LoanPayment::count());
        $this->assertEquals(1000, (float) $loan->fresh()->outstanding_amount);
    }

    public function test_a_payment_on_a_stale_loan_that_has_been_repaid_is_rejected(): void
    {
        $loan = $this->activeLoan(100);
        $stale = Loan::findOrFail($loan->id);

        $this->service->recordPayment($loan, $this->paymentData(100), $this->user->id);

        $this->assertRejected(fn () => $this->service->recordPayment($stale, $this->paymentData(50), $this->user->id));
        $this->assertSame(1, LoanPayment::count());
    }

    public function test_a_payment_cannot_be_applied_to_another_loans_schedule(): void
    {
        $loan = $this->activeLoan(1000);
        $schedule = LoanSchedule::factory()->create([
            'loan_id' => $this->activeLoan(1000)->id,
            'installment_number' => 1,
            'due_date' => now()->toDateString(),
            'principal_amount' => 100,
            'interest_amount' => 0,
            'total_amount' => 100,
            'outstanding_balance' => 900,
            'paid_amount' => 0,
            'status' => LoanSchedule::STATUS_PENDING,
        ]);

        $this->assertRejected(fn () => $this->service->recordPayment(
            $loan,
            $this->paymentData(100) + ['schedule_id' => $schedule->id],
            $this->user->id,
        ));

        $this->assertEquals(0, (float) $schedule->fresh()->paid_amount);
        $this->assertSame(0, LoanPayment::count());
    }

    private function activeLoan(float $outstanding): Loan
    {
        return Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => $outstanding,
            'total_interest' => 0,
            'total_amount' => $outstanding,
            'outstanding_amount' => $outstanding,
            'currency_code' => 'SAR',
            'status' => Loan::STATUS_ACTIVE,
            'loan_account_id' => $this->loanAccount->id,
            'bank_account_id' => null,
            'created_by' => $this->user->id,
        ]);
    }

    private function paymentData(float $principal): array
    {
        return [
            'payment_date' => now()->toDateString(),
            'principal_paid' => $principal,
            'interest_paid' => 0,
            'payment_method' => 'bank_transfer',
        ];
    }
}
