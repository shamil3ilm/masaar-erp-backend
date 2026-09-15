<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Core\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The audit trail of super-admin impersonation sessions.
 *
 * Sessions are recorded in the impersonated user's organization, and the
 * audit spans all of them, so the organization scope is lifted on purpose.
 * Callers must already have confirmed the super-admin role.
 */
class ImpersonationAuditService
{
    private const PEOPLE = ['user:id,name,email', 'impersonatedBy:id,name,email'];

    /**
     * Started sessions, newest first.
     */
    public function paginateSessions(int $perPage): LengthAwarePaginator
    {
        return ActivityLog::withoutGlobalScopes()
            ->where('action', ActivityLog::ACTION_IMPERSONATION_STARTED)
            ->with(self::PEOPLE)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A session's start, end, reason and the actions taken during it, or null
     * when no session with that id was started.
     */
    public function sessionDetail(string $sessionId): ?array
    {
        $actions = ActivityLog::withoutGlobalScopes()
            ->where('impersonation_session_id', $sessionId)
            ->with(self::PEOPLE)
            ->orderBy('created_at')
            ->get();

        $start = $actions->firstWhere('action', ActivityLog::ACTION_IMPERSONATION_STARTED);

        if ($start === null) {
            return null;
        }

        $end = $actions->firstWhere('action', ActivityLog::ACTION_IMPERSONATION_ENDED);

        return [
            'session_id' => $sessionId,
            'started_at' => $start->created_at,
            'ended_at' => $end?->created_at,
            'reason' => $start->metadata['reason'] ?? null,
            'admin' => $start->impersonatedBy,
            'target_user' => $start->user,
            'duration_minutes' => $end ? $start->created_at->diffInMinutes($end->created_at) : null,
            'actions' => $actions
                ->whereNotIn('action', [
                    ActivityLog::ACTION_IMPERSONATION_STARTED,
                    ActivityLog::ACTION_IMPERSONATION_ENDED,
                ])
                ->values(),
        ];
    }
}
