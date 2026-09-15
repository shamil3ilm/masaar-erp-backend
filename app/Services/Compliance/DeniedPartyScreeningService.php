<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Compliance\DpsListEntry;
use App\Models\Compliance\DpsSanctionList;
use App\Models\Compliance\DpsScreeningResult;
use App\Models\Compliance\DpsScreeningRun;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeniedPartyScreeningService
{
    private const CONFIRMED_MATCH_THRESHOLD = 95.0;

    // -------------------------------------------------------------------------
    // Sanction lists and entries
    // -------------------------------------------------------------------------

    /**
     * The organization's sanction lists, each with its count of active entries.
     */
    public function paginateLists(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return DpsSanctionList::where('organization_id', $organizationId)
            ->withCount(['entries' => fn ($q) => $q->where('is_active', true)])
            ->paginate($perPage);
    }

    public function createList(array $data, int $organizationId): DpsSanctionList
    {
        return DpsSanctionList::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function findList(int $id): DpsSanctionList
    {
        return DpsSanctionList::findOrFail($id);
    }

    public function findListWithActiveEntryCount(int $id): DpsSanctionList
    {
        return DpsSanctionList::withCount([
            'entries' => fn ($q) => $q->where('is_active', true),
        ])->findOrFail($id);
    }

    public function updateList(DpsSanctionList $list, array $data): DpsSanctionList
    {
        $list->update($data);

        return $list->fresh();
    }

    /**
     * The list's entries, optionally narrowed to names containing the search.
     */
    public function paginateEntries(DpsSanctionList $list, ?string $search, int $perPage): LengthAwarePaginator
    {
        return DpsListEntry::where('dps_sanction_list_id', $list->id)
            ->when($search, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->paginate($perPage);
    }

    public function addEntry(DpsSanctionList $list, array $data): DpsListEntry
    {
        return DpsListEntry::create(array_merge($data, ['dps_sanction_list_id' => $list->id]));
    }

    // -------------------------------------------------------------------------
    // Screening runs
    // -------------------------------------------------------------------------

    /**
     * The organization's screening runs, latest first.
     *
     * @param  array{status?: string|null, entity_type?: string|null}  $filters
     */
    public function paginateRuns(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return DpsScreeningRun::where('organization_id', $organizationId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['entity_type'] ?? null, fn ($q, $type) => $q->where('screened_entity_type', $type))
            ->orderByDesc('screening_date')
            ->paginate($perPage);
    }

    public function findRun(int $id): DpsScreeningRun
    {
        return DpsScreeningRun::findOrFail($id);
    }

    /**
     * A run with its matches and, by name, the officer who cleared it.
     */
    public function findRunWithDetails(int $id): DpsScreeningRun
    {
        return DpsScreeningRun::with(['results.listEntry', 'clearedBy:id,name'])->findOrFail($id);
    }

    /**
     * The contact's most recent screening run in the organization, if any.
     */
    public function latestRunForContact(int $contactId): ?DpsScreeningRun
    {
        return DpsScreeningRun::where('screened_entity_type', 'contact')
            ->where('screened_entity_id', $contactId)
            ->orderByDesc('screening_date')
            ->first();
    }

    /**
     * Screen a single contact against all active sanction lists.
     */
    public function screenContact(int $contactId, float $threshold = 80.0): DpsScreeningRun
    {
        $contact = Contact::findOrFail($contactId);

        return DB::transaction(function () use ($contact, $threshold): DpsScreeningRun {
            $run = DpsScreeningRun::create([
                'organization_id'      => $contact->organization_id,
                'screened_entity_type' => 'contact',
                'screened_entity_id'   => $contact->id,
                'screening_date'       => now(),
                'match_threshold'      => $threshold,
                'status'               => DpsScreeningRun::STATUS_CLEAN,
                'triggered_by'         => DpsScreeningRun::TRIGGER_MANUAL,
            ]);

            $searchName   = $contact->company_name ?? $contact->contact_name ?? '';
            $highestScore = 0.0;
            $hasMatch     = false;

            DpsListEntry::whereHas('sanctionList', function ($q) use ($contact): void {
                $q->where('organization_id', $contact->organization_id)->where('is_active', true);
            })->active()->chunkById(500, function ($entries) use ($run, $searchName, $threshold, &$highestScore, &$hasMatch): void {
                foreach ($entries as $entry) {
                    $bestScore = 0.0;
                    $bestField = DpsScreeningResult::FIELD_NAME;

                    foreach ($entry->getAllNames() as $index => $sanctionName) {
                        $score = $this->calculateMatchScore($searchName, $sanctionName);
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestField = $index === 0
                                ? DpsScreeningResult::FIELD_NAME
                                : DpsScreeningResult::FIELD_ALIAS;
                        }
                    }

                    if ($bestScore >= $threshold) {
                        DpsScreeningResult::create([
                            'dps_screening_run_id' => $run->id,
                            'dps_list_entry_id'    => $entry->id,
                            'match_score'          => $bestScore,
                            'matched_field'        => $bestField,
                            'is_false_positive'    => false,
                        ]);

                        $hasMatch = true;
                        $highestScore = max($highestScore, $bestScore);
                    }
                }
            });

            if ($hasMatch) {
                $status = $highestScore >= self::CONFIRMED_MATCH_THRESHOLD
                    ? DpsScreeningRun::STATUS_CONFIRMED_MATCH
                    : DpsScreeningRun::STATUS_POTENTIAL_MATCH;

                $run->update(['status' => $status]);
            }

            return $run->load('results.listEntry');
        });
    }

    /**
     * Bulk-screen all active contacts for an organisation.
     *
     * @return array{screened: int, clean: int, potential_matches: int, confirmed_matches: int}
     */
    public function screenAll(int $organizationId): array
    {
        $summary = [
            'screened'          => 0,
            'clean'             => 0,
            'potential_matches' => 0,
            'confirmed_matches' => 0,
        ];

        Contact::where('organization_id', $organizationId)
            ->active()
            ->chunkById(100, function ($contacts) use (&$summary): void {
                foreach ($contacts as $contact) {
                    try {
                        $run = $this->screenContact($contact->id);
                        $summary['screened']++;

                        match ($run->status) {
                            DpsScreeningRun::STATUS_CLEAN            => $summary['clean']++,
                            DpsScreeningRun::STATUS_POTENTIAL_MATCH  => $summary['potential_matches']++,
                            DpsScreeningRun::STATUS_CONFIRMED_MATCH  => $summary['confirmed_matches']++,
                            default                                  => null,
                        };
                    } catch (\Throwable $e) {
                        Log::error("DPS bulk screening failed for contact #{$contact->id}: {$e->getMessage()}");
                    }
                }
            });

        return $summary;
    }

    /**
     * Calculate fuzzy match score between two names (0–100).
     * Uses PHP's similar_text with a levenshtein distance penalty for very short strings.
     */
    public function calculateMatchScore(string $name, string $sanctionName): float
    {
        if ($name === '' || $sanctionName === '') {
            return 0.0;
        }

        $a = mb_strtolower(trim($name));
        $b = mb_strtolower(trim($sanctionName));

        if ($a === $b) {
            return 100.0;
        }

        similar_text($a, $b, $percent);

        // Boost for exact substring containment
        if (str_contains($a, $b) || str_contains($b, $a)) {
            $percent = max($percent, 85.0);
        }

        return round($percent, 2);
    }

    /**
     * Get all active sanction lists for an organisation.
     */
    public function getActiveSanctionLists(int $organizationId): Collection
    {
        return DpsSanctionList::where('organization_id', $organizationId)
            ->active()
            ->withCount(['entries' => fn ($q) => $q->where('is_active', true)])
            ->limit(100)
            ->get();
    }

    /**
     * Bulk import entries into a sanction list. Returns the count of imported entries.
     *
     * @param  array<array{entry_type: string, name: string, aliases?: array, country_code?: string, ...}> $entries
     */
    public function importListEntries(int $sanctionListId, array $entries): int
    {
        $sanctionList = DpsSanctionList::findOrFail($sanctionListId);
        $count        = 0;

        DB::transaction(function () use ($sanctionList, $entries, &$count): void {
            foreach ($entries as $entry) {
                DpsListEntry::create([
                    'dps_sanction_list_id' => $sanctionList->id,
                    'entry_type'           => $entry['entry_type'] ?? DpsListEntry::ENTRY_ENTITY,
                    'name'                 => $entry['name'],
                    'aliases'              => $entry['aliases'] ?? null,
                    'country_code'         => $entry['country_code'] ?? null,
                    'address'              => $entry['address'] ?? null,
                    'id_number'            => $entry['id_number'] ?? null,
                    'program'              => $entry['program'] ?? null,
                    'remarks'              => $entry['remarks'] ?? null,
                    'effective_date'       => $entry['effective_date'] ?? null,
                    'expiry_date'          => $entry['expiry_date'] ?? null,
                    'is_active'            => true,
                ]);
                $count++;
            }

            // Update the list's entry count and last_updated_at
            $sanctionList->update([
                'entry_count'     => DpsListEntry::where('dps_sanction_list_id', $sanctionList->id)
                    ->where('is_active', true)
                    ->count(),
                'last_updated_at' => now(),
            ]);
        });

        return $count;
    }

    /**
     * Mark a screening run as cleared by a compliance officer.
     *
     * Only a run awaiting review is cleared, checked on the locked row: a
     * clearance is the record of who accepted a match and why, so a second
     * officer cannot overwrite it, and a clean run has nothing to accept.
     *
     * @throws BusinessRuleException when the run has no match awaiting review
     */
    public function clearScreening(DpsScreeningRun $run, int $userId, string $notes): DpsScreeningRun
    {
        return $run->lockForTransition(function (DpsScreeningRun $locked) use ($userId, $notes): DpsScreeningRun {
            if (! $locked->requiresReview()) {
                throw new BusinessRuleException(
                    'Only a screening run with a potential or confirmed match can be cleared.',
                    'SCREENING_NOT_REVIEWABLE',
                    422
                );
            }

            $locked->update([
                'status'          => DpsScreeningRun::STATUS_CLEARED,
                'cleared_by'      => $userId,
                'cleared_at'      => now(),
                'clearance_notes' => $notes,
            ]);

            return $locked->fresh(['clearedBy:id,name']);
        });
    }

    /**
     * Get all screening runs that require manual review.
     */
    public function getPendingReviews(int $organizationId): Collection
    {
        return DpsScreeningRun::where('organization_id', $organizationId)
            ->pendingReview()
            ->with(['results.listEntry'])
            ->orderByDesc('screening_date')
            ->limit(100)
            ->get();
    }

    /**
     * Quick check whether the most recent screening for a contact is clean.
     */
    public function isContactClean(int $contactId): bool
    {
        $latestRun = $this->latestRunForContact($contactId);

        if (! $latestRun) {
            return true; // Never screened — treat as not blocked
        }

        return in_array($latestRun->status, [
            DpsScreeningRun::STATUS_CLEAN,
            DpsScreeningRun::STATUS_CLEARED,
        ], true);
    }
}
