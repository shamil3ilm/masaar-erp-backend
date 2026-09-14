<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\ProbationPeriod;
use App\Models\User;
use App\Services\HR\ProbationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Probation periods: created active, then updated, extended, completed by a
 * reviewer or waived, inside the organization that owns them.
 */
class ProbationTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/probation';

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.lifecycle.view', 'hr.lifecycle.manage']);

        $this->employee = $this->employee($this->organization);
    }

    public function test_a_probation_period_is_created_active(): void
    {
        $this->apiPost($this->baseUrl, [
            'employee_id' => $this->employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-01',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', ProbationPeriod::STATUS_ACTIVE)
            ->assertJsonPath('data.employee.id', $this->employee->id);
    }

    public function test_a_probation_period_cannot_name_another_organizations_employee(): void
    {
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost($this->baseUrl, [
            'employee_id' => $theirs->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-01',
        ])->assertStatus(422);

        $this->assertSame(0, ProbationPeriod::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_probation_period_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirs = $this->period(['organization_id' => $other->id, 'employee_id' => $this->employee($other)->id]);

        $this->apiGet("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->id}/waive")->assertNotFound();
    }

    public function test_only_a_period_that_is_not_completed_is_updated(): void
    {
        $active = $this->period();
        $completed = $this->period(['status' => ProbationPeriod::STATUS_COMPLETED]);

        $this->apiPut("{$this->baseUrl}/{$active->id}", ['review_date' => '2026-03-01'])
            ->assertOk()
            ->assertJsonPath('data.employee.id', $this->employee->id);
        $this->assertSame('2026-03-01', $active->fresh()->review_date->toDateString());

        $this->apiPut("{$this->baseUrl}/{$completed->id}", ['review_date' => '2026-03-01'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_period_is_extended_past_its_end_date(): void
    {
        $period = $this->period();

        $this->apiPost("{$this->baseUrl}/{$period->id}/extend", ['new_end_date' => '2026-03-01'])
            ->assertStatus(422);

        $this->apiPost("{$this->baseUrl}/{$period->id}/extend", ['new_end_date' => '2026-06-01', 'reason' => 'More time'])
            ->assertOk()
            ->assertJsonPath('data.status', ProbationPeriod::STATUS_EXTENDED)
            ->assertJsonPath('data.review_notes', 'More time');
    }

    public function test_a_period_is_completed_by_a_reviewer_of_the_organization_only(): void
    {
        $period = $this->period();
        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/{$period->id}/complete", [
            'outcome' => 'confirmed',
            'reviewer_id' => $outsider->id,
            'notes' => 'Good',
        ])->assertStatus(422);

        $this->apiPost("{$this->baseUrl}/{$period->id}/complete", [
            'outcome' => 'confirmed',
            'reviewer_id' => $this->user->id,
            'notes' => 'Good',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ProbationPeriod::STATUS_COMPLETED)
            ->assertJsonPath('data.outcome', 'confirmed')
            ->assertJsonPath('data.reviewer.id', $this->user->id);

        $this->apiPost("{$this->baseUrl}/{$period->id}/waive")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_periods_due_soon_are_the_organizations_active_ones(): void
    {
        $due = $this->period(['end_date' => now()->addDays(10)->toDateString()]);
        $this->period(['end_date' => now()->addDays(90)->toDateString()]);
        $other = Organization::factory()->create();
        $this->period([
            'organization_id' => $other->id,
            'employee_id' => $this->employee($other)->id,
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->apiGet("{$this->baseUrl}/due-soon?days_ahead=30");

        $response->assertOk();
        $this->assertSame([$due->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_completed_period_cannot_be_waived_through_a_stale_copy(): void
    {
        $this->actingAs($this->user, 'api');
        $service = app(ProbationService::class);

        $period = $this->period();
        $stale = ProbationPeriod::findOrFail($period->id);

        $service->complete($period, ProbationPeriod::OUTCOME_CONFIRMED, $this->user->id, 'Good');

        $this->assertRejected(fn () => $service->waive($stale));
        $this->assertSame(ProbationPeriod::STATUS_COMPLETED, $period->fresh()->status);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function period(array $overrides = []): ProbationPeriod
    {
        return ProbationPeriod::create(array_merge([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-01',
            'status' => ProbationPeriod::STATUS_ACTIVE,
        ], $overrides));
    }
}
