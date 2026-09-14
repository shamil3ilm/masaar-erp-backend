<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeLoan;
use App\Models\HR\LoanRepayment;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use App\Services\HR\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Paying a payslip runs once, on the locked payslip: one salary journal and
 * one loan repayment per payslip, however often the payment is submitted.
 */
class PayslipPaymentTransitionTest extends TestCase
{
    use AssertsRejection, BuildsLedger, RefreshDatabase, TestHelpers;

    private PayrollService $service;
    private Employee $employee;
    private PayrollPeriod $period;
    private EmployeeLoan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        Config::set('erp.default_accounts.salary_expense',
            $this->ledgerAccount('6000', 'Salaries', Account::TYPE_EXPENSE, Account::SUBTYPE_OPERATING_EXPENSE)->id);
        Config::set('erp.default_accounts.salary_payable',
            $this->ledgerAccount('2100', 'Salaries Payable', Account::TYPE_LIABILITY, Account::SUBTYPE_OTHER_LIABILITY)->id);

        $this->employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $this->loan = EmployeeLoan::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'loan_number' => 'EL-00001',
            'loan_type' => 'loan',
            'principal_amount' => 1000,
            'disbursement_date' => now()->subMonth()->toDateString(),
            'repayment_start_date' => now()->startOfMonth()->toDateString(),
            'tenure_months' => 10,
            'emi_amount' => 100,
            'total_repaid' => 0,
            'balance' => 1000,
            'status' => EmployeeLoan::STATUS_ACTIVE,
            'currency_code' => 'SAR',
        ]);

        foreach ([1, 2] as $installment) {
            LoanRepayment::forceCreate([
                'employee_loan_id' => $this->loan->id,
                'installment_number' => $installment,
                'due_date' => now()->startOfMonth()->toDateString(),
                'principal_amount' => 100,
                'interest_amount' => 0,
                'total_amount' => 100,
                'status' => LoanRepayment::STATUS_PENDING,
            ]);
        }

        $this->service = app(PayrollService::class);
    }

    public function test_a_second_payment_from_a_stale_payslip_is_rejected(): void
    {
        $payslip = $this->approvedPayslip();
        $stale = Payslip::findOrFail($payslip->id);

        $this->service->markAsPaid($payslip, 'bank_transfer');

        $this->assertRejected(fn () => $this->service->markAsPaid($stale, 'bank_transfer'));
        $this->assertSame(1, JournalEntry::where('source_type', Payslip::class)->count());
        $this->assertEquals(900, (float) $this->loan->fresh()->balance);
        $this->assertSame(1, LoanRepayment::where('status', LoanRepayment::STATUS_PAID)->count());
    }

    private function approvedPayslip(): Payslip
    {
        return Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'payment_date' => now()->toDateString(),
            'gross_earnings' => 5000,
            'total_deductions' => 0,
            'net_salary' => 5000,
            'currency_code' => 'SAR',
            'status' => Payslip::STATUS_APPROVED,
        ]);
    }
}
