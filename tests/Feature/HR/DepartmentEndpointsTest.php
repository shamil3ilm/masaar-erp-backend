<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Departments: listing, creation with a manager and a parent, and deletion
 * only when nothing depends on the department.
 */
class DepartmentEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/departments';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.departments.view', 'hr.departments.create', 'hr.departments.edit', 'hr.departments.delete',
        ]);
    }

    public function test_departments_are_searched_filtered_and_sorted(): void
    {
        $operations = $this->department(['name' => 'Finance Ops', 'code' => 'FOP']);
        $this->department(['name' => 'Finance Audit', 'code' => 'FAU']);
        $this->department(['name' => 'Finance Old', 'code' => 'FOL', 'is_active' => false]);
        $this->department(['name' => 'Engineering', 'code' => 'ENG']);
        $this->department(['name' => 'Finance Child', 'code' => 'FCH', 'parent_id' => $operations->id]);

        $response = $this->apiGet("{$this->baseUrl}?search=Finance&is_active=1&root_only=1&sort_by=name&sort_order=desc");

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Finance Ops', 'Finance Audit'], array_column($response->json('data'), 'name'));
    }

    public function test_a_department_is_created_with_a_manager_of_the_organization(): void
    {
        $this->apiPost($this->baseUrl, ['name' => 'Treasury', 'code' => 'TRS', 'manager_id' => $this->user->id])
            ->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.manager.id', $this->user->id);
    }

    public function test_a_department_cannot_take_a_manager_or_parent_from_another_organization(): void
    {
        $other = Organization::factory()->create();
        $outsider = User::factory()->create(['organization_id' => $other->id]);
        $theirDepartment = $this->department(['name' => 'Theirs', 'organization_id' => $other->id]);
        $ours = $this->department(['name' => 'Ours']);

        $this->apiPost($this->baseUrl, ['name' => 'Treasury', 'manager_id' => $outsider->id])->assertStatus(422);
        $this->apiPost($this->baseUrl, ['name' => 'Payables', 'parent_id' => $theirDepartment->id])->assertStatus(422);

        $response = $this->apiPut("{$this->baseUrl}/{$ours->id}", ['manager_id' => $outsider->id]);
        $response->assertStatus(422);
        $this->assertStringNotContainsString($outsider->email, $response->getContent());

        $this->assertNull($ours->fresh()->manager_id);
        $this->assertSame(0, Department::where('name', 'Treasury')->count());
    }

    public function test_a_department_shows_its_employee_counts(): void
    {
        $department = $this->department();
        $this->employee($department);

        $this->apiGet("{$this->baseUrl}/{$department->id}")
            ->assertOk()
            ->assertJsonPath('data.employees_count', 1);
    }

    public function test_a_department_with_employees_or_sub_departments_is_not_deleted(): void
    {
        $staffed = $this->department(['name' => 'Staffed']);
        $this->employee($staffed);
        $parent = $this->department(['name' => 'Parent']);
        $this->department(['name' => 'Child', 'parent_id' => $parent->id]);
        $empty = $this->department(['name' => 'Empty']);

        $this->apiDelete("{$this->baseUrl}/{$staffed->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'Cannot delete department with assigned employees. Reassign employees first.');

        $this->apiDelete("{$this->baseUrl}/{$parent->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Cannot delete department with sub-departments. Remove or reassign sub-departments first.');

        $this->apiDelete("{$this->baseUrl}/{$empty->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Department deleted successfully.');
        $this->assertSoftDeleted($empty);
    }

    private function department(array $overrides = []): Department
    {
        return Department::create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'General',
            'is_active' => true,
        ], $overrides));
    }

    private function employee(Department $department): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
        ]);
    }
}
