<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Models\Core\ApprovalAction;
use App\Models\Core\ApprovalRequest;
use App\Models\Core\ApprovalWorkflow;
use App\Models\Core\ApprovalWorkflowStep;
use App\Models\Core\Organization;
use App\Models\Core\Role;
use App\Models\Sales\Contact;
use App\Models\User;
use App\Services\Core\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the approval workflow endpoints, keeps approvers inside the
 * organization, and approves or rejects requests through the approval
 * workflow rules: no self-approval, the next step's approvers receive their
 * actions, and a rejection closes every other pending action.
 */
class WorkflowTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private User $colleague;

    private Contact $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'automation.workflows.view',
            'automation.workflows.create',
            'automation.workflows.update',
            'automation.approvals.view',
            'automation.approvals.approve',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
        $this->colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->document = Contact::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_filters_by_type_and_activity_ordered_by_name_within_the_organization(): void
    {
        $beta = $this->workflow(['name' => 'Beta', 'approvable_type' => 'App\Models\Sales\Invoice']);
        $alpha = $this->workflow(['name' => 'Alpha', 'approvable_type' => 'App\Models\Sales\Invoice']);
        $this->workflow(['name' => 'Aardvark', 'approvable_type' => 'App\Models\Sales\Invoice', 'is_active' => false]);
        $this->workflow(['name' => 'Aaron', 'approvable_type' => 'App\Models\Purchase\PurchaseOrder']);
        ApprovalWorkflow::factory()->create(['organization_id' => $this->other->id, 'name' => 'Abacus', 'approvable_type' => 'App\Models\Sales\Invoice']);
        $this->step($alpha, 1, $this->colleague->id);

        $response = $this->apiGet('/automation/workflows?is_active=1&approvable_type='.urlencode('App\Models\Sales\Invoice'));

        $response->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$alpha->id, $beta->id], array_column($response->json('data'), 'id'));
        $this->assertCount(1, $response->json('data.0.steps'));
    }

    public function test_store_creates_the_workflow_with_its_steps_and_accepts_a_global_role(): void
    {
        $globalRole = Role::factory()->create(['organization_id' => null, 'slug' => 'global-approver']);

        $response = $this->apiPost('/automation/workflows', [
            'name' => 'Invoice approval',
            'approvable_type' => 'App\Models\Sales\Invoice',
            'steps' => [
                ['name' => 'Manager', 'approver_type' => 'user', 'approver_id' => $this->colleague->id, 'sequence' => 1],
                ['name' => 'Finance', 'approver_type' => 'role', 'approver_id' => $this->role->id, 'sequence' => 2],
                ['name' => 'Board', 'approver_type' => 'role', 'approver_id' => $globalRole->id, 'sequence' => 3],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.organization_id', $this->organization->id);
        $this->assertCount(3, $response->json('data.steps'));
    }

    public function test_a_step_cannot_name_another_organizations_user_or_role_as_approver(): void
    {
        $outsider = User::factory()->create(['organization_id' => $this->other->id]);
        $theirRole = Role::factory()->create(['organization_id' => $this->other->id, 'slug' => 'their-approver']);

        $this->apiPost('/automation/workflows', [
            'name' => 'Borrowed approvers',
            'approvable_type' => 'App\Models\Sales\Invoice',
            'steps' => [
                ['name' => 'Outsider', 'approver_type' => 'user', 'approver_id' => $outsider->id, 'sequence' => 1],
                ['name' => 'Their role', 'approver_type' => 'role', 'approver_id' => $theirRole->id, 'sequence' => 2],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['steps.0.approver_id', 'steps.1.approver_id']);

        $this->assertSame(0, ApprovalWorkflow::withoutGlobalScopes()->count());
    }

    public function test_show_and_update_are_limited_to_the_organization(): void
    {
        $mine = $this->workflow(['name' => 'Mine']);
        $theirs = ApprovalWorkflow::factory()->create(['organization_id' => $this->other->id]);

        $this->apiGet("/automation/workflows/{$theirs->id}")->assertNotFound();
        $this->apiPut("/automation/workflows/{$theirs->id}", ['name' => 'Taken'])->assertNotFound();

        $this->apiPut("/automation/workflows/{$mine->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.steps', []);
        $this->apiGet("/automation/workflows/{$mine->id}")->assertOk()->assertJsonPath('data.name', 'Renamed');
    }

    public function test_approving_a_step_hands_the_request_to_the_next_steps_approver(): void
    {
        $workflow = $this->workflow();
        $first = $this->step($workflow, 1, $this->user->id);
        $second = $this->step($workflow, 2, $this->colleague->id);
        $request = $this->approvalRequest($workflow, $first, $this->colleague->id);
        $this->action($request, $first, $this->user->id);

        $this->apiPost("/automation/approvals/{$request->id}/approve", ['comments' => 'Looks right'])
            ->assertOk()
            ->assertJsonPath('data.current_step_id', $second->id);

        $this->assertSame(
            ApprovalAction::STATUS_PENDING,
            ApprovalAction::where('approval_request_id', $request->id)->where('workflow_step_id', $second->id)->where('assigned_to', $this->colleague->id)->sole()->status
        );
    }

    public function test_approving_the_last_step_approves_the_request(): void
    {
        $workflow = $this->workflow();
        $step = $this->step($workflow, 1, $this->user->id);
        $request = $this->approvalRequest($workflow, $step, $this->colleague->id);
        $this->action($request, $step, $this->user->id);

        $this->apiPost("/automation/approvals/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequest::STATUS_APPROVED);
        $this->assertNotNull($request->fresh()->completed_at);
    }

    public function test_an_approver_cannot_approve_their_own_request(): void
    {
        $workflow = $this->workflow();
        $step = $this->step($workflow, 1, $this->user->id);
        $request = $this->approvalRequest($workflow, $step, $this->user->id);
        $action = $this->action($request, $step, $this->user->id);

        $this->apiPost("/automation/approvals/{$request->id}/approve")->assertStatus(422);

        $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame(ApprovalAction::STATUS_PENDING, $action->fresh()->status);
    }

    public function test_only_an_assigned_approver_of_this_organization_can_act(): void
    {
        $workflow = $this->workflow();
        $step = $this->step($workflow, 1, $this->colleague->id);
        $request = $this->approvalRequest($workflow, $step, $this->colleague->id);
        $this->action($request, $step, $this->colleague->id);

        $this->apiPost("/automation/approvals/{$request->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_PENDING_ACTION');

        $theirWorkflow = ApprovalWorkflow::factory()->create(['organization_id' => $this->other->id]);
        $theirStep = $this->step($theirWorkflow, 1, $this->user->id);
        $theirs = $this->approvalRequest($theirWorkflow, $theirStep, $this->colleague->id, $this->other);
        $this->action($theirs, $theirStep, $this->user->id);

        $this->apiPost("/automation/approvals/{$theirs->id}/approve")->assertNotFound();
        $this->apiPost("/automation/approvals/{$theirs->id}/reject", ['comments' => 'No'])->assertNotFound();
    }

    public function test_rejecting_closes_the_request_and_skips_the_other_pending_actions(): void
    {
        $workflow = $this->workflow();
        $step = $this->step($workflow, 1, $this->user->id);
        $request = $this->approvalRequest($workflow, $step, $this->colleague->id);
        $this->action($request, $step, $this->user->id);
        $colleagueAction = $this->action($request, $step, User::factory()->create(['organization_id' => $this->organization->id])->id);

        $this->apiPost("/automation/approvals/{$request->id}/reject")->assertStatus(422);
        $this->apiPost("/automation/approvals/{$request->id}/reject", ['comments' => 'Wrong amount'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequest::STATUS_REJECTED);

        $this->assertSame(ApprovalAction::STATUS_SKIPPED, $colleagueAction->fresh()->status);
    }

    public function test_a_stale_pending_action_is_not_rejected_after_it_was_approved(): void
    {
        $workflow = $this->workflow();
        $step = $this->step($workflow, 1, $this->user->id);
        $request = $this->approvalRequest($workflow, $step, $this->colleague->id);
        $stale = $this->action($request, $step, $this->user->id);

        ApprovalAction::whereKey($stale->id)->update(['status' => ApprovalAction::STATUS_APPROVED]);

        try {
            app(ApprovalWorkflowService::class)->reject($stale, $this->user->id, 'Too late');
            $this->fail('A processed action was rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(ApprovalAction::STATUS_APPROVED, $stale->fresh()->status);
            $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->fresh()->status);
        }
    }

    public function test_pending_lists_requests_awaiting_the_user_and_history_lists_completed_ones(): void
    {
        $workflow = $this->workflow();
        $step = $this->step($workflow, 1, $this->user->id);
        $awaiting = $this->approvalRequest($workflow, $step, $this->colleague->id);
        $this->action($awaiting, $step, $this->user->id);
        $notMine = $this->approvalRequest($workflow, $step, $this->colleague->id);
        $this->action($notMine, $step, $this->colleague->id);
        $older = $this->approvalRequest($workflow, $step, $this->colleague->id, attributes: ['status' => ApprovalRequest::STATUS_APPROVED, 'completed_at' => now()->subDay()]);
        $newer = $this->approvalRequest($workflow, $step, $this->colleague->id, attributes: ['status' => ApprovalRequest::STATUS_REJECTED, 'completed_at' => now()]);

        $pending = $this->apiGet('/automation/approvals')->assertOk();
        $this->assertSame([$awaiting->id], array_column($pending->json('data'), 'id'));

        $history = $this->apiGet('/automation/approvals/history')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($history->json('data'), 'id'));
    }

    public function test_a_step_resolves_user_and_role_approvers_only_within_the_workflows_organization(): void
    {
        $workflow = $this->workflow();
        $outsider = User::factory()->create(['organization_id' => $this->other->id]);

        $this->assertSame([$this->colleague->id], $this->step($workflow, 1, $this->colleague->id)->getApprovers());
        $this->assertSame([], $this->step($workflow, 2, $outsider->id)->getApprovers());

        $roleStep = $this->step($workflow, 3, $this->role->id);
        $roleStep->update(['approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_ROLE]);
        $this->assertSame([$this->user->id], $roleStep->fresh()->getApprovers());
    }

    private function workflow(array $attributes = []): ApprovalWorkflow
    {
        return ApprovalWorkflow::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function step(ApprovalWorkflow $workflow, int $sequence, int $approverId): ApprovalWorkflowStep
    {
        return ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'name' => "Step {$sequence}",
            'sequence' => $sequence,
            'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_USER,
            'approver_id' => $approverId,
            'action_type' => 'approve',
            'min_approvers' => 1,
        ]);
    }

    private function approvalRequest(
        ApprovalWorkflow $workflow,
        ApprovalWorkflowStep $step,
        int $submittedBy,
        ?Organization $organization = null,
        array $attributes = [],
    ): ApprovalRequest {
        return ApprovalRequest::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => Contact::class,
            'approvable_id' => $this->document->id,
            'current_step_id' => $step->id,
            'submitted_by' => $submittedBy,
            'status' => ApprovalRequest::STATUS_PENDING,
            'submitted_at' => now(),
            'completed_at' => null,
            ...$attributes,
        ]);
    }

    private function action(ApprovalRequest $request, ApprovalWorkflowStep $step, int $assignedTo): ApprovalAction
    {
        return ApprovalAction::factory()->create([
            'approval_request_id' => $request->id,
            'workflow_step_id' => $step->id,
            'assigned_to' => $assignedTo,
            'status' => ApprovalAction::STATUS_PENDING,
            'comments' => null,
            'action_at' => null,
            'expires_at' => null,
        ]);
    }
}
