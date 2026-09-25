<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationSchedule;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps a scheduled rule's next run: reads its cron expression, holds the
 * pending entry for it, and runs the rule over its entity set when due.
 *
 * Whether a record matches the rule, and what happens when it does, is
 * AutomationRuleRunner's work.
 */
class AutomationScheduleService
{
    /**
     * Records read at a time when a rule sweeps its entity set.
     */
    private const ENTITIES_PER_CHUNK = 200;

    /**
     * How long an entry may sit claimed before the sweep that took it is
     * treated as gone. Long enough that a rule still working through a large
     * entity set is not declared dead underneath itself.
     */
    private const ABANDONED_AFTER_MINUTES = 60;

    public function __construct(
        private AutomationRuleRunner $runner
    ) {}

    /**
     * Book the rule's next run, or hand back the one it already holds.
     *
     * A rule has one next run. Two callers can arrive at once — a sweep
     * finishing a run beside an operator switching the rule back on — and the
     * read below cannot see an entry the other caller has not written yet, so
     * the unique index on automation_schedules.pending_rule_id is what holds
     * the rule to one entry. Losing that race is not a failure: the entry the
     * winner wrote is the rule's next run just as this one would have been.
     * Callers that mean to move the next run clear the pending entry first.
     */
    public function create(AutomationRule $rule): ?AutomationSchedule
    {
        if (!$rule->isScheduled() || empty($rule->trigger_schedule)) {
            return null;
        }

        $booked = $rule->schedules()->pending()->first();

        if ($booked) {
            return $booked;
        }

        $nextRun = $this->getNextRun($rule->trigger_schedule);

        if (!$nextRun) {
            return null;
        }

        try {
            return AutomationSchedule::create([
                'rule_id' => $rule->id,
                'scheduled_for' => $nextRun,
                'status' => AutomationSchedule::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $rule->schedules()->pending()->first();
        }
    }

    /**
     * Run every schedule whose time has come, in every organization.
     *
     * The sweep runs from the framework scheduler, outside any request, so
     * there is no tenant to read it as: a schedule carries none of its own and
     * each rule names the organization its records are read for.
     *
     * One rule failing is that rule's failure. The sweep records it against
     * the schedule it belongs to and moves on to the next one.
     *
     * @return list<array<string, mixed>>
     */
    public function processScheduledRules(): array
    {
        $results = $this->recoverAbandonedEntries();

        foreach (AutomationSchedule::due()->orderBy('scheduled_for')->get() as $schedule) {
            try {
                $result = $this->processSchedule($schedule);
            } catch (\Throwable $e) {
                // Taking an entry has its own ways of failing, before there is
                // a claim to record anything against: a lock wait, a dropped
                // connection. It is still one entry's failure.
                Log::error('Scheduled automation could not be taken', [
                    'schedule_id' => $schedule->id,
                    'rule_id' => $schedule->rule_id,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'schedule_id' => $schedule->id,
                    'rule_id' => $schedule->rule_id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Put right the entries a sweep took and never came back to.
     *
     * A process killed between claiming an entry and recording its outcome
     * leaves that entry running, and a running entry is one nothing looks at
     * again: the rule would hold a claim nobody owns and never book another
     * run. Such an entry is recorded as failed rather than run again, since
     * the run it belonged to may already have sent half its email, and the
     * rule is booked forward so it keeps its cadence.
     *
     * @return list<array<string, mixed>>
     */
    private function recoverAbandonedEntries(): array
    {
        $abandonedSince = now()->subMinutes(self::ABANDONED_AFTER_MINUTES);
        $results = [];

        $abandoned = AutomationSchedule::running()
            ->where('updated_at', '<=', $abandonedSince)
            ->get();

        foreach ($abandoned as $schedule) {
            // Under the same lock the sweep claims with, so one recovery wins.
            $recovered = DB::transaction(function () use ($schedule, $abandonedSince) {
                $locked = AutomationSchedule::lockForUpdate()->find($schedule->getKey());

                if (!$locked || !$locked->isRunning() || $locked->updated_at->gt($abandonedSince)) {
                    return null;
                }

                $locked->markAsFailed();

                return $locked;
            });

            if (!$recovered) {
                continue;
            }

            Log::warning('Scheduled automation was abandoned mid-run', [
                'schedule_id' => $recovered->id,
                'rule_id' => $recovered->rule_id,
            ]);

            $rule = AutomationRule::withoutGlobalScope('organization')->find($recovered->rule_id);

            if ($rule && $rule->isActive()) {
                $this->create($rule);
            }

            $results[] = [
                'schedule_id' => $recovered->id,
                'rule_id' => $recovered->rule_id,
                'status' => 'failed',
                'error' => 'Abandoned mid-run and not run again',
            ];
        }

        return $results;
    }

    /**
     * Run one due schedule, or nothing if another sweep already took it.
     *
     * @return array<string, mixed>|null
     */
    public function processSchedule(AutomationSchedule $schedule): ?array
    {
        $claimed = $this->claim($schedule->getKey());

        if (!$claimed) {
            return null;
        }

        // Read without the tenant scope: the sweep has no authenticated user to
        // scope by, and the rule's own organization is what the run belongs to.
        $rule = AutomationRule::withoutGlobalScope('organization')->find($claimed->rule_id);

        if (!$rule || !$rule->isActive()) {
            // Nothing will ever run this entry, and a rule that is switched off
            // books no next run, so the entry goes rather than sitting behind
            // every future sweep.
            $claimed->delete();

            return [
                'schedule_id' => $claimed->id,
                'rule_id' => $rule?->id,
                'status' => 'skipped',
                'reason' => 'Rule not active or not found',
            ];
        }

        try {
            $this->executeScheduledRule($rule);

            $claimed->markAsCompleted();
            $this->create($rule);

            return [
                'schedule_id' => $claimed->id,
                'rule_id' => $rule->id,
                'status' => 'completed',
            ];
        } catch (\Throwable $e) {
            Log::error('Scheduled automation failed', [
                'schedule_id' => $claimed->id,
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);

            $claimed->markAsFailed();

            // A run that failed is not a reason to stop running the rule.
            $this->create($rule);

            return [
                'schedule_id' => $claimed->id,
                'rule_id' => $rule->id,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Take a due schedule for this sweep, or return null if it is already taken.
     *
     * Two sweeps overlapping on a cron tick read the same due entries, so the
     * entry is re-read under a row lock and moved off pending before anything
     * runs: whichever sweep wins the lock owns the run, and the other finds it
     * no longer due. The claim is committed before the rule runs, so a rule
     * that fails leaves the entry claimed rather than back in the queue for
     * the next tick a minute later.
     */
    private function claim(int $scheduleId): ?AutomationSchedule
    {
        return DB::transaction(function () use ($scheduleId) {
            $schedule = AutomationSchedule::lockForUpdate()->find($scheduleId);

            if (!$schedule || !$schedule->isDue()) {
                return null;
            }

            $schedule->markAsRunning();

            return $schedule;
        });
    }

    /**
     * Execute a scheduled rule against its entity query set.
     */
    protected function executeScheduledRule(AutomationRule $rule): void
    {
        $entityClass = $this->runner->entityClassFor($rule->entity_type);

        if (!$entityClass || !class_exists($entityClass)) {
            Log::warning("Unknown entity type for automation rule", [
                'rule_id' => $rule->id,
                'entity_type' => $rule->entity_type,
            ]);
            return;
        }

        // The organization is the rule's own rather than an authenticated
        // user's, and read in pages so the set a rule sweeps stays off the
        // heap whole.
        $entityClass::query()
            ->withoutGlobalScope('organization')
            ->where('organization_id', $rule->organization_id)
            ->chunkById(self::ENTITIES_PER_CHUNK, function ($entities) use ($rule): void {
                foreach ($entities as $entity) {
                    $this->runAgainst($rule, $entity);
                }
            });
    }

    /**
     * Run the rule against one record.
     *
     * A record whose actions fail is logged and stepped over: one bad record
     * is not the rule's failure and must not cost the rest of the set their
     * run. Conditions that cannot be evaluated are raised to the caller, which
     * records the failure against the schedule — a rule that cannot be read is
     * broken for every record, not this one.
     */
    private function runAgainst(AutomationRule $rule, Model $entity): void
    {
        if (!$this->runner->evaluate($rule, $entity)) {
            return;
        }

        try {
            $this->runner->executeActions($rule, $entity);
        } catch (\Throwable $e) {
            Log::warning("Scheduled rule action failed for entity", [
                'rule_id' => $rule->id,
                'entity_id' => $entity->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate the next run time from a cron expression.
     */
    public function getNextRun(string $cronExpression, ?Carbon $from = null): ?Carbon
    {
        try {
            $cron = new CronExpression($cronExpression);
            $nextRun = Carbon::instance($cron->getNextRunDate($from?->toDateTime() ?? 'now'));
            return $nextRun;
        } catch (\Throwable $e) {
            Log::warning('Invalid cron expression', [
                'expression' => $cronExpression,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
