<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Defining the organization's campaigns: create, edit, activate, pause and
 * delete. Sending them to users is CampaignService's job.
 */
class CampaignManagementService
{
    /**
     * The organization's campaigns with their send counts, newest first.
     */
    public function paginate(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->withCount('sends')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A campaign of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(int $organizationId, string $campaignId, bool $withSendCount = false): Campaign
    {
        return Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->when($withSendCount, fn ($query) => $query->withCount('sends'))
            ->where('id', $campaignId)
            ->firstOrFail();
    }

    /**
     * Create a draft campaign. The target segment must already be validated
     * as one of the organization's.
     */
    public function create(int $organizationId, int $userId, array $data): Campaign
    {
        return Campaign::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger_event' => $data['trigger_event'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'target_segment_id' => $data['target_segment_id'] ?? null,
            'actions' => $data['actions'],
            'status' => Campaign::STATUS_DRAFT,
            'schedule_type' => $data['schedule_type'] ?? 'immediate',
            'delay_minutes' => $data['delay_minutes'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'max_sends_per_user' => $data['max_sends_per_user'] ?? 1,
            'created_by' => $userId,
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);

        return $campaign->fresh();
    }

    public function activate(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => Campaign::STATUS_ACTIVE]);

        return $campaign->fresh();
    }

    public function pause(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => Campaign::STATUS_PAUSED]);

        return $campaign->fresh();
    }

    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }
}
