<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * HR reports and the statutory calculator take department and employee ids
 * from the caller's organization only.
 */
class HRReportsEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        // The calculator is a POST; a user holding only view permissions is refused writes.
        $this->setUpAuthenticatedUser(['hr.reports.view', 'hr.payroll.view', 'core.test.act']);

        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_reports_are_filtered_by_a_department_of_the_organization(): void
    {
        $ours = Department::create(['organization_id' => $this->organization->id, 'name' => 'Finance', 'is_active' => true]);

        $this->apiGet("/hr/reports/headcount?department_id={$ours->id}")->assertOk();
    }

    public function test_reports_refuse_another_organizations_department(): void
    {
        $theirs = Department::create(['organization_id' => $this->otherOrganization->id, 'name' => 'Theirs', 'is_active' => true]);

        $this->apiGet("/hr/reports/headcount?department_id={$theirs->id}")
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->apiGet("/hr/reports/attendance?start_date=2026-01-01&end_date=2026-01-31&department_id={$theirs->id}")
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->apiGet("/hr/reports/leave-analysis?start_date=2026-01-01&end_date=2026-01-31&department_id={$theirs->id}")
            ->assertStatus(422)->assertJsonValidationErrors('department_id');
    }

    public function test_the_statutory_calculator_refuses_another_organizations_employee(): void
    {
        $theirs = Employee::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/hr/statutory/calculate', ['gross_salary' => 10000, 'employee_id' => $theirs->id])
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    public function test_the_statutory_calculator_uses_an_employee_of_the_organization(): void
    {
        $ours = Employee::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);

        $this->apiPost('/hr/statutory/calculate', ['gross_salary' => 10000, 'employee_id' => $ours->id])
            ->assertOk()
            ->assertJsonPath('data.gross_salary', 10000);
    }
}
