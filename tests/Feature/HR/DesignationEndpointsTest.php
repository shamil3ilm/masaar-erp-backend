<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Designation;
use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Designations: listing, creation and deletion only when no employee holds
 * the designation.
 */
class DesignationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/designations';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.designations.view', 'hr.designations.create', 'hr.designations.edit', 'hr.designations.delete',
        ]);
    }

    public function test_designations_are_searched_filtered_by_level_and_sorted(): void
    {
        $this->designation(['name' => 'Engineer I', 'code' => 'ENG1', 'level' => 2]);
        $this->designation(['name' => 'Engineer II', 'code' => 'ENG2', 'level' => 2]);
        $this->designation(['name' => 'Engineer III', 'code' => 'ENG3', 'level' => 3]);
        $this->designation(['name' => 'Engineer Old', 'code' => 'ENGO', 'level' => 2, 'is_active' => false]);
        $this->designation(['name' => 'Analyst', 'code' => 'ANL', 'level' => 2]);

        $response = $this->apiGet('/hr/designations?search=Engineer&level=2&is_active=1&sort_by=name&sort_order=asc');

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Engineer I', 'Engineer II'], array_column($response->json('data'), 'name'));
    }

    public function test_a_designation_is_created_and_shown_with_its_counts(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'name' => 'Architect',
            'code' => 'ARC',
            'level' => 5,
            'min_salary' => 1000,
            'max_salary' => 2000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Architect')
            ->assertJsonPath('data.organization_id', $this->organization->id);

        $this->apiGet("{$this->baseUrl}/{$response->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.employees_count', 0);
    }

    public function test_a_designation_held_by_employees_is_not_deleted(): void
    {
        $held = $this->designation(['name' => 'Held', 'code' => 'HLD']);
        Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'designation_id' => $held->id,
        ]);
        $free = $this->designation(['name' => 'Free', 'code' => 'FRE']);

        $this->apiDelete("{$this->baseUrl}/{$held->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Cannot delete designation with assigned employees. Reassign employees first.');

        $this->apiDelete("{$this->baseUrl}/{$free->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Designation deleted successfully.');
    }

    private function designation(array $overrides = []): Designation
    {
        return Designation::create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'General',
            'is_active' => true,
        ], $overrides));
    }
}
