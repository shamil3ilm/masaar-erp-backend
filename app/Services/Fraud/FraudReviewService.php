<?php

declare(strict_types=1);

namespace App\Services\Fraud;

use App\Models\Fraud\FraudAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The fraud alerts an organization's reviewers work through.
 *
 * An alert names the user whose activity raised it and, once reviewed, the
 * reviewer; the reviewer is shown by id and name only.
 */
final class FraudReviewService
{
    private const ALERT_RELATIONS = ['rule', 'user', 'reviewer:id,name'];

    /**
     * The organization's alerts, latest first.
     *
     * @param  array{status?: string, severity?: string, entity_type?: string, from_date?: string, to_date?: string}  $filters
     */
    public function paginateAlerts(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return FraudAlert::with(self::ALERT_RELATIONS)
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['severity']), fn ($q) => $q->where('severity', $filters['severity']))
            ->when(isset($filters['entity_type']), fn ($q) => $q->where('entity_type', $filters['entity_type']))
            ->when(isset($filters['from_date']), fn ($q) => $q->where('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($q) => $q->where('created_at', '<=', $filters['to_date']))
            ->paginate($perPage);
    }

    public function findAlert(int $organizationId, int $id): FraudAlert
    {
        return FraudAlert::with(self::ALERT_RELATIONS)
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    /**
     * Records a reviewer's decision on the locked alert, so two reviewers
     * deciding at once leave one complete decision rather than a mix of both.
     */
    public function review(int $organizationId, int $id, string $status, ?string $notes, int $reviewerId): FraudAlert
    {
        return DB::transaction(function () use ($organizationId, $id, $status, $notes, $reviewerId): FraudAlert {
            $alert = FraudAlert::where('organization_id', $organizationId)
                ->lockForUpdate()
                ->findOrFail($id);

            $alert->fill([
                'status' => $status,
                'reviewer_notes' => $notes,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ])->save();

            return $alert->fresh(['rule', 'reviewer:id,name']);
        });
    }
}
