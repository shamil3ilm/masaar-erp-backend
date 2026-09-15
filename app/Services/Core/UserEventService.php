<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Jobs\TrackUserEvent;
use App\Models\Core\UserEvent;
use App\Services\Campaign\CampaignService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records user events and reads them back. Events carry the IP address and
 * browser of whoever caused them, so a reader is narrowed to one user's
 * events unless the caller decided they may see everyone's.
 */
class UserEventService
{
    /** Events per page in the event list. */
    private const PER_PAGE = 50;

    /** Days the summary looks back. */
    private const SUMMARY_DAYS = 30;

    /**
     * Events of the organization, latest first, narrowed to one user when
     * $onlyUserId is set and by the filters that are set.
     *
     * @param  array{event_type?: ?string, user_id?: ?int, from?: ?string, to?: ?string}  $filters
     */
    public function list(int $organizationId, ?int $onlyUserId, array $filters): LengthAwarePaginator
    {
        return UserEvent::query()
            ->where('organization_id', $organizationId)
            ->when($onlyUserId !== null, fn ($query) => $query->where('user_id', $onlyUserId))
            ->orderByDesc('created_at')
            ->when(isset($filters['event_type']), fn ($query) => $query->where('event_type', $filters['event_type']))
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->paginate(self::PER_PAGE);
    }

    /**
     * Event counts by type and day over the last 30 days, latest day first,
     * narrowed to one user when $onlyUserId is set.
     *
     * @return Collection<int, object{event_type: string, date: string, count: int}>
     */
    public function summary(int $organizationId, ?int $onlyUserId): Collection
    {
        return DB::table('user_events')
            ->select([
                'event_type',
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
            ])
            ->where('organization_id', $organizationId)
            ->when($onlyUserId !== null, fn ($query) => $query->where('user_id', $onlyUserId))
            ->where('created_at', '>=', now()->subDays(self::SUMMARY_DAYS))
            ->groupBy('event_type', DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Dispatch an async job to record a user event.
     *
     * Keeping the dispatch asynchronous ensures that event tracking never
     * adds latency to the request that triggered it.
     */
    public function track(
        string $eventType,
        array $payload = [],
        ?int $userId = null,
        ?int $organizationId = null,
        ?Request $request = null
    ): void {
        TrackUserEvent::dispatch(
            $eventType,
            $payload,
            $userId,
            $organizationId,
            $request?->ip(),
            $request?->userAgent(),
        )->afterCommit();

        if ($organizationId !== null) {
            CampaignService::triggerEventAsync(
                $eventType,
                $userId ?? 0,
                $organizationId,
            );
        }
    }
}
