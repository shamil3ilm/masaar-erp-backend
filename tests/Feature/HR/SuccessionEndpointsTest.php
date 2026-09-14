<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\KeyPosition;
use App\Models\HR\SuccessionCandidate;
use App\Models\HR\SuccessionPoolActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Key positions, succession candidates and their development activities stay
 * inside the caller's organization.
 *
 * Candidate and activity rows carry no organization column: a candidate
 * belongs to the organization of its key position, and an activity to that of
 * its candidate.
 */
class SuccessionEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/hr/succession';

    private Organization $otherOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.succession.view', 'hr.succession.manage']);

        $this->otherOrganization = Organization::factory()->create();
        $this->employee = $this->employee($this->organization);
    }

    public function test_positions_are_the_active_ones_by_criticality_with_candidate_counts(): void
    {
        $critical = $this->position($this->organization, 'CFO', 'critical');
        $this->candidate($critical, $this->employee);
        $high = $this->position($this->organization, 'Controller', 'high');
        $this->position($this->organization, 'Retired', 'critical', ['is_active' => false]);
        $this->position($this->otherOrganization, 'Theirs', 'critical');

        $response = $this->apiGet("{$this->baseUrl}/positions");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$critical->id, $high->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.active_candidates_count', 1);

        $this->apiGet("{$this->baseUrl}/positions?criticality=high")->assertJsonCount(1, 'data');
    }

    public function test_a_position_cannot_name_another_organizations_department_or_holder(): void
    {
        $theirDepartment = Department::create(['organization_id' => $this->otherOrganization->id, 'name' => 'Theirs', 'is_active' => true]);

        $this->apiPost("{$this->baseUrl}/positions", ['title' => 'CFO', 'criticality' => 'critical', 'department_id' => $theirDepartment->id])
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->apiPost("{$this->baseUrl}/positions", ['title' => 'CFO', 'criticality' => 'critical', 'current_holder_id' => $this->employee($this->otherOrganization)->id])
            ->assertStatus(422)->assertJsonValidationErrors('current_holder_id');

        $position = $this->position($this->organization, 'CFO', 'critical');
        $this->apiPut("{$this->baseUrl}/positions/{$position->uuid}", ['current_holder_id' => $this->employee($this->otherOrganization)->id])
            ->assertStatus(422)->assertJsonValidationErrors('current_holder_id');

        $this->apiPost("{$this->baseUrl}/positions", ['title' => 'COO', 'criticality' => 'high', 'current_holder_id' => $this->employee->id])
            ->assertCreated()
            ->assertJsonPath('data.current_holder.id', $this->employee->id);
    }

    public function test_candidates_of_a_position_are_filtered_by_readiness(): void
    {
        $position = $this->position($this->organization, 'CFO', 'critical');
        $ready = $this->candidate($position, $this->employee, SuccessionCandidate::READINESS_READY_NOW);
        $this->candidate($position, $this->employee($this->organization), SuccessionCandidate::READINESS_ONE_TWO_YEARS);

        $response = $this->apiGet("{$this->baseUrl}/positions/{$position->uuid}/candidates?readiness=ready_now");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$ready->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_candidate_is_nominated_from_the_organization_only(): void
    {
        $position = $this->position($this->organization, 'CFO', 'critical');

        $this->apiPost("{$this->baseUrl}/positions/{$position->uuid}/candidates", [
            'employee_id' => $this->employee($this->otherOrganization)->id,
            'readiness' => 'ready_now',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost("{$this->baseUrl}/positions/{$position->uuid}/candidates", [
            'employee_id' => $this->employee->id,
            'readiness' => 'ready_now',
        ])->assertCreated()->assertJsonPath('data.employee.id', $this->employee->id);

        $this->assertSame(1, SuccessionCandidate::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_candidate_and_activity_are_out_of_reach(): void
    {
        $theirPosition = $this->position($this->otherOrganization, 'Theirs', 'critical');
        $theirCandidate = $this->candidate($theirPosition, $this->employee($this->otherOrganization));
        $theirActivity = SuccessionPoolActivity::create([
            'candidate_id' => $theirCandidate->id,
            'employee_id' => $theirCandidate->employee_id,
            'activity_type' => 'mentoring',
            'title' => 'Shadow the CFO',
            'status' => SuccessionPoolActivity::STATUS_PLANNED,
        ]);

        $this->apiPut("{$this->baseUrl}/candidates/{$theirCandidate->uuid}/readiness", ['readiness' => 'ready_now'])->assertNotFound();
        $this->apiPost("{$this->baseUrl}/candidates/{$theirCandidate->uuid}/deactivate")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/candidates/{$theirCandidate->uuid}/activities", ['activity_type' => 'course', 'title' => 'MBA'])->assertNotFound();
        $this->apiPut("{$this->baseUrl}/activities/{$theirActivity->uuid}", ['status' => 'completed'])->assertNotFound();

        $theirCandidate = SuccessionCandidate::withoutGlobalScopes()->find($theirCandidate->id);
        $this->assertTrue((bool) $theirCandidate->is_active);
        $this->assertSame(SuccessionCandidate::READINESS_THREE_FIVE_YEARS, $theirCandidate->readiness);
        $this->assertSame(1, SuccessionPoolActivity::withoutGlobalScopes()->count());
        $this->assertSame(SuccessionPoolActivity::STATUS_PLANNED, SuccessionPoolActivity::withoutGlobalScopes()->find($theirActivity->id)->status);
    }

    public function test_own_candidate_readiness_and_activity_are_updated(): void
    {
        $candidate = $this->candidate($this->position($this->organization, 'CFO', 'critical'), $this->employee);

        $this->apiPut("{$this->baseUrl}/candidates/{$candidate->uuid}/readiness", ['readiness' => 'ready_now'])
            ->assertOk()
            ->assertJsonPath('data.readiness', 'ready_now');

        $activity = $this->apiPost("{$this->baseUrl}/candidates/{$candidate->uuid}/activities", ['activity_type' => 'course', 'title' => 'MBA'])
            ->assertCreated()
            ->json('data');

        $this->apiPut("{$this->baseUrl}/activities/{$activity['uuid']}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_summary_counts_the_organizations_positions(): void
    {
        $covered = $this->position($this->organization, 'CFO', 'critical');
        $this->candidate($covered, $this->employee, SuccessionCandidate::READINESS_READY_NOW);
        $this->position($this->organization, 'CTO', 'critical');
        $this->position($this->otherOrganization, 'Theirs', 'critical');

        $this->apiGet("{$this->baseUrl}/summary")
            ->assertOk()
            ->assertJsonPath('data.total_key_positions', 2)
            ->assertJsonPath('data.positions_ready_now_covered', 1)
            ->assertJsonPath('data.critical_gaps', 1);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function position(Organization $organization, string $title, string $criticality, array $overrides = []): KeyPosition
    {
        return KeyPosition::create(array_merge([
            'organization_id' => $organization->id,
            'title' => $title,
            'criticality' => $criticality,
            'is_active' => true,
        ], $overrides));
    }

    private function candidate(
        KeyPosition $position,
        Employee $employee,
        string $readiness = SuccessionCandidate::READINESS_THREE_FIVE_YEARS,
    ): SuccessionCandidate {
        return SuccessionCandidate::create([
            'key_position_id' => $position->id,
            'employee_id' => $employee->id,
            'readiness' => $readiness,
            'nominated_by' => $this->user->id,
            'is_active' => true,
        ]);
    }
}
