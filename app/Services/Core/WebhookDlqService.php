<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\WebhookDlqEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookDlqService
{
    /**
     * Dead-letter entries of the organization, most recently failed first, 20
     * to a page, narrowed to one status when given.
     */
    public function list(int $orgId, ?string $status): LengthAwarePaginator
    {
        return WebhookDlqEntry::where('organization_id', $orgId)
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('last_failed_at', 'desc')
            ->paginate(20);
    }

    /**
     * An entry of the organization by id; another organization's entry is not found.
     */
    public function findForOrganization(int $orgId, int $id): WebhookDlqEntry
    {
        return WebhookDlqEntry::where('organization_id', $orgId)->findOrFail($id);
    }

    /**
     * Replays the organization's entries among $ids in one transaction and
     * returns how many there were; ids of other organizations are ignored.
     *
     * @param  list<int>  $ids
     */
    public function replayMany(int $orgId, array $ids, int $userId): int
    {
        return DB::transaction(function () use ($orgId, $ids, $userId): int {
            $entries = WebhookDlqEntry::where('organization_id', $orgId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $this->replay($entry, $userId);
            }

            return $entries->count();
        });
    }

    public function delete(WebhookDlqEntry $entry): void
    {
        $entry->delete();
    }

    public function recordFailure(int $orgId, int $webhookId, string $event, array $payload, string $error): WebhookDlqEntry
    {
        $existing = WebhookDlqEntry::where('webhook_id', $webhookId)
            ->where('event_type', $event)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existing->increment('failure_count');
            $existing->update([
                'last_failed_at' => now(),
                'last_error'     => $error,
                'next_retry_at'  => $this->calculateNextRetry($existing->failure_count),
                'status'         => $existing->failure_count >= 5 ? 'dead' : 'pending',
            ]);
            return $existing;
        }

        return WebhookDlqEntry::create([
            'uuid'             => Str::uuid(),
            'organization_id'  => $orgId,
            'webhook_id'       => $webhookId,
            'event_type'       => $event,
            'payload'          => $payload,
            'failure_count'    => 1,
            'first_failed_at'  => now(),
            'last_failed_at'   => now(),
            'last_error'       => $error,
            'next_retry_at'    => now()->addMinutes(5),
            'status'           => 'pending',
        ]);
    }

    public function scheduleRetry(WebhookDlqEntry $entry): void
    {
        $entry->update([
            'status'        => 'retrying',
            'next_retry_at' => $this->calculateNextRetry($entry->failure_count),
        ]);
    }

    public function markDead(WebhookDlqEntry $entry): void
    {
        $entry->update(['status' => 'dead', 'next_retry_at' => null]);
    }

    public function replay(WebhookDlqEntry $entry, int $userId): void
    {
        $entry->update([
            'status'      => 'replayed',
            'replayed_at' => now(),
            'replayed_by' => $userId,
        ]);
    }

    public function getDlqSummary(int $orgId): array
    {
        return WebhookDlqEntry::where('organization_id', $orgId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    private function calculateNextRetry(int $failureCount): \Carbon\Carbon
    {
        // Exponential backoff: 5m, 15m, 1h, 4h, 24h
        $minutes = min(5 * (2 ** ($failureCount - 1)), 1440);
        return now()->addMinutes($minutes);
    }
}
