<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Analytics\UserActivityLog;
use App\Models\Analytics\UserClusterAssignment;
use App\Models\Analytics\UserFeatureUsage;
use App\Models\Analytics\UserSessionExtended;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads of the organization's recorded user activity: requests, feature
 * usage, sessions and cluster assignments.
 *
 * A filter applies when its key is present in the filters array.
 */
class UserAnalyticsService
{
    private const PER_PAGE = 50;

    /**
     * @param  array{user_id?: int, module?: string, from_date?: string, to_date?: string}  $filters
     */
    public function paginateActivity(int $organizationId, array $filters): LengthAwarePaginator
    {
        return UserActivityLog::where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->when(array_key_exists('user_id', $filters), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(array_key_exists('module', $filters), fn ($query) => $query->where('module', $filters['module']))
            ->when(array_key_exists('from_date', $filters), fn ($query) => $query->where('created_at', '>=', $filters['from_date']))
            ->when(array_key_exists('to_date', $filters), fn ($query) => $query->where('created_at', '<=', $filters['to_date'].' 23:59:59'))
            ->paginate(self::PER_PAGE);
    }

    /**
     * @param  array{user_id?: int, module?: string, from_date?: string, to_date?: string}  $filters
     */
    public function paginateFeatureUsage(int $organizationId, array $filters): LengthAwarePaginator
    {
        return UserFeatureUsage::where('organization_id', $organizationId)
            ->orderByDesc('usage_date')
            ->when(array_key_exists('module', $filters), fn ($query) => $query->where('module', $filters['module']))
            ->when(array_key_exists('from_date', $filters), fn ($query) => $query->where('usage_date', '>=', $filters['from_date']))
            ->when(array_key_exists('to_date', $filters), fn ($query) => $query->where('usage_date', '<=', $filters['to_date']))
            ->when(array_key_exists('user_id', $filters), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->paginate(self::PER_PAGE);
    }

    /**
     * @param  array{user_id?: int}  $filters
     */
    public function paginateSessions(int $organizationId, array $filters): LengthAwarePaginator
    {
        return UserSessionExtended::where('organization_id', $organizationId)
            ->orderByDesc('started_at')
            ->when(array_key_exists('user_id', $filters), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->paginate(self::PER_PAGE);
    }

    /**
     * How many distinct users sit in each cluster, largest cluster first.
     */
    public function clusterDistribution(int $organizationId): Collection
    {
        return UserClusterAssignment::where('organization_id', $organizationId)
            ->selectRaw('cluster_name, COUNT(DISTINCT user_id) as user_count')
            ->groupBy('cluster_name')
            ->orderByDesc('user_count')
            ->get();
    }

    /**
     * A user of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findUser(int $organizationId, int $userId): User
    {
        return User::where('id', $userId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();
    }

    /**
     * The user's cluster assignments, latest first.
     */
    public function clusterAssignments(User $user): Collection
    {
        return UserClusterAssignment::where('user_id', $user->id)
            ->orderByDesc('assigned_at')
            ->get(['cluster_name', 'algorithm', 'confidence', 'assigned_at', 'expires_at']);
    }
}
