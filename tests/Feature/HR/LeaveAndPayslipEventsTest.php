<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Events\HR\LeaveRequestApproved;
use App\Events\HR\LeaveRequestSubmitted;
use App\Events\HR\PayslipGenerated;
use App\Models\Core\Notification;
use App\Models\Core\Role;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use App\Models\User;
use App\Notifications\HR\LeaveRequestApprovedNotification;
use App\Notifications\HR\LeaveRequestSubmittedNotification;
use App\Services\HR\LeaveService;
use App\Services\HR\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LeaveAndPayslipEventsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    public function test_submitting_a_leave_request_dispatches_leave_request_submitted(): void
    {
        Event::fake([LeaveRequestSubmitted::class]);

        $request = $this->leaveRequest($this->employee(), LeaveRequest::STATUS_DRAFT);

        app(LeaveService::class)->submit($request);

        Event::assertDispatched(LeaveRequestSubmitted::class, fn (LeaveRequestSubmitted $event) => $event->leaveRequest->id === $request->id
            && $event->leaveRequest->status === LeaveRequest::STATUS_PENDING);
    }

    public function test_approving_a_leave_request_dispatches_leave_request_approved(): void
    {
        Event::fake([LeaveRequestApproved::class]);

        $request = $this->leaveRequest($this->employee(), LeaveRequest::STATUS_PENDING);

        app(LeaveService::class)->approve($request, $this->user->id);

        Event::assertDispatched(LeaveRequestApproved::class, fn (LeaveRequestApproved $event) => $event->leaveRequest->id === $request->id
            && $event->approvedBy === $this->user->id);
    }

    public function test_submission_notifies_the_department_manager_and_the_reporting_managers_user(): void
    {
        $departmentManager = $this->makeUser();
        $reportingUser = $this->makeUser();

        $employee = $this->employee(
            departmentManager: $departmentManager,
            reportingManager: $this->employee(user: $reportingUser),
        );

        app(LeaveService::class)->submit($this->leaveRequest($employee, LeaveRequest::STATUS_DRAFT));

        $this->assertSame(1, $this->submittedNotifications($departmentManager));
        $this->assertSame(1, $this->submittedNotifications($reportingUser));
    }

    public function test_submission_notifies_a_manager_holding_both_roles_once(): void
    {
        $manager = $this->makeUser();

        $employee = $this->employee(
            departmentManager: $manager,
            reportingManager: $this->employee(user: $manager),
        );

        app(LeaveService::class)->submit($this->leaveRequest($employee, LeaveRequest::STATUS_DRAFT));

        $this->assertSame(1, $this->submittedNotifications($manager));
    }

    public function test_submission_falls_back_to_hr_managers_without_a_manager(): void
    {
        $hrManager = $this->makeUser();
        $role = Role::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'hr-manager',
        ]);
        $hrManager->roles()->attach($role->id);

        app(LeaveService::class)->submit($this->leaveRequest($this->employee(), LeaveRequest::STATUS_DRAFT));

        $this->assertSame(1, $this->submittedNotifications($hrManager));
    }

    public function test_approval_notifies_the_employee(): void
    {
        $employeeUser = $this->makeUser();
        $request = $this->leaveRequest($this->employee(user: $employeeUser), LeaveRequest::STATUS_PENDING);

        app(LeaveService::class)->approve($request, $this->user->id);

        $this->assertSame(1, Notification::where('user_id', $employeeUser->id)
            ->where('type', LeaveRequestApprovedNotification::class)
            ->count());
    }

    public function test_approving_a_payslip_dispatches_payslip_generated(): void
    {
        Event::fake([PayslipGenerated::class]);

        $payslip = $this->pendingPayslip($this->employee(user: $this->makeUser()));

        app(PayrollService::class)->approvePayslip($payslip, $this->user->id);

        Event::assertDispatched(PayslipGenerated::class, fn (PayslipGenerated $event) => $event->payslip->id === $payslip->id
            && $event->payslip->status === Payslip::STATUS_APPROVED);
    }

    public function test_submitting_a_payslip_dispatches_nothing(): void
    {
        Event::fake([PayslipGenerated::class]);

        $payslip = $this->pendingPayslip($this->employee(user: $this->makeUser()));
        $payslip->update(['status' => Payslip::STATUS_DRAFT]);

        app(PayrollService::class)->submitPayslip($payslip, $this->user->id);

        Event::assertNotDispatched(PayslipGenerated::class);
    }

    public function test_payslip_approval_tells_the_employee_which_period_it_covers(): void
    {
        $employeeUser = $this->makeUser();
        $payslip = $this->pendingPayslip($this->employee(user: $employeeUser));

        app(PayrollService::class)->approvePayslip($payslip, $this->user->id);

        $notification = Notification::where('user_id', $employeeUser->id)
            ->where('type', 'payslip_generated')
            ->firstOrFail();

        $this->assertSame('Your payslip for March 2026 is now available', $notification->title);
        $this->assertSame('March 2026', $notification->data['period_label']);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function employee(?User $user = null, ?User $departmentManager = null, ?Employee $reportingManager = null): Employee
    {
        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
            'manager_id' => $departmentManager?->id,
        ]);

        return Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'user_id' => $user?->id,
            'reporting_manager_id' => $reportingManager?->id,
        ]);
    }

    private function leaveRequest(Employee $employee, string $status): LeaveRequest
    {
        return LeaveRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => $status,
        ]);
    }

    private function pendingPayslip(Employee $employee): Payslip
    {
        return Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => PayrollPeriod::factory()->create([
                'organization_id' => $this->organization->id,
                'name' => 'March 2026',
            ])->id,
            'employee_id' => $employee->id,
            'employee_salary_id' => EmployeeSalary::factory()->create(['employee_id' => $employee->id])->id,
            'status' => Payslip::STATUS_PENDING,
        ]);
    }

    private function submittedNotifications(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('type', LeaveRequestSubmittedNotification::class)
            ->count();
    }
}
