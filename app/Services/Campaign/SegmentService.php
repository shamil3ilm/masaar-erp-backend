<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Models\Campaign\UserSegment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SegmentService
{
    public function __construct(private readonly ConditionEvaluator $evaluator)
    {
    }

    /**
     * The organization's segments by name.
     */
    public function paginate(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * A segment of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(int $organizationId, string $segmentId): UserSegment
    {
        return UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $segmentId)
            ->firstOrFail();
    }

    /**
     * Create a segment and work out its members once the response is sent.
     */
    public function create(int $organizationId, int $userId, array $data): UserSegment
    {
        $segment = UserSegment::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'conditions' => $data['conditions'],
            'color' => $data['color'] ?? '#6366f1',
            'is_dynamic' => $data['is_dynamic'] ?? true,
            'created_by' => $userId,
        ]);

        $this->reevaluateAfterResponse($segment);

        return $segment;
    }

    /**
     * Update a segment; changed conditions re-work out its members once the response is sent.
     */
    public function update(UserSegment $segment, array $data): UserSegment
    {
        $conditionsChanged = isset($data['conditions']) && $data['conditions'] !== $segment->conditions;

        $segment->update($data);

        if ($conditionsChanged) {
            $this->reevaluateAfterResponse($segment);
        }

        return $segment->fresh();
    }

    public function delete(UserSegment $segment): void
    {
        $segment->delete();
    }

    public function paginateMembers(UserSegment $segment, int $perPage): LengthAwarePaginator
    {
        return $segment->members()->paginate($perPage);
    }

    /**
     * Membership scans every user of the organization, so it runs after the
     * response instead of holding the request. A segment deleted in the
     * meantime is skipped.
     */
    private function reevaluateAfterResponse(UserSegment $segment): void
    {
        dispatch(function () use ($segment) {
            $current = $segment->fresh();

            if ($current !== null) {
                $this->reevaluateSegment($current);
            }
        })->afterResponse();
    }

    public function userMatchesSegment(User $user, UserSegment $segment): bool
    {
        return $this->evaluator->evaluate($segment->conditions ?? [], $user);
    }

    /**
     * Re-evaluate ALL users in an org for a segment, updating memberships and member_count.
     */
    public function reevaluateSegment(UserSegment $segment): void
    {
        $matchingUserIds = [];

        User::where('organization_id', $segment->organization_id)
            ->whereNull('deleted_at')
            ->chunk(500, function ($users) use ($segment, &$matchingUserIds) {
                foreach ($users as $user) {
                    if ($this->userMatchesSegment($user, $segment)) {
                        $matchingUserIds[] = $user->id;
                    }
                }
            });

        DB::transaction(function () use ($segment, $matchingUserIds) {
            // Lock the segment row to prevent concurrent reevaluation races.
            \App\Models\Campaign\UserSegment::lockForUpdate()->findOrFail($segment->id);

            // Remove all current memberships
            DB::table('user_segment_memberships')
                ->where('segment_id', $segment->id)
                ->delete();

            // Insert new memberships in chunks
            $rows = array_map(
                fn (int $userId) => ['user_id' => $userId, 'segment_id' => $segment->id],
                $matchingUserIds
            );

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('user_segment_memberships')->insert($chunk);
            }

            $segment->update([
                'member_count'      => count($matchingUserIds),
                'last_evaluated_at' => now(),
            ]);
        });
    }

    /**
     * Re-evaluate all dynamic segments for an organization.
     */
    public function reevaluateAllForOrganization(int $organizationId): void
    {
        UserSegment::where('organization_id', $organizationId)
            ->where('is_dynamic', true)
            ->whereNull('deleted_at')
            ->each(function (UserSegment $segment) {
                try {
                    $this->reevaluateSegment($segment);
                } catch (\Throwable $e) {
                    Log::error('Failed to reevaluate segment', [
                        'segment_id' => $segment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            });
    }
}
