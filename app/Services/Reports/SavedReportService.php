<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Reports\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A user's saved report definitions.
 *
 * The owner edits and deletes a report; anyone in the organization can see
 * and run one that is shared.
 */
class SavedReportService
{
    /**
     * Reports the user owns or that are shared in the organization, by name.
     */
    public function visibleTo(User $user): Collection
    {
        return SavedReport::where('organization_id', $user->organization_id)
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('is_shared', true))
            ->with('latestExecution')
            ->orderBy('name')
            ->get();
    }

    /**
     * Save a report for the user, scheduling its next run when it has a frequency.
     */
    public function create(User $user, array $data): SavedReport
    {
        return DB::transaction(function () use ($user, $data) {
            $report = SavedReport::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                // A null left out, so a column with a default keeps it.
                ...array_filter($data, fn ($value) => $value !== null),
                'is_scheduled' => ! empty($data['schedule_frequency']),
            ]);

            if ($report->schedule_frequency) {
                $report->next_run_at = $report->calculateNextRunAt();
                $report->save();
            }

            return $report;
        });
    }

    /**
     * A report the user owns.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOwned(User $user, int $reportId): SavedReport
    {
        return SavedReport::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->findOrFail($reportId);
    }

    /**
     * A report the user owns or that is shared in the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findRunnable(User $user, int $reportId): SavedReport
    {
        return SavedReport::where('organization_id', $user->organization_id)
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('is_shared', true))
            ->findOrFail($reportId);
    }

    /**
     * Update a report, rescheduling its next run when the schedule changes.
     */
    public function update(SavedReport $report, array $data): SavedReport
    {
        return DB::transaction(function () use ($report, $data) {
            $report->update($data);

            if ($report->wasChanged('schedule_frequency') || $report->wasChanged('schedule_day')) {
                $report->next_run_at = $report->calculateNextRunAt();
                $report->save();
            }

            return $report;
        });
    }

    public function delete(SavedReport $report): void
    {
        $report->delete();
    }

    public function markRun(SavedReport $report): void
    {
        $report->update(['last_run_at' => now()]);
    }
}
