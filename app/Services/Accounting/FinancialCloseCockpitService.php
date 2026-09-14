<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FinancialClosePeriod;
use App\Models\Accounting\FinancialCloseTask;
use App\Models\Accounting\FinancialCloseTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FinancialCloseCockpitService
{
    /**
     * List close templates with their tasks, by name, optionally only active ones.
     */
    public function listTemplates(bool $activeOnly): Collection
    {
        return FinancialCloseTemplate::with('tasks')
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a close template and its tasks in one transaction, so a failing
     * task leaves no half-built template behind. A task without sort_order
     * takes its position in the list.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTemplate(int $organizationId, array $data): FinancialCloseTemplate
    {
        return DB::transaction(function () use ($organizationId, $data): FinancialCloseTemplate {
            $template = FinancialCloseTemplate::create([
                'organization_id' => $organizationId,
                'name'            => $data['name'],
                'description'     => $data['description'] ?? null,
                'close_type'      => $data['close_type'],
                'is_active'       => $data['is_active'] ?? true,
            ]);

            foreach ($data['tasks'] ?? [] as $index => $taskData) {
                $template->tasks()->create([
                    'task_name'                => $taskData['task_name'],
                    'description'              => $taskData['description'] ?? null,
                    'task_type'                => $taskData['task_type'],
                    'sort_order'               => $taskData['sort_order'] ?? $index,
                    'estimated_duration_hours' => $taskData['estimated_duration_hours'] ?? null,
                    'required_role'            => $taskData['required_role'] ?? null,
                ]);
            }

            return $template->load('tasks');
        });
    }

    /**
     * Page through close periods, latest year and period first. A fiscal year
     * or status filter applies only when its value is truthy.
     */
    public function paginatePeriods(mixed $fiscalYear, mixed $status, int $perPage): LengthAwarePaginator
    {
        return FinancialClosePeriod::query()
            ->when($fiscalYear, fn ($q, $y) => $q->where('fiscal_year', (int) $y))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('fiscal_year')
            ->orderByDesc('period')
            ->paginate($perPage);
    }

    /**
     * Find one of the organisation's close periods; a missing or foreign id is a 404.
     *
     * @param  array<int, string>  $relations
     */
    public function findPeriod(int $id, array $relations = []): FinancialClosePeriod
    {
        return FinancialClosePeriod::with($relations)->findOrFail($id);
    }

    /**
     * Find a close task of one of the organisation's periods.
     *
     * Tasks carry no organization_id, so the organisation scope is reached
     * through the period: a task of another organisation's period is a 404.
     * A soft-deleted period still owns its tasks.
     */
    public function findTask(int $taskId): FinancialCloseTask
    {
        return FinancialCloseTask::whereHas('period', fn ($q) => $q->withTrashed())
            ->findOrFail($taskId);
    }

    /**
     * Create a financial close period and instantiate tasks from the template (if given).
     */
    public function createPeriod(array $data): FinancialClosePeriod
    {
        return DB::transaction(function () use ($data): FinancialClosePeriod {
            $period = FinancialClosePeriod::create(array_merge($data, [
                'status' => FinancialClosePeriod::STATUS_OPEN,
                'opened_at' => now(),
            ]));

            $templateId = $data['financial_close_template_id'] ?? null;

            if ($templateId) {
                $template = FinancialCloseTemplate::with('tasks')->find($templateId);

                if ($template) {
                    foreach ($template->tasks as $tmplTask) {
                        FinancialCloseTask::create([
                            'financial_close_period_id' => $period->id,
                            'template_task_id' => $tmplTask->id,
                            'task_name' => $tmplTask->task_name,
                            'description' => $tmplTask->description,
                            'task_type' => $tmplTask->task_type,
                            'status' => FinancialCloseTask::STATUS_PENDING,
                            'sort_order' => $tmplTask->sort_order,
                        ]);
                    }
                }
            }

            return $period->load('tasks');
        });
    }

    /**
     * Start a task (transition pending → in_progress).
     */
    public function startTask(FinancialCloseTask $task, int $userId): void
    {
        if ($task->status !== FinancialCloseTask::STATUS_PENDING) {
            throw new InvalidArgumentException(
                "Task [{$task->task_name}] cannot be started (current status: {$task->status})."
            );
        }

        if (! $this->canStartTask($task)) {
            throw new RuntimeException(
                "Task [{$task->task_name}] has unresolved dependencies."
            );
        }

        $task->update([
            'status' => FinancialCloseTask::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'assigned_to' => $userId,
        ]);
    }

    /**
     * Complete a task (transition in_progress → completed).
     */
    public function completeTask(FinancialCloseTask $task, int $userId, string $notes = ''): void
    {
        if (! in_array($task->status, [
            FinancialCloseTask::STATUS_IN_PROGRESS,
            FinancialCloseTask::STATUS_PENDING,
        ], true)) {
            throw new InvalidArgumentException(
                "Task [{$task->task_name}] cannot be completed (current status: {$task->status})."
            );
        }

        $task->update([
            'status' => FinancialCloseTask::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'notes' => $notes ?: $task->notes,
        ]);
    }

    /**
     * Check whether all dependency tasks are completed.
     */
    public function canStartTask(FinancialCloseTask $task): bool
    {
        return $task->dependencies()
            ->where('status', '!=', FinancialCloseTask::STATUS_COMPLETED)
            ->doesntExist();
    }

    /**
     * Skip a task (must be pending or blocked).
     */
    public function skipTask(FinancialCloseTask $task, int $userId, string $reason = ''): void
    {
        if (! in_array($task->status, [
            FinancialCloseTask::STATUS_PENDING,
            FinancialCloseTask::STATUS_BLOCKED,
        ], true)) {
            throw new InvalidArgumentException(
                "Task [{$task->task_name}] cannot be skipped (current status: {$task->status})."
            );
        }

        $task->update([
            'status' => FinancialCloseTask::STATUS_SKIPPED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'notes' => $reason ?: $task->notes,
        ]);
    }

    /**
     * Assign a task to a user.
     */
    public function assignTask(FinancialCloseTask $task, int $assigneeId): void
    {
        if ($task->status === FinancialCloseTask::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Cannot reassign a completed task.');
        }

        $task->update(['assigned_to' => $assigneeId]);
    }

    /**
     * Record a formal sign-off on a closed period (CFO / controller approval).
     */
    public function signOff(FinancialClosePeriod $period, int $userId, string $notes = ''): void
    {
        if ($period->status !== FinancialClosePeriod::STATUS_CLOSED) {
            throw new InvalidArgumentException('Period must be closed before it can be signed off.');
        }

        if ($period->signed_off_by !== null) {
            throw new InvalidArgumentException('Period has already been signed off.');
        }

        $period->update([
            'signed_off_by' => $userId,
            'signed_off_at' => now(),
            'sign_off_notes' => $notes,
        ]);
    }

    /**
     * Get progress summary for a close period.
     *
     * @return array{total: int, pending: int, in_progress: int, completed: int, blocked: int, skipped: int, percent_complete: float}
     */
    public function getPeriodProgress(FinancialClosePeriod $period): array
    {
        // reorder() drops the relation's sort_order, which is not in the
        // GROUP BY and which a strict MySQL refuses to order by.
        $counts = $period->tasks()
            ->reorder()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = (int) array_sum($counts);
        $completed = (int) ($counts[FinancialCloseTask::STATUS_COMPLETED] ?? 0);
        $percent = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'pending' => (int) ($counts[FinancialCloseTask::STATUS_PENDING] ?? 0),
            'in_progress' => (int) ($counts[FinancialCloseTask::STATUS_IN_PROGRESS] ?? 0),
            'completed' => $completed,
            'blocked' => (int) ($counts[FinancialCloseTask::STATUS_BLOCKED] ?? 0),
            'skipped' => (int) ($counts[FinancialCloseTask::STATUS_SKIPPED] ?? 0),
            'percent_complete' => $percent,
        ];
    }

    /**
     * Schedule a close period with automatic due dates per task.
     *
     * Tasks are given due dates counted back from the period's due_date, spaced by their
     * estimated_duration_hours or sort_order position. Tasks later in sort_order get later dates.
     */
    public function schedulePeriod(array $data): FinancialClosePeriod
    {
        return DB::transaction(function () use ($data): FinancialClosePeriod {
            $period = $this->createPeriod($data);

            $dueDate = isset($data['due_date'])
                ? Carbon::parse($data['due_date'])
                : now()->endOfMonth();

            $tasks = $period->tasks()->orderBy('sort_order')->get();
            $taskCount = $tasks->count();

            foreach ($tasks as $i => $task) {
                $daysBack = $taskCount - $i - 1;
                $taskDue = $dueDate->copy()->subDays($daysBack);

                $task->update(['due_date' => $taskDue->toDateString()]);
            }

            return $period->load('tasks');
        });
    }

    /**
     * After completing or skipping a task, unblock dependent tasks whose
     * remaining dependencies are all now completed/skipped.
     *
     * Marks tasks BLOCKED when a prerequisite was failed/skipped.
     */
    public function propagateBlocked(FinancialCloseTask $completedTask): void
    {
        $dependents = $completedTask->dependents()->with('dependencies')->get();

        foreach ($dependents as $dependent) {
            if ($dependent->status !== FinancialCloseTask::STATUS_BLOCKED
                && $dependent->status !== FinancialCloseTask::STATUS_PENDING) {
                continue;
            }

            $allDone = $dependent->dependencies->every(
                fn ($dep) => in_array($dep->status, [
                    FinancialCloseTask::STATUS_COMPLETED,
                    FinancialCloseTask::STATUS_SKIPPED,
                ], true)
            );

            if ($allDone && $dependent->status === FinancialCloseTask::STATUS_BLOCKED) {
                $dependent->update(['status' => FinancialCloseTask::STATUS_PENDING]);
            }
        }
    }

    /**
     * Block all pending tasks that depend on the given task (e.g. when a critical task fails).
     */
    public function blockDependents(FinancialCloseTask $failedTask): void
    {
        $dependents = $failedTask->dependents()
            ->whereIn('status', [FinancialCloseTask::STATUS_PENDING])
            ->get();

        foreach ($dependents as $dependent) {
            $dependent->update(['status' => FinancialCloseTask::STATUS_BLOCKED]);
        }
    }

    /**
     * Close a financial period (all tasks must be completed or skipped).
     */
    public function closePeriod(FinancialClosePeriod $period, int $userId): void
    {
        if ($period->status === FinancialClosePeriod::STATUS_CLOSED) {
            throw new InvalidArgumentException('Period is already closed.');
        }

        $blockerCount = $period->tasks()
            ->whereIn('status', [
                FinancialCloseTask::STATUS_PENDING,
                FinancialCloseTask::STATUS_IN_PROGRESS,
                FinancialCloseTask::STATUS_BLOCKED,
            ])
            ->count();

        if ($blockerCount > 0) {
            throw new RuntimeException(
                "{$blockerCount} task(s) are still open. Complete or skip them before closing the period."
            );
        }

        $period->update([
            'status' => FinancialClosePeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);
    }
}
