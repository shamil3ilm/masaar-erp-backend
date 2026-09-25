<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationRuleLog;
use App\Models\Core\Organization;
use App\Models\CRM\Lead;
use App\Models\User;
use App\Services\Automation\AutomationRuleRunner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the half of automation every trigger shares: whether a rule's
 * conditions hold for a record, and what executing its actions leaves behind.
 *
 * The rule endpoints reach this through a test request and a due schedule
 * reaches it through AutomationScheduleService, so the semantics are pinned
 * here once rather than through each caller.
 */
class AutomationRuleRunnerTest extends TestCase
{
    use RefreshDatabase;

    private AutomationRuleRunner $runner;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new AutomationRuleRunner();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_rule_without_conditions_matches_any_record(): void
    {
        $this->assertTrue($this->runner->evaluate($this->rule(['conditions' => []]), $this->lead()));
    }

    public function test_a_bare_condition_is_read_as_a_group_of_one(): void
    {
        $lead = $this->lead(['status' => Lead::STATUS_NEW]);

        $matching = $this->rule(['conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'new']]]);
        $missing = $this->rule(['conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'lost']]]);

        $this->assertTrue($this->runner->evaluate($matching, $lead));
        $this->assertFalse($this->runner->evaluate($missing, $lead));
    }

    public function test_an_and_group_needs_every_condition_and_an_or_group_needs_one(): void
    {
        $lead = $this->lead(['status' => Lead::STATUS_NEW, 'country_code' => 'SA']);

        $and = fn (string $country): array => [['operator' => 'and', 'rules' => [
            ['field' => 'status', 'operator' => '=', 'value' => 'new'],
            ['field' => 'country_code', 'operator' => '=', 'value' => $country],
        ]]];

        $this->assertTrue($this->runner->evaluate($this->rule(['conditions' => $and('SA')]), $lead));
        $this->assertFalse($this->runner->evaluate($this->rule(['conditions' => $and('AE')]), $lead));

        $or = [['operator' => 'or', 'rules' => [
            ['field' => 'status', 'operator' => '=', 'value' => 'lost'],
            ['field' => 'country_code', 'operator' => '=', 'value' => 'SA'],
        ]]];

        $this->assertTrue($this->runner->evaluate($this->rule(['conditions' => $or]), $lead));
    }

    public function test_groups_beside_each_other_all_have_to_hold(): void
    {
        $lead = $this->lead(['status' => Lead::STATUS_NEW, 'country_code' => 'SA']);

        $rule = $this->rule(['conditions' => [
            ['field' => 'status', 'operator' => '=', 'value' => 'new'],
            ['field' => 'country_code', 'operator' => '=', 'value' => 'AE'],
        ]]);

        $this->assertFalse($this->runner->evaluate($rule, $lead));
    }

    public function test_executing_actions_writes_the_record_counts_the_run_and_logs_it(): void
    {
        $lead = $this->lead(['status' => Lead::STATUS_NEW]);
        $rule = $this->rule([
            'actions' => [['type' => 'update_field', 'field' => 'status', 'value' => Lead::STATUS_QUALIFIED]],
            'execution_count' => 0,
        ]);

        $executed = $this->runner->executeActions($rule, $lead);

        $this->assertSame('update_field', $executed[0]['type']);
        $this->assertSame('success', $executed[0]['result']['status']);
        $this->assertSame(Lead::STATUS_QUALIFIED, $lead->fresh()->status);

        $rule = $rule->fresh();
        $this->assertSame(1, $rule->execution_count);
        $this->assertNotNull($rule->last_executed_at);

        $log = AutomationRuleLog::where('rule_id', $rule->id)->sole();
        $this->assertSame(AutomationRuleLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(Lead::class, $log->entity_type);
        $this->assertSame($lead->id, $log->entity_id);
        $this->assertSame('update_field', $log->actions_executed[0]['type']);
        $this->assertNull($log->error_message);
    }

    public function test_an_action_type_the_runner_does_not_know_is_skipped(): void
    {
        $rule = $this->rule(['actions' => [['type' => 'teleport']]]);

        $executed = $this->runner->executeActions($rule, $this->lead());

        $this->assertSame(['status' => 'skipped', 'reason' => 'Unknown action type: teleport'], $executed[0]['result']);
        $this->assertSame(
            AutomationRuleLog::STATUS_SUCCESS,
            AutomationRuleLog::where('rule_id', $rule->id)->sole()->status
        );
    }

    public function test_a_failing_action_is_logged_and_raised_to_the_caller(): void
    {
        $lead = $this->lead();
        $rule = $this->rule([
            'actions' => [['type' => 'update_field', 'field' => 'contact_name', 'value' => null]],
            'execution_count' => 0,
        ]);

        try {
            $this->runner->executeActions($rule, $lead);
            $this->fail('A write the database refused was swallowed instead of raised.');
        } catch (QueryException) {
            // The caller decides what a failed run means; the log below is this test's subject.
        }

        $log = AutomationRuleLog::where('rule_id', $rule->id)->sole();
        $this->assertSame(AutomationRuleLog::STATUS_FAILED, $log->status);
        $this->assertNotNull($log->error_message);
        $this->assertSame(0, $rule->fresh()->execution_count);
    }

    public function test_an_entity_type_resolves_to_its_model_and_a_record_only_inside_its_organization(): void
    {
        $mine = $this->lead();
        $theirs = Lead::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->assertSame(Lead::class, $this->runner->entityClassFor('lead'));
        $this->assertNull($this->runner->entityClassFor('spaceship'));
        $this->assertNull($this->runner->entityClassFor(AutomationRule::ENTITY_EXPENSE));

        $this->assertSame($mine->id, $this->runner->findEntity($this->organization->id, Lead::class, $mine->id)?->getKey());
        $this->assertNull($this->runner->findEntity($this->organization->id, Lead::class, $theirs->id));
    }

    private function rule(array $attributes = []): AutomationRule
    {
        return AutomationRule::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'entity_type' => 'lead',
            ...$attributes,
        ]);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::factory()->create([
            'organization_id' => $this->organization->id,
            ...$attributes,
        ]);
    }
}
