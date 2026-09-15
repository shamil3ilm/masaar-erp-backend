<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CapaAction;
use App\Models\Manufacturing\CapaEffectivenessReview;
use App\Models\Manufacturing\CapaRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Corrective and preventive action records, their actions and effectiveness
 * reviews. Actions and reviews have no organization column; they are reached
 * only through a CAPA record of the organization.
 */
class CapaService
{
    public function list(int $organizationId): LengthAwarePaginator
    {
        return CapaRecord::where('organization_id', $organizationId)
            ->with('owner')
            ->paginate(20);
    }

    public function create(int $organizationId, array $data): CapaRecord
    {
        return CapaRecord::create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * One of the organization's CAPA records; a missing id is a 404.
     *
     * @param  array<int, string>  $with
     */
    public function find(int $organizationId, int $id, array $with = []): CapaRecord
    {
        return CapaRecord::where('organization_id', $organizationId)
            ->with($with)
            ->findOrFail($id);
    }

    public function addAction(CapaRecord $capa, array $data): CapaAction
    {
        return $capa->actions()->create([...$data, 'uuid' => (string) Str::uuid()]);
    }

    /**
     * Complete one of the CAPA's actions; an action of another CAPA is a 404.
     */
    public function completeAction(CapaRecord $capa, int $actionId, ?string $notes): CapaAction
    {
        $action = $capa->actions()->findOrFail($actionId);

        $action->update([
            'status'           => 'completed',
            'completed_date'   => now()->toDateString(),
            'completion_notes' => $notes,
        ]);

        return $action;
    }

    /**
     * Record an effectiveness review; an effective one closes the CAPA.
     *
     * The review and the close run on the locked CAPA in one transaction, so a
     * review is never kept for a CAPA that failed to close.
     */
    public function addEffectivenessReview(CapaRecord $capa, array $data, int $reviewerId): CapaEffectivenessReview
    {
        return $capa->lockForTransition(function (CapaRecord $capa) use ($data, $reviewerId): CapaEffectivenessReview {
            $review = CapaEffectivenessReview::create([
                ...$data,
                'uuid'           => (string) Str::uuid(),
                'capa_record_id' => $capa->id,
                'reviewed_by_id' => $reviewerId,
            ]);

            if ($data['effectiveness'] === 'effective') {
                $capa->update(['status' => 'closed', 'actual_close_date' => now()->toDateString()]);
            }

            return $review;
        });
    }
}
