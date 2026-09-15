<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ApprovalRequest;
use App\Models\Core\ApprovalWorkflow;
use App\Models\Core\Organization;
use App\Models\Core\WorkflowEscalationRule;
use App\Models\Core\WorkflowSubstitutionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the workflow escalation endpoints: escalation rules, running the
 * escalation engine and approver substitutions. Workflows and users named by
 * a rule or substitution belong to the caller's organization, and a request
 * already decided in a run is not acted on again by a later rule.
 */
class WorkflowEscalationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.workflow-escalation.view', 'core.workflow-escalation.manage']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_rule_is_created_listed_updated_and_deleted(): void
    {
        $workflow = ApprovalWorkflow::factory()->create(['organization_id' => $this->organization->id]);
        $manager = $this->member(['name' => 'Manager']);

        $created = $this->apiPost('/workflow-escalation/rules', [
            'approval_workflow_id' => $workflow->id,
            'escalation_type' => 'reminder',
            'trigger_after_hours' => 24,
            'escalate_to_user_id' => $manager->id,
            'is_active' => true,
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('message', 'Escalation rule created.')
            ->assertJsonPath('data.organization_id', $this->organization->id);
        $id = $created->json('data.id');

        $this->apiGet('/workflow-escalation/rules')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.escalate_to.name', 'Manager')
            ->assertJsonPath('data.0.workflow.id', $workflow->id);

        $this->apiPut("/workflow-escalation/rules/{$id}", ['trigger_after_hours' => 48])
            ->assertOk()
            ->assertJsonPath('message', 'Escalation rule updated.')
            ->assertJsonPath('data.trigger_after_hours', 48);

        $this->apiDelete("/workflow-escalation/rules/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Escalation rule deleted.');
        $this->assertSoftDeleted('workflow_escalation_rules', ['id' => $id]);
    }

    public function test_another_organizations_users_and_workflow_are_refused(): void
    {
        $foreignUser = User::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignWorkflow = ApprovalWorkflow::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/workflow-escalation/rules', [
            'approval_workflow_id' => $foreignWorkflow->id,
            'escalation_type' => 'reminder',
            'trigger_after_hours' => 24,
            'escalate_to_user_id' => $foreignUser->id,
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('approval_workflow_id', $response->json('errors') ?? []);
        $this->assertArrayHasKey('escalate_to_user_id', $response->json('errors') ?? []);

        $rule = $this->rule(['escalation_type' => 'reminder', 'trigger_after_hours' => 1]);
        $this->apiPut("/workflow-escalation/rules/{$rule->id}", ['escalate_to_user_id' => $foreignUser->id])
            ->assertStatus(422);

        $response = $this->apiPost('/workflow-escalation/substitutions', [
            'approver_id' => $foreignUser->id,
            'substitute_id' => User::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'valid_from' => now()->toDateString(),
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('approver_id', $response->json('errors') ?? []);
        $this->assertArrayHasKey('substitute_id', $response->json('errors') ?? []);

        $this->assertSame(1, WorkflowEscalationRule::count());
        $this->assertSame(0, WorkflowSubstitutionRule::count());
    }

    public function test_another_organizations_rule_and_substitution_are_not_found(): void
    {
        $rule = WorkflowEscalationRule::withoutGlobalScopes()->create([
            'organization_id' => $this->otherOrg->id,
            'escalation_type' => 'reminder',
            'trigger_after_hours' => 1,
            'is_active' => true,
        ]);
        $substitution = WorkflowSubstitutionRule::withoutGlobalScopes()->create([
            'organization_id' => $this->otherOrg->id,
            'approver_id' => $this->user->id,
            'substitute_id' => $this->user->id,
            'valid_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->apiPut("/workflow-escalation/rules/{$rule->id}", ['trigger_after_hours' => 2])->assertNotFound();
        $this->apiDelete("/workflow-escalation/rules/{$rule->id}")->assertNotFound();
        $this->apiDelete("/workflow-escalation/substitutions/{$substitution->id}")->assertNotFound();
        $this->assertTrue((bool) $substitution->fresh()->is_active);
    }

    public function test_a_substitution_is_created_found_and_revoked(): void
    {
        $approver = $this->member();
        $substitute = $this->member(['name' => 'Stand In']);
        $payload = [
            'approver_id' => $approver->id,
            'substitute_id' => $substitute->id,
            'valid_from' => now()->subDay()->toDateString(),
            'reason' => 'Leave',
        ];

        $id = $this->apiPost('/workflow-escalation/substitutions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('message', 'Substitution rule created.')
            ->json('data.id');

        $this->apiPost('/workflow-escalation/substitutions', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'An active substitution already exists for this approver in the given period.');

        $this->apiGet('/workflow-escalation/substitutions')
            ->assertOk()
            ->assertJsonPath('data.0.substitute.name', 'Stand In');

        $this->apiGet("/workflow-escalation/substitute/{$approver->id}")
            ->assertOk()
            ->assertJsonPath('data', ['substitute_id' => $substitute->id, 'substitute_name' => 'Stand In']);

        $this->apiDelete("/workflow-escalation/substitutions/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Substitution rule revoked.');

        $this->apiGet("/workflow-escalation/substitute/{$approver->id}")
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No active substitute found for this approver.');
    }

    public function test_a_request_is_escalated_once_and_not_again_after_a_rule_decides_it(): void
    {
        $this->rule(['escalation_type' => 'auto_approve', 'trigger_after_hours' => 1]);
        $this->rule(['escalation_type' => 'auto_reject', 'trigger_after_hours' => 2]);
        $request = ApprovalRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'approval_workflow_id' => ApprovalWorkflow::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => ApprovalRequest::STATUS_PENDING,
            'submitted_at' => now()->subHours(3),
        ]);

        $this->apiPost('/workflow-escalation/check-and-escalate')
            ->assertOk()
            ->assertJsonPath('message', '1 escalation action(s) taken.')
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.actions.0.action_taken', 'auto_approved');
        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $request->fresh()->status);

        $this->apiPost('/workflow-escalation/check-and-escalate')
            ->assertOk()
            ->assertJsonPath('data.processed', 0);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(array $attributes): WorkflowEscalationRule
    {
        return WorkflowEscalationRule::create(array_merge([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ], $attributes));
    }
}
