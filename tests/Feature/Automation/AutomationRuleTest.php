<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationRuleLog;
use App\Models\Automation\AutomationSchedule;
use App\Models\Core\Organization;
use App\Models\CRM\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the automation rule endpoints and keeps a rule test on the caller's
 * own records.
 */
class AutomationRuleTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['automation.rules.view', 'automation.rules.manage']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_filters_active_rules_highest_priority_first_within_the_organization(): void
    {
        $low = $this->rule(['priority' => 5]);
        $high = $this->rule(['priority' => 10]);
        $this->rule(['priority' => 50, 'is_active' => false]);
        AutomationRule::factory()->create([
            'organization_id' => $this->other->id,
            'priority' => 99,
            'created_by' => User::factory()->create(['organization_id' => $this->other->id])->id,
        ]);

        $response = $this->apiGet('/automation/rules?is_active=true');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$high->id, $low->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->user->id, $response->json('data.0.creator.id'));
    }

    public function test_destroy_removes_the_rule_with_its_logs_and_schedules(): void
    {
        $rule = $this->rule();
        AutomationRuleLog::factory()->create(['rule_id' => $rule->id]);
        AutomationSchedule::factory()->create(['rule_id' => $rule->id]);

        $this->apiDelete("/automation/rules/{$rule->getRouteKey()}")->assertOk();

        $this->assertNull(AutomationRule::find($rule->id));
        $this->assertSame(0, AutomationRuleLog::where('rule_id', $rule->id)->count());
        $this->assertSame(0, AutomationSchedule::where('rule_id', $rule->id)->count());
    }

    public function test_set_active_refuses_a_no_op_and_deactivates(): void
    {
        $rule = $this->rule(['is_active' => true]);

        $this->apiPatch("/automation/rules/{$rule->getRouteKey()}/active", ['active' => true])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_ACTIVE');
        $this->apiPatch("/automation/rules/{$rule->getRouteKey()}/active", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_rule_is_tested_only_against_the_organizations_records(): void
    {
        $rule = $this->rule(['entity_type' => 'lead']);
        $mine = Lead::factory()->create(['organization_id' => $this->organization->id]);
        $theirs = Lead::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost("/automation/rules/{$rule->getRouteKey()}/test", ['entity_type' => 'lead', 'entity_id' => $theirs->id])
            ->assertNotFound();
        $this->apiPost("/automation/rules/{$rule->getRouteKey()}/test", ['entity_type' => 'spaceship', 'entity_id' => $mine->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ENTITY_TYPE');
        $this->apiPost("/automation/rules/{$rule->getRouteKey()}/test", ['entity_type' => 'lead', 'entity_id' => $mine->id])
            ->assertOk()
            ->assertJsonPath('data.entity_id', $mine->id)
            ->assertJsonStructure(['data' => ['conditions_met', 'actions_would_execute']]);
    }

    public function test_logs_filter_by_status_newest_first(): void
    {
        $rule = $this->rule();
        $older = AutomationRuleLog::factory()->create(['rule_id' => $rule->id, 'status' => 'failed', 'created_at' => now()->subDay()]);
        $newer = AutomationRuleLog::factory()->create(['rule_id' => $rule->id, 'status' => 'failed']);
        AutomationRuleLog::factory()->create(['rule_id' => $rule->id, 'status' => 'success']);

        $response = $this->apiGet("/automation/rules/{$rule->getRouteKey()}/logs?status=failed")->assertOk();

        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_scheduled_rule_carries_a_pending_schedule_through_its_lifecycle(): void
    {
        $created = $this->apiPost('/automation/rules', [
            'name' => 'Nightly overdue sweep',
            'trigger_type' => 'schedule',
            'trigger_schedule' => '0 2 * * *',
            'entity_type' => 'invoice',
            'conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'overdue']],
            'actions' => [['type' => 'send_email']],
        ])->assertCreated();

        $rule = AutomationRule::findOrFail($created->json('data.id'));
        $this->assertSame('02:00', $this->pendingSchedule($rule)->scheduled_for->format('H:i'));

        $this->apiPut("/automation/rules/{$rule->getRouteKey()}", ['trigger_schedule' => '0 3 * * *'])->assertOk();
        $this->assertSame('03:00', $this->pendingSchedule($rule)->scheduled_for->format('H:i'));

        $this->apiPatch("/automation/rules/{$rule->getRouteKey()}/active", ['active' => false])->assertOk();
        $this->assertSame(0, AutomationSchedule::where('rule_id', $rule->id)->count());

        $this->apiPatch("/automation/rules/{$rule->getRouteKey()}/active", ['active' => true])->assertOk();
        $this->assertSame('03:00', $this->pendingSchedule($rule)->scheduled_for->format('H:i'));
    }

    /**
     * The rule's only pending schedule.
     */
    private function pendingSchedule(AutomationRule $rule): AutomationSchedule
    {
        $schedules = AutomationSchedule::where('rule_id', $rule->id)->pending()->get();
        $this->assertCount(1, $schedules);

        return $schedules->first();
    }

    private function rule(array $attributes = []): AutomationRule
    {
        return AutomationRule::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
