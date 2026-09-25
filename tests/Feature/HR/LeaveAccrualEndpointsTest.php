<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\HR\Leave\LeaveEncashment;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Accrual history and the adjustment and encashment listings, inside the
 * caller's organization only.
 */
class LeaveAccrualEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/leave-management';

    private Employee $employee;

    private LeaveType $type;

    private LeaveBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.leave.view', 'hr.leave.manage']);

        $this->employee = $this->employee($this->organization);
        $this->type = $this->leaveType($this->organization);
        $this->balance = $this->balance($this->employee, $this->type);
    }

    public function test_accrual_history_is_the_balance_and_its_accruals_latest_first(): void
    {
        $january = $this->accrual('2026-01-31');
        $february = $this->accrual('2026-02-28');

        $response = $this->apiGet(
            "{$this->baseUrl}/accruals/history?employee_id={$this->employee->id}&leave_type_id={$this->type->id}&year=2026"
        );

        $response->assertOk()->assertJsonPath('data.balance.id', $this->balance->id);
        $this->assertSame([$february->id, $january->id], array_column($response->json('data.accruals'), 'id'));
    }

    public function test_accrual_history_of_another_organizations_employee_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirEmployee = $this->employee($other);
        $theirType = $this->leaveType($other);
        $this->balance($theirEmployee, $theirType);

        $this->apiGet("{$this->baseUrl}/accruals/history?employee_id={$theirEmployee->id}&leave_type_id={$theirType->id}&year=2026")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'leave_type_id']);
    }

    public function test_adjustments_are_filtered_by_employee_and_paged_on_request(): void
    {
        $this->adjustment($this->employee);
        $this->adjustment($this->employee);
        $this->adjustment($this->employee($this->organization));

        $list = $this->apiGet("{$this->baseUrl}/adjustments?employee_id={$this->employee->id}");
        $list->assertOk()->assertJsonCount(2, 'data');

        $page = $this->apiGet("{$this->baseUrl}/adjustments?employee_id={$this->employee->id}&per_page=1");
        $this->assertPaginatedResponse($page);
        $this->assertCount(1, $page->json('data'));
    }

    public function test_encashments_are_filtered_by_status(): void
    {
        $pending = $this->encashment(LeaveEncashment::STATUS_PENDING);
        $this->encashment(LeaveEncashment::STATUS_APPROVED);

        $response = $this->apiGet("{$this->baseUrl}/encashments?status=pending");

        $response->assertOk();
        $this->assertSame([$pending->id], array_column($response->json('data'), 'id'));
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function leaveType(Organization $organization): LeaveType
    {
        return LeaveType::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
    }

    private function balance(Employee $employee, LeaveType $type): LeaveBalance
    {
        return LeaveBalance::factory()->create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => 2026,
        ]);
    }

    private function accrual(string $date): LeaveAccrual
    {
        return LeaveAccrual::forceCreate([
            'leave_balance_id' => $this->balance->id,
            'employee_id' => $this->employee->id,
            'accrual_date' => $date,
            'accrual_type' => 'monthly',
            'days' => 1.75,
        ]);
    }

    private function adjustment(Employee $employee): LeaveAdjustment
    {
        return LeaveAdjustment::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $this->type->id,
            'leave_balance_id' => $this->balance->id,
            'adjustment_type' => 'add',
            'days' => 1,
            'balance_before' => 0,
            'balance_after' => 1,
            'reason' => 'Correction',
            'effective_date' => '2026-03-01',
            'created_by' => $this->user->id,
        ]);
    }

    private function encashment(string $status): LeaveEncashment
    {
        return LeaveEncashment::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'leave_balance_id' => $this->balance->id,
            'requested_days' => 2,
            'daily_rate' => 100,
            'encashment_rate' => 100,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }
}
