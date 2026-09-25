<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Accounting\CostCenter;
use App\Models\Core\Organization;
use App\Models\HR\CompensationReview;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\LeaveType;
use App\Models\HR\OrgUnit;
use App\Models\HR\PayGrade;
use App\Models\HR\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * HR endpoints refuse an id that belongs to another organization.
 *
 * Each case sends the same request twice: once with a row of a second
 * organization, which the field must reject, and once with the caller's own
 * row, which the field must accept. The second call only asserts that the
 * field itself passed validation, so a case stays about the rule rather than
 * about whatever the endpoint does afterwards.
 */
class OwnedReferenceValidationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.compensation.manage',
            'hr.lifecycle.view',
            'hr.lifecycle.manage',
            'hr.leave.view',
            'hr.leave.manage',
            'hr.org.view',
            'hr.org.manage',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_another_organizations_employee_is_not_added_to_a_compensation_review(): void
    {
        $review = CompensationReview::create([
            'organization_id' => $this->organization->id,
            'review_name' => 'Annual 2026',
            'review_date' => '2026-01-01',
            'effective_date' => '2026-02-01',
        ]);

        $url = "/hr/compensation/{$review->id}/items";

        $this->apiPost($url, ['employee_id' => $this->employee($this->otherOrg)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->apiPost($url, ['employee_id' => $this->employee()->id])
            ->assertJsonMissingValidationErrors('employee_id');
    }

    public function test_onboarding_does_not_start_for_another_organizations_employee_or_assignee(): void
    {
        $this->apiPost('/hr/onboarding', $this->onboardingPayload(
            $this->employee($this->otherOrg)->id,
            $this->userOf($this->otherOrg)->id,
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'tasks.0.assigned_to']);

        $this->apiPost('/hr/onboarding', $this->onboardingPayload(
            $this->employee()->id,
            $this->user->id,
        ))
            ->assertJsonMissingValidationErrors(['employee_id', 'tasks.0.assigned_to']);
    }

    public function test_an_onboarding_task_is_not_assigned_to_another_organizations_user(): void
    {
        $onboarding = EmployeeOnboarding::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee()->id,
            'started_date' => '2026-01-01',
        ]);

        $url = "/hr/onboarding/{$onboarding->id}/tasks";

        $this->apiPost($url, ['title' => 'Issue laptop', 'assigned_to' => $this->userOf($this->otherOrg)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->apiPost($url, ['title' => 'Issue laptop', 'assigned_to' => $this->user->id])
            ->assertJsonMissingValidationErrors('assigned_to');
    }

    public function test_a_leave_balance_of_another_organization_is_not_read_adjusted_or_encashed(): void
    {
        $theirs = ['employee' => $this->employee($this->otherOrg), 'type' => $this->leaveType($this->otherOrg)];
        $ours = ['employee' => $this->employee(), 'type' => $this->leaveType()];

        $query = fn (array $rows) => "employee_id={$rows['employee']->id}&leave_type_id={$rows['type']->id}";

        $this->apiGet("/hr/leave-management/accruals/balance?{$query($theirs)}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'leave_type_id']);

        $this->apiGet("/hr/leave-management/accruals/balance?{$query($ours)}")
            ->assertJsonMissingValidationErrors(['employee_id', 'leave_type_id']);

        $this->apiPost('/hr/leave-management/adjustments', $this->adjustmentPayload($theirs))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'leave_type_id']);

        $this->apiPost('/hr/leave-management/adjustments', $this->adjustmentPayload($ours))
            ->assertJsonMissingValidationErrors(['employee_id', 'leave_type_id']);

        $this->apiPost('/hr/leave-management/encashments', $this->encashmentPayload($theirs))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'leave_type_id']);

        $this->apiPost('/hr/leave-management/encashments', $this->encashmentPayload($ours))
            ->assertJsonMissingValidationErrors(['employee_id', 'leave_type_id']);
    }

    public function test_an_org_unit_does_not_take_another_organizations_cost_center(): void
    {
        $this->apiPost('/hr/org-units', $this->orgUnitPayload($this->costCenter($this->otherOrg)->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cost_center_id');

        $this->apiPost('/hr/org-units', $this->orgUnitPayload($this->costCenter()->id))
            ->assertJsonMissingValidationErrors('cost_center_id');

        $unit = OrgUnit::forceCreate([
            'organization_id' => $this->organization->id,
            'org_unit_code' => 'OU-EXIST',
            'name' => 'Operations',
            'unit_type' => 'department',
            'org_unit_type' => OrgUnit::TYPE_DEPARTMENT,
            'valid_from' => '2026-01-01',
        ]);

        $this->apiPut("/hr/org-units/{$unit->id}", ['cost_center_id' => $this->costCenter($this->otherOrg)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cost_center_id');

        $this->apiPut("/hr/org-units/{$unit->id}", ['cost_center_id' => $this->costCenter()->id])
            ->assertJsonMissingValidationErrors('cost_center_id');
    }

    public function test_a_position_does_not_take_another_organizations_org_data(): void
    {
        $theirs = $this->positionReferences($this->otherOrg);
        $fields = array_keys($theirs);

        $this->apiPost('/hr/positions', $this->positionPayload('POS-A', $theirs))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($fields);

        $this->apiPost('/hr/positions', $this->positionPayload('POS-B', $this->positionReferences()))
            ->assertJsonMissingValidationErrors($fields);

        $position = $this->position();

        $this->apiPut("/hr/positions/{$position->id}", $theirs)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($fields);

        $this->apiPut("/hr/positions/{$position->id}", $this->positionReferences())
            ->assertJsonMissingValidationErrors($fields);
    }

    public function test_another_organizations_employee_does_not_fill_or_vacate_a_position(): void
    {
        $position = $this->position();

        foreach (['assign', 'vacate'] as $action) {
            $this->apiPost("/hr/positions/{$position->id}/{$action}", [
                'employee_id' => $this->employee($this->otherOrg)->id,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('employee_id');

            $this->apiPost("/hr/positions/{$position->id}/{$action}", [
                'employee_id' => $this->employee()->id,
            ])
                ->assertJsonMissingValidationErrors('employee_id');
        }
    }

    /** @return array<string, mixed> */
    private function onboardingPayload(int $employeeId, int $assigneeId): array
    {
        return [
            'employee_id' => $employeeId,
            'started_date' => '2026-01-01',
            'tasks' => [['title' => 'Sign contract', 'assigned_to' => $assigneeId]],
        ];
    }

    /**
     * @param  array{employee: Employee, type: LeaveType}  $rows
     * @return array<string, mixed>
     */
    private function adjustmentPayload(array $rows): array
    {
        return [
            'employee_id' => $rows['employee']->id,
            'leave_type_id' => $rows['type']->id,
            'adjustment_type' => 'add',
            'days' => 1,
            'reason' => 'Carry over from the previous year.',
        ];
    }

    /**
     * @param  array{employee: Employee, type: LeaveType}  $rows
     * @return array<string, mixed>
     */
    private function encashmentPayload(array $rows): array
    {
        return [
            'employee_id' => $rows['employee']->id,
            'leave_type_id' => $rows['type']->id,
            'requested_days' => 2,
            'daily_rate' => 300,
        ];
    }

    /** @return array<string, mixed> */
    private function orgUnitPayload(int $costCenterId): array
    {
        return [
            'org_unit_code' => 'OU-'.fake()->unique()->numerify('####'),
            'name' => 'Field service',
            'org_unit_type' => OrgUnit::TYPE_DEPARTMENT,
            'cost_center_id' => $costCenterId,
            'valid_from' => '2026-01-01',
        ];
    }

    /**
     * @param  array<string, int>  $references
     * @return array<string, mixed>
     */
    private function positionPayload(string $code, array $references): array
    {
        return array_merge(['position_code' => $code, 'position_title' => 'Technician'], $references);
    }

    /** @return array<string, int> */
    private function positionReferences(?Organization $organization = null): array
    {
        return [
            'department_id' => $this->department($organization)->id,
            'designation_id' => $this->designation($organization)->id,
            'pay_grade_id' => $this->payGrade($organization)->id,
            'reports_to_position_id' => $this->position($organization)->id,
        ];
    }

    private function employee(?Organization $organization = null): Employee
    {
        $organization ??= $this->organization;

        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function leaveType(?Organization $organization = null): LeaveType
    {
        return LeaveType::factory()->create([
            'organization_id' => $this->idOf($organization),
            'is_active' => true,
        ]);
    }

    private function costCenter(?Organization $organization = null): CostCenter
    {
        return CostCenter::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function department(?Organization $organization = null): Department
    {
        return Department::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function designation(?Organization $organization = null): Designation
    {
        return Designation::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function payGrade(?Organization $organization = null): PayGrade
    {
        return PayGrade::create([
            'organization_id' => $this->idOf($organization),
            'grade_code' => 'G'.fake()->unique()->numerify('####'),
            'grade_name' => 'Grade',
            'min_salary' => 4000,
            'mid_salary' => 6000,
            'max_salary' => 8000,
        ]);
    }

    private function position(?Organization $organization = null): Position
    {
        return Position::create([
            'organization_id' => $this->idOf($organization),
            'position_code' => 'P'.fake()->unique()->numerify('####'),
            'position_title' => 'Supervisor',
        ]);
    }

    private function userOf(Organization $organization): User
    {
        return User::factory()->create(['organization_id' => $organization->id]);
    }

    private function idOf(?Organization $organization): int
    {
        return ($organization ?? $this->organization)->id;
    }
}
