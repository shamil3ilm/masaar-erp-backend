<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationRuleLog;
use App\Models\Automation\AutomationSchedule;
use App\Models\Core\Organization;
use App\Models\CRM\Lead;
use App\Models\User;
use App\Services\Automation\AutomationRuleRunner;
use App\Services\Automation\AutomationScheduleService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * The sweep that makes a scheduled rule run.
 *
 * A rule with a schedule trigger writes a pending entry for its next run, and
 * that entry is only a note until something picks it up. This covers the thing
 * that picks it up: that it runs, that it spans organizations because cron has
 * no tenant, that two sweeps overlapping on a cron tick cannot run one entry
 * twice, and that one broken rule does not take the rest of the sweep with it.
 */
class AutomationScheduleSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_entry_runs_its_rule_in_every_organization_and_books_the_next_run(): void
    {
        [$mine, $myLead, $myEntry] = $this->dueScheduledRule();
        [$theirs, $theirLead, $theirEntry] = $this->dueScheduledRule();

        $this->artisan('automation:process-schedules')->assertExitCode(0);

        foreach ([[$mine, $myLead, $myEntry], [$theirs, $theirLead, $theirEntry]] as [$rule, $lead, $entry]) {
            $this->assertSame(Lead::STATUS_QUALIFIED, $lead->fresh()->status, 'The rule\'s action did not reach its record.');

            $entry = $entry->fresh();
            $this->assertSame(AutomationSchedule::STATUS_COMPLETED, $entry->status);
            $this->assertNotNull($entry->executed_at);

            $next = AutomationSchedule::where('rule_id', $rule->id)->pending()->sole();
            $this->assertTrue($next->scheduled_for->isFuture(), 'The next occurrence was not booked ahead.');

            // One record matched, so the run is counted and logged exactly once.
            $this->assertSame(1, $rule->fresh()->execution_count);
            $this->assertSame(1, AutomationRuleLog::where('rule_id', $rule->id)->count());
        }
    }

    public function test_an_entry_that_is_not_due_yet_is_left_alone(): void
    {
        [$rule, $lead, $entry] = $this->dueScheduledRule();
        $entry->update(['scheduled_for' => now()->addHour()]);

        $this->artisan('automation:process-schedules')->assertExitCode(0);

        $this->assertSame(AutomationSchedule::STATUS_PENDING, $entry->fresh()->status);
        $this->assertSame(Lead::STATUS_NEW, $lead->fresh()->status);
        $this->assertSame(0, $rule->fresh()->execution_count);
        $this->assertSame(1, AutomationSchedule::where('rule_id', $rule->id)->count());
    }

    public function test_a_sweep_holding_an_entry_another_sweep_already_ran_does_not_run_it_again(): void
    {
        [$rule, $lead, $entry] = $this->dueScheduledRule();

        // The copy a second sweep read when it listed the same due entries.
        $stale = AutomationSchedule::findOrFail($entry->id);

        $service = app(AutomationScheduleService::class);
        $service->processScheduledRules();

        $this->assertNull($service->processSchedule($stale), 'A schedule already taken was run a second time.');

        $this->assertSame(Lead::STATUS_QUALIFIED, $lead->fresh()->status);
        $this->assertSame(1, $rule->fresh()->execution_count);
        $this->assertSame(1, AutomationRuleLog::where('rule_id', $rule->id)->count());
        $this->assertSame(1, AutomationSchedule::where('rule_id', $rule->id)->completed()->count());
        $this->assertSame(1, AutomationSchedule::where('rule_id', $rule->id)->pending()->count());
    }

    public function test_a_rule_that_throws_fails_its_own_entry_and_the_sweep_carries_on(): void
    {
        [$broken, $brokenLead, $brokenEntry] = $this->dueScheduledRule();
        [$sound, $soundLead, $soundEntry] = $this->dueScheduledRule();

        // The broken rule comes first, so the sweep has to get past it.
        $brokenEntry->update(['scheduled_for' => now()->subMinutes(5)]);

        $this->app->bind(AutomationRuleRunner::class, fn () => new class($broken->id) extends AutomationRuleRunner
        {
            public function __construct(private int $failingRuleId) {}

            public function evaluate(AutomationRule $rule, Model $entity): bool
            {
                if ($rule->id === $this->failingRuleId) {
                    throw new RuntimeException('The rule blew up mid-run.');
                }

                return parent::evaluate($rule, $entity);
            }
        });

        $this->artisan('automation:process-schedules')->assertExitCode(0);

        $brokenEntry = $brokenEntry->fresh();
        $this->assertSame(AutomationSchedule::STATUS_FAILED, $brokenEntry->status, 'The failure was not recorded against its schedule.');
        $this->assertNotNull($brokenEntry->executed_at);
        $this->assertSame(Lead::STATUS_NEW, $brokenLead->fresh()->status);

        // A run that failed still leaves the rule with a next occurrence.
        $this->assertTrue(AutomationSchedule::where('rule_id', $broken->id)->pending()->sole()->scheduled_for->isFuture());

        $this->assertSame(AutomationSchedule::STATUS_COMPLETED, $soundEntry->fresh()->status, 'One broken rule stopped the sweep.');
        $this->assertSame(Lead::STATUS_QUALIFIED, $soundLead->fresh()->status);
    }

    public function test_a_due_entry_belonging_to_an_inactive_rule_is_cleared_without_running(): void
    {
        [$rule, $lead, $entry] = $this->dueScheduledRule(['is_active' => false]);

        $this->artisan('automation:process-schedules')->assertExitCode(0);

        $this->assertSame(0, AutomationSchedule::where('rule_id', $rule->id)->count(), 'A switched-off rule kept a schedule entry.');
        $this->assertSame(Lead::STATUS_NEW, $lead->fresh()->status);
        $this->assertSame(0, $rule->fresh()->execution_count);
    }

    public function test_an_entry_a_sweep_took_and_never_finished_is_failed_and_the_rule_booked_forward(): void
    {
        [$rule, $lead, $entry] = $this->dueScheduledRule();

        // What a sweep killed mid-run leaves behind: taken, and never resolved.
        $entry->forceFill([
            'status' => AutomationSchedule::STATUS_RUNNING,
            'updated_at' => now()->subHours(3),
        ])->saveQuietly();

        $this->artisan('automation:process-schedules')->assertExitCode(0);

        $entry = $entry->fresh();
        $this->assertSame(AutomationSchedule::STATUS_FAILED, $entry->status, 'An abandoned entry was left running forever.');

        // The run it belonged to may have done half its work, so it is not run
        // again; the rule only gets its cadence back.
        $this->assertSame(Lead::STATUS_NEW, $lead->fresh()->status);
        $this->assertTrue(AutomationSchedule::where('rule_id', $rule->id)->pending()->sole()->scheduled_for->isFuture());
    }

    public function test_an_entry_that_cannot_be_taken_does_not_stop_the_sweep(): void
    {
        [$broken, , $brokenEntry] = $this->dueScheduledRule();
        [, $soundLead, $soundEntry] = $this->dueScheduledRule();

        $brokenEntry->update(['scheduled_for' => now()->subMinutes(5)]);

        // Taking an entry has its own way of failing: a lock wait, a dropped
        // connection. It happens before anything can be recorded against the
        // entry, and it is still one entry's problem.
        $service = new class(app(AutomationRuleRunner::class), $broken->id) extends AutomationScheduleService
        {
            public function __construct(AutomationRuleRunner $runner, private int $unreachableRuleId)
            {
                parent::__construct($runner);
            }

            public function processSchedule(AutomationSchedule $schedule): ?array
            {
                if ($schedule->rule_id === $this->unreachableRuleId) {
                    throw new RuntimeException('The entry could not be taken.');
                }

                return parent::processSchedule($schedule);
            }
        };

        $results = $service->processScheduledRules();

        $this->assertSame(AutomationSchedule::STATUS_COMPLETED, $soundEntry->fresh()->status, 'An entry that could not be taken stopped the sweep.');
        $this->assertSame(Lead::STATUS_QUALIFIED, $soundLead->fresh()->status);
        $this->assertSame(['failed', 'completed'], array_column($results, 'status'));
    }

    public function test_a_rule_that_already_holds_its_next_run_does_not_book_a_second(): void
    {
        [$rule, , $entry] = $this->dueScheduledRule();

        $booked = app(AutomationScheduleService::class)->create($rule);

        $this->assertSame($entry->id, $booked?->id);
        $this->assertSame(1, AutomationSchedule::where('rule_id', $rule->id)->pending()->count());
    }

    public function test_a_rule_booked_beneath_a_caller_keeps_the_one_entry_without_erroring(): void
    {
        [$rule, , $entry] = $this->dueScheduledRule();

        // The rule holds nothing, as it does the moment a run completes.
        $entry->delete();

        // The window the read-then-write leaves open: another process writes
        // the rule's next run after this one has looked and found none, and
        // before its own write lands.
        $raced = false;
        Event::listen('eloquent.creating: ' . AutomationSchedule::class, function () use ($rule, &$raced): void {
            if ($raced) {
                return;
            }

            $raced = true;

            AutomationSchedule::create([
                'rule_id' => $rule->id,
                'scheduled_for' => now()->addHour(),
                'status' => AutomationSchedule::STATUS_PENDING,
            ]);
        });

        $booked = app(AutomationScheduleService::class)->create($rule);

        $this->assertTrue($raced, 'The competing booking never ran, so the race was not exercised.');
        $this->assertNotNull($booked, 'Losing the race left the rule with no next run.');
        $this->assertSame(1, AutomationSchedule::where('rule_id', $rule->id)->count(), 'The rule booked its next run twice.');
        $this->assertSame($booked->id, AutomationSchedule::where('rule_id', $rule->id)->pending()->sole()->id);
    }

    public function test_the_database_refuses_a_second_pending_entry_for_one_rule(): void
    {
        [$rule, , ] = $this->dueScheduledRule();

        // What the booking code's read cannot prevent on its own.
        $this->expectException(UniqueConstraintViolationException::class);

        AutomationSchedule::create([
            'rule_id' => $rule->id,
            'scheduled_for' => now()->addHour(),
            'status' => AutomationSchedule::STATUS_PENDING,
        ]);
    }

    public function test_an_entry_that_leaves_pending_frees_the_rule_to_book_its_next_run(): void
    {
        [$rule, , $entry] = $this->dueScheduledRule();

        $entry->markAsCompleted();

        $next = app(AutomationScheduleService::class)->create($rule);

        $this->assertNotNull($next);
        $this->assertNotSame($entry->id, $next->id, 'A completed entry was handed back as the next run.');
        $this->assertNull($entry->fresh()->pending_rule_id, 'A completed entry still holds the rule on the pending marker.');
        $this->assertSame($rule->id, (int) $next->pending_rule_id);
    }


    public function test_the_sweep_is_registered_on_the_scheduler_every_minute(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn ($event) => str_contains((string) $event->command, 'automation:process-schedules')
        ));

        $this->assertCount(1, $events, 'automation:process-schedules is not on the framework scheduler.');
        $this->assertSame('* * * * *', $events[0]->expression);
    }

    /**
     * A scheduled rule of its own organization, a record it matches, and a
     * pending entry that came due a minute ago.
     *
     * @return array{0: AutomationRule, 1: Lead, 2: AutomationSchedule}
     */
    private function dueScheduledRule(array $attributes = []): array
    {
        $organization = Organization::factory()->create();

        $rule = AutomationRule::factory()->create([
            'organization_id' => $organization->id,
            'trigger_type' => AutomationRule::TRIGGER_SCHEDULE,
            'trigger_schedule' => '0 * * * *',
            'entity_type' => 'lead',
            'conditions' => [['field' => 'status', 'operator' => '=', 'value' => Lead::STATUS_NEW]],
            'actions' => [['type' => 'update_field', 'field' => 'status', 'value' => Lead::STATUS_QUALIFIED]],
            'execution_count' => 0,
            'is_active' => true,
            'created_by' => User::factory()->create(['organization_id' => $organization->id])->id,
            ...$attributes,
        ]);

        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'status' => Lead::STATUS_NEW,
        ]);

        $entry = AutomationSchedule::factory()->create([
            'rule_id' => $rule->id,
            'scheduled_for' => now()->subMinute(),
            'executed_at' => null,
            'status' => AutomationSchedule::STATUS_PENDING,
        ]);

        return [$rule, $lead, $entry];
    }
}
