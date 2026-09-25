<?php

declare(strict_types=1);

namespace Tests\Feature\Fraud;

use App\Models\Core\Organization;
use App\Models\Fraud\FraudAlert;
use App\Models\Fraud\FraudRule;
use App\Services\Fraud\FraudRuleTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Fraud alerts and the rules that raise them, as an organization's reviewers
 * see and manage them.
 */
class FraudEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    private FraudRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['fraud.alerts.view', 'fraud.alerts.manage', 'fraud.rules.manage']);
        $this->otherOrganization = Organization::factory()->create();

        $this->rule = $this->fraudRule($this->organization->id, ['name' => 'Large single transaction']);
    }

    // ----------------------------------------------------------------
    // Alerts
    // ----------------------------------------------------------------

    public function test_alerts_lists_this_organizations_alerts_filtered_by_severity(): void
    {
        $high = $this->alert($this->organization->id, ['severity' => 'high']);
        $this->alert($this->organization->id, ['severity' => 'low']);
        $this->alert($this->otherOrganization->id, ['severity' => 'high']);

        $this->apiGet('/fraud/alerts?severity=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $high->id)
            ->assertJsonPath('data.0.rule.id', $this->rule->id)
            ->assertJsonPath('data.0.user.id', $this->user->id);
    }

    public function test_an_alert_is_shown_and_another_organizations_is_not_found(): void
    {
        $alert = $this->alert($this->organization->id);
        $foreign = $this->alert($this->otherOrganization->id);

        $this->apiGet("/fraud/alerts/{$alert->id}")
            ->assertOk()
            ->assertJsonPath('data.evidence.amount', 50000)
            ->assertJsonPath('data.rule.name', 'Large single transaction');

        $this->apiGet("/fraud/alerts/{$foreign->id}")->assertNotFound();
    }

    public function test_reviewing_an_alert_records_the_decision_and_shows_the_reviewer_by_name(): void
    {
        $alert = $this->alert($this->organization->id);

        $response = $this->apiPatch("/fraud/alerts/{$alert->id}/status", ['status' => 'false_positive', 'notes' => 'Known customer.'])
            ->assertOk()
            ->assertJsonPath('message', 'Alert status updated.')
            ->assertJsonPath('data.status', 'false_positive')
            ->assertJsonPath('data.reviewer_notes', 'Known customer.');

        $this->assertSame(['id', 'name'], array_keys($response->json('data.reviewer')));
        $this->assertSame($this->user->id, $alert->fresh()->reviewed_by);
    }

    public function test_another_organizations_alert_cannot_be_reviewed(): void
    {
        $foreign = $this->alert($this->otherOrganization->id);

        $this->apiPatch("/fraud/alerts/{$foreign->id}/status", ['status' => 'resolved'])->assertNotFound();

        $this->assertSame('open', $foreign->fresh()->status);
    }

    // ----------------------------------------------------------------
    // Rules
    // ----------------------------------------------------------------

    public function test_rules_lists_this_organizations_rules_by_name_and_active_only(): void
    {
        $this->fraudRule($this->organization->id, ['name' => 'Aardvark velocity', 'is_active' => false]);
        $this->fraudRule($this->otherOrganization->id, ['name' => 'Foreign rule']);

        $names = array_column($this->apiGet('/fraud/rules')->assertOk()->json('data'), 'name');
        $this->assertSame(['Aardvark velocity', 'Large single transaction'], $names);

        $this->apiGet('/fraud/rules?active_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Large single transaction');
    }

    public function test_a_rule_is_created_for_the_organization(): void
    {
        $this->apiPost('/fraud/rules', [
            'name' => 'Many logins',
            'rule_type' => 'velocity',
            'entity_type' => 'login',
            'conditions' => ['max_count' => 10, 'window_minutes' => 5],
            'severity' => 'medium',
        ])->assertCreated()
            ->assertJsonPath('message', 'Fraud rule created.')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.created_by', $this->user->id);
    }

    public function test_a_rule_is_toggled_off_and_on_again(): void
    {
        $this->apiPatch("/fraud/rules/{$this->rule->id}/toggle")
            ->assertOk()
            ->assertJsonPath('message', 'Fraud rule disabled.')
            ->assertJsonPath('data.is_active', false);

        $this->apiPatch("/fraud/rules/{$this->rule->id}/toggle")
            ->assertOk()
            ->assertJsonPath('message', 'Fraud rule enabled.');
    }

    public function test_another_organizations_rule_cannot_be_toggled(): void
    {
        $foreign = $this->fraudRule($this->otherOrganization->id, ['name' => 'Foreign rule']);

        $this->apiPatch("/fraud/rules/{$foreign->id}/toggle")->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_seeding_defaults_adds_each_missing_template_once(): void
    {
        $templates = count(FraudRuleTemplates::defaults());

        $this->apiPost('/fraud/rules/seed-defaults')
            ->assertOk()
            ->assertJsonPath('data.created', $templates - 1);

        $this->apiPost('/fraud/rules/seed-defaults')
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('message', '0 default rules seeded.');

        $this->assertSame($templates, FraudRule::withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function fraudRule(int $organizationId, array $attributes = []): FraudRule
    {
        return FraudRule::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'name' => 'Rule',
            'rule_type' => FraudRule::AMOUNT,
            'entity_type' => 'invoice',
            'conditions' => ['threshold' => 10000],
            'severity' => FraudRule::HIGH,
            'is_active' => true,
            'auto_block' => false,
            'score_impact' => 40,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function alert(int $organizationId, array $attributes = []): FraudAlert
    {
        return FraudAlert::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'fraud_rule_id' => $this->rule->id,
            'entity_type' => 'invoice',
            'entity_id' => 1,
            'user_id' => $this->user->id,
            'severity' => 'high',
            'status' => FraudAlert::OPEN,
            'fraud_score' => 80,
            'evidence' => ['amount' => 50000],
        ], $attributes));
    }
}
