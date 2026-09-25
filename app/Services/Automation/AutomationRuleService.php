<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationRuleLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the organization's automation rules: reads them, writes them, and
 * keeps a scheduled rule's pending schedule in step with its definition.
 *
 * Evaluating a rule and executing its actions is AutomationRuleRunner's work.
 */
class AutomationRuleService
{
    public function __construct(
        private AutomationScheduleService $scheduleService
    ) {}

    /**
     * The organization's rules.
     *
     * @param  array{trigger_type?: ?string, entity_type?: ?string, is_active?: ?string, trigger_event?: ?string, search?: ?string}  $filters
     */
    public function paginate(int $organizationId, array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return AutomationRule::with(['creator'])
            ->where('organization_id', $organizationId)
            ->when($filters['trigger_type'] ?? null, fn ($query, $type) => $query->forTriggerType($type))
            ->when($filters['entity_type'] ?? null, fn ($query, $type) => $query->forEntityType($type))
            ->when(($filters['is_active'] ?? null) !== null, fn ($query) => $filters['is_active'] === 'true'
                ? $query->active()
                : $query->inactive())
            ->when($filters['trigger_event'] ?? null, fn ($query, $event) => $query->forTriggerEvent($event))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Delete a rule together with its execution logs and schedules.
     */
    public function delete(AutomationRule $rule): void
    {
        DB::transaction(function () use ($rule) {
            $rule->logs()->delete();
            $rule->schedules()->delete();
            $rule->delete();
        });
    }

    /**
     * The rule's execution logs, newest first.
     */
    public function paginateLogs(AutomationRule $rule, ?string $status, ?int $days, int $perPage): LengthAwarePaginator
    {
        return AutomationRuleLog::forRule($rule->id)
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($days, fn ($query, $value) => $query->recent($value))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new automation rule.
     */
    public function create(array $data, int $userId): AutomationRule
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['is_active'] = $data['is_active'] ?? true;
            $data['priority'] = $data['priority'] ?? 0;
            $data['execution_count'] = 0;
            $data['created_by'] = $data['created_by'] ?? $userId;

            $rule = AutomationRule::create($data);

            // If it's a scheduled rule, create the initial schedule
            if ($rule->isScheduled() && !empty($rule->trigger_schedule)) {
                $this->scheduleService->create($rule);
            }

            return $rule;
        });
    }

    /**
     * Update an existing automation rule.
     */
    public function update(AutomationRule $rule, array $data): AutomationRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $rule->update($data);

            // If the schedule changed, update scheduled jobs
            if (isset($data['trigger_schedule']) && $rule->isScheduled()) {
                $rule->schedules()->pending()->delete();
                $this->scheduleService->create($rule);
            }

            return $rule->fresh();
        });
    }

    /**
     * Activate an automation rule.
     */
    public function activate(AutomationRule $rule): AutomationRule
    {
        return DB::transaction(function () use ($rule) {
            $rule->update(['is_active' => true]);

            // If it's a scheduled rule, create the next schedule
            if ($rule->isScheduled()) {
                $this->scheduleService->create($rule);
            }

            return $rule->fresh();
        });
    }

    /**
     * Deactivate an automation rule.
     */
    public function deactivate(AutomationRule $rule): AutomationRule
    {
        return DB::transaction(function () use ($rule) {
            $rule->update(['is_active' => false]);

            // Cancel any pending schedules
            $rule->schedules()->pending()->delete();

            return $rule->fresh();
        });
    }
}
