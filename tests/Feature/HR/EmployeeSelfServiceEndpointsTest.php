<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Models\HR\EmployeeLoan;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\LoanRepayment;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use App\Services\HR\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Self-service endpoints answer with the caller's own records only.
 *
 * Every record here belongs to the employee linked to the authenticated user.
 * A colleague in the same organization and an employee of another
 * organization each hold the same kind of record, and none of theirs may be
 * listed, shown, downloaded or cancelled.
 */
class EmployeeSelfServiceEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Employee $me;

    private Employee $colleague;

    private Organization $otherOrganization;

    private Employee $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['core.test.act']);

        $this->me = $this->employee($this->organization, ['user_id' => $this->user->id, 'first_name' => 'Mine']);
        $this->colleague = $this->employee($this->organization, ['first_name' => 'Colleague']);
        $this->otherOrganization = Organization::factory()->create();
        $this->stranger = $this->employee($this->otherOrganization, ['first_name' => 'Stranger']);
    }

    public function test_attendance_lists_own_records_of_the_month(): void
    {
        $mine = $this->attendance($this->me, '2026-03-10');
        $this->attendance($this->me, '2026-04-01');
        $this->attendance($this->colleague, '2026-03-11');

        $response = $this->apiGet('/hr/me/attendance?month=2026-03')->assertOk();

        $response->assertJsonPath('data.month', '2026-03');
        $this->assertSame([$mine->id], array_column($response->json('data.records'), 'id'));
        $this->assertIsArray($response->json('data.summary'));
    }

    public function test_leave_balances_count_only_own_pending_requests(): void
    {
        $type = $this->leaveType($this->organization);
        LeaveBalance::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->me->id,
            'leave_type_id' => $type->id,
            'year' => 2026,
            'opening_balance' => 21,
        ]);
        LeaveBalance::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->colleague->id,
            'leave_type_id' => $type->id,
            'year' => 2026,
            'opening_balance' => 30,
        ]);
        $this->leaveRequest($this->me, $type, '2026-05-10', LeaveRequest::STATUS_PENDING, 2);
        $this->leaveRequest($this->colleague, $type, '2026-05-10', LeaveRequest::STATUS_PENDING, 5);

        $this->apiGet('/hr/me/leave-balances?year=2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.leave_type_code', $type->code)
            ->assertJsonPath('data.0.carried_forward', 21)
            ->assertJsonPath('data.0.pending', 2);
    }

    public function test_leave_requests_list_own_requests_filtered_by_status(): void
    {
        $type = $this->leaveType($this->organization);
        $pending = $this->leaveRequest($this->me, $type, '2026-05-10', LeaveRequest::STATUS_PENDING);
        $this->leaveRequest($this->me, $type, '2026-06-10', LeaveRequest::STATUS_APPROVED);
        $this->leaveRequest($this->colleague, $type, '2026-05-11', LeaveRequest::STATUS_PENDING);

        $response = $this->apiGet('/hr/me/leave-requests?status=pending');

        $this->assertPaginatedResponse($response);
        $this->assertSame([$pending->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.leave_type.id', $type->id);
    }

    public function test_a_leave_request_cannot_name_another_organizations_leave_type(): void
    {
        $theirs = $this->leaveType($this->otherOrganization);

        $this->apiPost('/hr/me/leave-requests', [
            'leave_type_id' => $theirs->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Family',
        ])->assertStatus(422)->assertJsonValidationErrors('leave_type_id');

        $this->assertSame(0, LeaveRequest::withoutGlobalScopes()->count());
    }

    /**
     * Too few days left is the employee's mistake, so it is answered as one,
     * not reported as a server failure.
     */
    public function test_a_leave_request_beyond_the_balance_is_refused_as_invalid(): void
    {
        $type = $this->leaveType($this->organization);

        $this->apiPost('/hr/me/leave-requests', [
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Family',
        ])->assertStatus(422);

        $this->assertSame(0, LeaveRequest::withoutGlobalScopes()->count());
    }

    public function test_own_pending_request_is_cancelled(): void
    {
        $request = $this->leaveRequest($this->me, $this->leaveType($this->organization), '2026-12-10', LeaveRequest::STATUS_PENDING);

        $this->apiPost("/hr/me/leave-requests/{$request->id}/cancel", ['reason' => 'Plans changed'])
            ->assertOk();

        $request->refresh();
        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $request->status);
        $this->assertSame('Plans changed', $request->cancellation_reason);
    }

    public function test_a_decided_request_is_not_cancelled_by_the_employee(): void
    {
        $request = $this->leaveRequest($this->me, $this->leaveType($this->organization), '2026-12-10', LeaveRequest::STATUS_APPROVED);

        $this->apiPost("/hr/me/leave-requests/{$request->id}/cancel")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_another_employees_request_cannot_be_cancelled(): void
    {
        $colleagues = $this->leaveRequest($this->colleague, $this->leaveType($this->organization), '2026-12-10', LeaveRequest::STATUS_PENDING);
        $strangers = LeaveRequest::withoutGlobalScopes()->find(
            $this->leaveRequest($this->stranger, $this->leaveType($this->otherOrganization), '2026-12-10', LeaveRequest::STATUS_PENDING)->id
        );

        foreach ([$colleagues, $strangers] as $theirs) {
            $this->apiPost("/hr/me/leave-requests/{$theirs->id}/cancel")->assertNotFound();

            $this->assertSame(LeaveRequest::STATUS_PENDING, LeaveRequest::withoutGlobalScopes()->find($theirs->id)->status);
        }
    }

    /**
     * The request held by the caller can be stale: HR may have approved it
     * since it was read. The withdrawal checks the row as it is now.
     */
    public function test_withdrawal_checks_the_current_row_not_a_stale_copy(): void
    {
        $stale = $this->leaveRequest($this->me, $this->leaveType($this->organization), '2026-12-10', LeaveRequest::STATUS_PENDING);
        LeaveRequest::whereKey($stale->id)->toBase()->update(['status' => LeaveRequest::STATUS_APPROVED]);

        try {
            app(LeaveService::class)->withdraw($stale, 'Too late');
            $this->fail('An approved request was withdrawn.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(LeaveRequest::STATUS_APPROVED, $stale->fresh()->status);
        }
    }

    public function test_payslips_list_own_payslips_only(): void
    {
        $period = $this->period($this->organization);
        $mine = $this->payslip($this->me, $period);
        $this->payslip($this->colleague, $period);

        $response = $this->apiGet('/hr/me/payslips');

        $this->assertPaginatedResponse($response);
        $this->assertSame([$mine->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.payroll_period.id', $period->id);
    }

    public function test_own_payslip_is_shown_with_its_items_and_employee(): void
    {
        $mine = $this->payslip($this->me, $this->period($this->organization));

        $response = $this->apiGet("/hr/me/payslips/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id)
            ->assertJsonPath('data.employee.id', $this->me->id)
            ->assertJsonPath('data.items', []);

        $this->assertArrayNotHasKey('national_id', $response->json('data.employee'));
    }

    public function test_another_employees_payslip_is_neither_shown_nor_downloaded(): void
    {
        $colleagues = $this->payslip($this->colleague, $this->period($this->organization));
        $strangers = $this->payslip($this->stranger, $this->period($this->otherOrganization));

        foreach ([$colleagues, $strangers] as $theirs) {
            $this->apiGet("/hr/me/payslips/{$theirs->id}")->assertNotFound();
            $this->apiGet("/hr/me/payslips/{$theirs->id}/download")->assertNotFound();
        }
    }

    /**
     * The loan table has no total or outstanding column: the total is what the
     * instalments add up to and the outstanding amount is the stored balance.
     */
    public function test_loans_list_own_loans_with_their_repayments(): void
    {
        $mine = $this->loan($this->me, 'LN-1');
        LoanRepayment::create([
            'employee_loan_id' => $mine->id,
            'installment_number' => 1,
            'due_date' => '2026-02-01',
            'principal_amount' => 1000,
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'paid_date' => '2026-02-01',
            'status' => LoanRepayment::STATUS_PAID,
        ]);
        $this->loan($this->colleague, 'LN-2');

        $this->apiGet('/hr/me/loans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.total_amount', 12000)
            ->assertJsonPath('data.0.total_paid', 1000)
            ->assertJsonPath('data.0.outstanding', '11000.0000')
            ->assertJsonCount(1, 'data.0.repayments');
    }

    public function test_documents_list_own_documents_by_type(): void
    {
        $visa = $this->document($this->me, 'visa');
        $iqama = $this->document($this->me, 'iqama');
        $this->document($this->colleague, 'aaa');

        $response = $this->apiGet('/hr/me/documents')->assertOk();

        $this->assertSame([$iqama->id, $visa->id], array_column($response->json('data'), 'id'));
    }

    public function test_directory_lists_active_colleagues_without_personal_numbers(): void
    {
        $this->employee($this->organization, ['first_name' => 'Gone', 'employment_status' => Employee::STATUS_TERMINATED]);

        $response = $this->apiGet('/hr/directory');

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Colleague', 'Mine'], array_column($response->json('data'), 'first_name'));

        foreach (['national_id', 'bank_iban', 'passport_number', 'date_of_birth'] as $field) {
            $this->assertArrayNotHasKey($field, $response->json('data.0'));
        }

        $this->apiGet('/hr/directory?search=Colleague')->assertJsonCount(1, 'data');
    }

    public function test_holidays_are_the_organizations_of_the_year(): void
    {
        $ours = Holiday::create(['organization_id' => $this->organization->id, 'name' => 'Founding Day', 'holiday_date' => '2026-02-22']);
        Holiday::create(['organization_id' => $this->organization->id, 'name' => 'Last year', 'holiday_date' => '2025-02-22']);
        Holiday::create(['organization_id' => $this->otherOrganization->id, 'name' => 'Theirs', 'holiday_date' => '2026-03-01']);

        $response = $this->apiGet('/hr/holidays?year=2026')->assertOk();

        $response->assertJsonPath('data.year', '2026');
        $this->assertSame([$ours->id], array_column($response->json('data.holidays'), 'id'));
    }

    private function employee(Organization $organization, array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ], $overrides));
    }

    private function attendance(Employee $employee, string $date): Attendance
    {
        return Attendance::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => 'present',
        ]);
    }

    private function leaveType(Organization $organization): LeaveType
    {
        return LeaveType::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
    }

    private function leaveRequest(Employee $employee, LeaveType $type, string $fromDate, string $status, int $days = 1): LeaveRequest
    {
        return LeaveRequest::forceCreate([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'from_date' => $fromDate,
            'to_date' => $fromDate,
            'total_days' => $days,
            'reason' => 'Personal',
            'status' => $status,
        ]);
    }

    private function period(Organization $organization): PayrollPeriod
    {
        return PayrollPeriod::factory()->create([
            'organization_id' => $organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);
    }

    private function payslip(Employee $employee, PayrollPeriod $period): Payslip
    {
        return Payslip::factory()->create([
            'organization_id' => $employee->organization_id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);
    }

    private function loan(Employee $employee, string $number): EmployeeLoan
    {
        return EmployeeLoan::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'loan_number' => $number,
            'principal_amount' => 12000,
            'disbursement_date' => '2026-01-01',
            'repayment_start_date' => '2026-02-01',
            'tenure_months' => 12,
            'emi_amount' => 1000,
            'total_repaid' => 1000,
            'balance' => 11000,
            'status' => EmployeeLoan::STATUS_ACTIVE,
        ]);
    }

    private function document(Employee $employee, string $type): EmployeeDocument
    {
        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type' => $type,
            'document_name' => ucfirst($type),
            'document_number' => 'DOC-'.$type,
        ]);
    }
}
