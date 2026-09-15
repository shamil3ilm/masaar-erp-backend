<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\CRM\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Calls, meetings, tasks and notes logged against the organization's CRM records.
 *
 * A completed or cancelled activity is final: it can no longer be edited or
 * completed. Changes re-check that on the locked row.
 */
class ActivityService
{
    private const RELATIONS = ['creator:id,name', 'assignee:id,name'];

    /**
     * The organization's activities, latest start first.
     *
     * @param  array{activity_type?: ?string, status?: ?string, priority?: ?string, assigned_to?: mixed,
     *     related_type?: ?string, related_id?: mixed, search?: ?string}  $filters
     */
    public function paginate(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Activity::with(self::RELATIONS)
            ->where('organization_id', $organizationId)
            ->when($filters['activity_type'] ?? null, fn ($query, $type) => $query->where('activity_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['related_type'] ?? null, function ($query, $relatedType) use ($filters) {
                $query->where('related_type', $relatedType);

                if (! empty($filters['related_id'])) {
                    $query->where('related_id', $filters['related_id']);
                }
            })
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('subject', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->orderByDesc('start_datetime')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * An activity of the organization with its creator and assignee, or null.
     */
    public function find(int $organizationId, int $activityId): ?Activity
    {
        return Activity::with(self::RELATIONS)
            ->where('organization_id', $organizationId)
            ->find($activityId);
    }

    /**
     * Log a planned activity created by the user.
     */
    public function create(int $organizationId, int $userId, array $data): Activity
    {
        return Activity::create([
            ...$data,
            'organization_id' => $organizationId,
            'status' => Activity::STATUS_PLANNED,
            'created_by' => $userId,
        ])->load(self::RELATIONS);
    }

    /**
     * @throws BusinessRuleException when the activity is completed or cancelled
     */
    public function assertEditable(Activity $activity): void
    {
        if ($activity->isCompleted()) {
            throw new BusinessRuleException('Cannot update a completed activity', 'ACTIVITY_COMPLETED');
        }

        if ($activity->isCancelled()) {
            throw new BusinessRuleException('Cannot update a cancelled activity', 'ACTIVITY_CANCELLED');
        }
    }

    /**
     * @throws BusinessRuleException when the activity is completed or cancelled
     */
    public function assertCompletable(Activity $activity): void
    {
        if ($activity->isCompleted()) {
            throw new BusinessRuleException('Activity is already completed', 'ALREADY_COMPLETED');
        }

        if ($activity->isCancelled()) {
            throw new BusinessRuleException('Cannot complete a cancelled activity', 'ACTIVITY_CANCELLED');
        }
    }

    /**
     * @throws BusinessRuleException when the activity became final
     */
    public function update(Activity $activity, array $data): Activity
    {
        $activity->lockForTransition(function (Activity $locked) use ($data) {
            $this->assertEditable($locked);
            $locked->update($data);
        });

        return $activity->fresh()->load(self::RELATIONS);
    }

    /**
     * @throws BusinessRuleException when the activity became final
     */
    public function complete(Activity $activity, ?string $outcome): Activity
    {
        $activity->lockForTransition(function (Activity $locked) use ($outcome) {
            $this->assertCompletable($locked);
            $locked->complete($outcome);
        });

        return $activity->fresh()->load(self::RELATIONS);
    }
}
