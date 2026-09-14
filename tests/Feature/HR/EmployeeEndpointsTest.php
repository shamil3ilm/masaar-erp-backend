<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Employee listing filters and salary assignment, inside the caller's
 * organization only.
 */
class EmployeeEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/employees';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.employees.view', 'hr.employees.salary']);
    }

    public function test_employees_are_searched_filtered_and_sorted(): void
    {
        $this->employee(['first_name' => 'Zayd', 'last_name' => 'Haddad']);
        $this->employee(['first_name' => 'Amal', 'last_name' => 'Haddad']);
        $this->employee(['first_name' => 'Hana', 'last_name' => 'Haddad', 'employment_status' => Employee::STATUS_TERMINATED]);
        $this->employee(['first_name' => 'Omar', 'last_name' => 'Saleh']);

        $response = $this->apiGet("{$this->baseUrl}?search=Haddad&status=active&sort_by=first_name&sort_order=desc");

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Zayd', 'Amal'], array_column($response->json('data'), 'first_name'));
    }

    public function test_a_salary_is_assigned_from_the_organizations_structure(): void
    {
        $employee = $this->employee();
        $structure = SalaryStructure::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPost("{$this->baseUrl}/{$employee->id}/salary", [
            'salary_structure_id' => $structure->id,
            'effective_from' => '2026-01-01',
            'components' => ['BASIC' => 5000],
            'reason' => 'Joining',
        ])
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.salary_structure_id', $structure->id)
            ->assertJsonPath('message', 'Salary assigned successfully.');
    }

    public function test_another_organizations_salary_structure_is_not_found(): void
    {
        $employee = $this->employee();
        $theirs = SalaryStructure::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/{$employee->id}/salary", [
            'salary_structure_id' => $theirs->id,
            'effective_from' => '2026-01-01',
            'components' => ['BASIC' => 5000],
        ])->assertNotFound();

        $this->assertDatabaseMissing('employee_salaries', ['employee_id' => $employee->id]);
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ], $overrides));
    }
}
