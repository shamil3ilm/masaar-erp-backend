<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\PersonnelAction;
use App\Services\HR\PersonnelActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Personnel actions: initiated as a draft with their steps, submitted, then
 * approved and applied to the employee or rejected, and reversed where the
 * action type allows it; all inside the organization that owns them.
 */
class PersonnelActionTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/personnel-actions';

    private Employee $employee;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.lifecycle.view', 'hr.lifecycle.manage']);

        $this->employee = $this->employee($this->organization);
    }

    public function test_actions_are_listed_newest_first_with_a_slim_employee_and_filtered_by_status(): void
    {
        $older = $this->action(['status' => PersonnelAction::STATUS_SUBMITTED, 'created_at' => now()->subDay()]);
        $newer = $this->action(['status' => PersonnelAction::STATUS_SUBMITTED]);
        $this->action(['status' => PersonnelAction::STATUS_DRAFT]);
        $other = Organization::factory()->create();
        $this->action([
            'organization_id' => $other->id,
            'employee_id' => $this->employee($other)->id,
            'status' => PersonnelAction::STATUS_SUBMITTED,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?status=submitted");

        $response->assertOk()->assertJsonPath('data.per_page', 25);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data.data'), 'id'));

        $employeeKeys = array_keys($response->json('data.data.0.employee'));
        sort($employeeKeys);
        $this->assertSame(['employee_number', 'first_name', 'id', 'last_name'], $employeeKeys);
    }

    public function test_an_action_is_initiated_as_a_draft_with_its_steps(): void
    {
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPost($this->baseUrl, [
            'employee_id' => $this->employee->id,
            'action_type' => 'transfer',
            'effective_date' => '2026-05-01',
            'payload' => ['department_id' => $department->id],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', PersonnelAction::STATUS_DRAFT)
            ->assertJsonCount(4, 'data.steps');
    }

    public function test_an_action_cannot_name_another_organizations_employee_or_department(): void
    {
        $other = Organization::factory()->create();
        $theirEmployee = $this->employee($other);
        $theirDepartment = Department::factory()->create(['organization_id' => $other->id]);

        $this->apiPost($this->baseUrl, [
            'employee_id' => $theirEmployee->id,
            'action_type' => 'promotion',
            'effective_date' => '2026-05-01',
        ])->assertStatus(422);

        $this->apiPost($this->baseUrl, [
            'employee_id' => $this->employee->id,
            'action_type' => 'transfer',
            'effective_date' => '2026-05-01',
            'payload' => ['department_id' => $theirDepartment->id],
        ])->assertStatus(422);

        $this->assertSame(0, PersonnelAction::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_action_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirs = $this->action(['organization_id' => $other->id, 'employee_id' => $this->employee($other)->id]);

        $this->apiGet("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->id}/submit")->assertNotFound();
    }

    public function test_a_transfer_is_submitted_approved_and_applied_to_the_employee(): void
    {
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);
        $action = $this->initiated('transfer', ['department_id' => $department->id]);

        $this->apiPost("{$this->baseUrl}/{$action->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', PersonnelAction::STATUS_SUBMITTED);

        $this->apiPost("{$this->baseUrl}/{$action->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', PersonnelAction::STATUS_COMPLETED)
            ->assertJsonCount(4, 'data.steps');

        $this->assertSame($department->id, $this->employee->fresh()->department_id);
    }

    public function test_a_transition_that_is_not_allowed_is_refused_as_an_invalid_status(): void
    {
        $draft = $this->action();

        $this->apiPost("{$this->baseUrl}/{$draft->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(PersonnelAction::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_only_transfers_promotions_and_demotions_are_reversed(): void
    {
        $hire = $this->action(['action_type' => PersonnelAction::TYPE_HIRE, 'status' => PersonnelAction::STATUS_COMPLETED]);
        $transfer = $this->action(['status' => PersonnelAction::STATUS_COMPLETED]);

        $this->apiPost("{$this->baseUrl}/{$hire->id}/reverse")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->apiPost("{$this->baseUrl}/{$transfer->id}/reverse")
            ->assertOk()
            ->assertJsonPath('data.status', PersonnelAction::STATUS_REVERSED);
    }

    public function test_a_rejected_action_cannot_be_approved_through_a_stale_copy(): void
    {
        $this->actingAs($this->user, 'api');
        $service = app(PersonnelActionService::class);

        $action = $this->action(['status' => PersonnelAction::STATUS_SUBMITTED]);
        $stale = PersonnelAction::findOrFail($action->id);

        $service->reject($action, $this->user, 'Not now');

        $this->assertRejected(fn () => $service->approve($stale, $this->user));
        $this->assertSame(PersonnelAction::STATUS_REJECTED, $action->fresh()->status);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function action(array $overrides = []): PersonnelAction
    {
        return PersonnelAction::forceCreate(array_merge([
            'organization_id' => $this->organization->id,
            'action_number' => 'PA-TEST-'.(++$this->sequence),
            'employee_id' => $this->employee->id,
            'action_type' => PersonnelAction::TYPE_TRANSFER,
            'effective_date' => '2026-05-01',
            'status' => PersonnelAction::STATUS_DRAFT,
            'initiated_by' => $this->user->id,
        ], $overrides));
    }

    private function initiated(string $type, array $payload): PersonnelAction
    {
        $this->actingAs($this->user, 'api');

        return app(PersonnelActionService::class)->initiate([
            'employee_id' => $this->employee->id,
            'action_type' => $type,
            'effective_date' => '2026-05-01',
            'payload' => $payload,
        ], $this->user);
    }
}
