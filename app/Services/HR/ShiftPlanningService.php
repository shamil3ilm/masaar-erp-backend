<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\ShiftPattern;
use App\Models\HR\ShiftRoster;
use App\Models\HR\ShiftRosterLine;
use App\Models\HR\ShiftSwapRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShiftPlanningService
{
    /**
     * Shift patterns of the current organization by name.
     */
    public function listPatterns(bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return ShiftPattern::query()
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * A new pattern of the organization, active from creation.
     */
    public function createPattern(array $data, int $organizationId): ShiftPattern
    {
        return ShiftPattern::create(array_merge($data, [
            'organization_id' => $organizationId,
            'is_active' => true,
        ]));
    }

    /**
     * A pattern of the current organization; the tenant scope turns another
     * organization's id into a not-found.
     */
    public function findPattern(int $id): ShiftPattern
    {
        return ShiftPattern::findOrFail($id);
    }

    /**
     * Rosters of the current organization with their line count, latest
     * period first.
     *
     * @param  array{status?: mixed, branch_id?: mixed, department_id?: mixed}  $filters  empty values are ignored
     */
    public function listRosters(array $filters, int $perPage): LengthAwarePaginator
    {
        return ShiftRoster::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->byStatus($status))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->withCount('lines')
            ->orderByDesc('roster_period_start')
            ->paginate($perPage);
    }

    /**
     * Swap requests of the current organization, newest first.
     *
     * @param  array{status?: mixed, employee_id?: mixed}  $filters  empty values are ignored; employee_id matches either side
     */
    public function listSwapRequests(array $filters, int $perPage): LengthAwarePaginator
    {
        return ShiftSwapRequest::with(['requester', 'requestedEmployee'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->byStatus($status))
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where(
                fn ($either) => $either->where('requester_id', $id)->orWhere('requested_employee_id', $id)
            ))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a new shift roster.
     */
    public function createRoster(array $data): ShiftRoster
    {
        $start = Carbon::parse($data['roster_period_start']);
        $end = Carbon::parse($data['roster_period_end']);

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('Roster end date must be after start date.');
        }

        return ShiftRoster::create([
            'organization_id' => auth()->user()->organization_id,
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'name' => $data['name'],
            'roster_period_start' => $data['roster_period_start'],
            'roster_period_end' => $data['roster_period_end'],
            'status' => ShiftRoster::STATUS_DRAFT,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Publish a roster, making it visible to employees.
     */
    public function publishRoster(ShiftRoster $roster): ShiftRoster
    {
        if (!$roster->canBePublished()) {
            throw new \InvalidArgumentException('Only draft rosters can be published.');
        }

        $roster->update([
            'status' => ShiftRoster::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        return $roster->fresh();
    }

    /**
     * Assign a shift to an employee on a roster.
     */
    public function assignShift(
        ShiftRoster $roster,
        Employee $employee,
        string $shiftDate,
        ?ShiftPattern $shiftPattern,
        array $options = []
    ): ShiftRosterLine {
        if (!$roster->isDraft()) {
            throw new \InvalidArgumentException('Shifts can only be assigned to draft rosters.');
        }

        $date = Carbon::parse($shiftDate);
        if ($date->lt($roster->roster_period_start) || $date->gt($roster->roster_period_end)) {
            throw new \InvalidArgumentException('Shift date must be within the roster period.');
        }

        return ShiftRosterLine::updateOrCreate(
            [
                'roster_id' => $roster->id,
                'employee_id' => $employee->id,
                'shift_date' => $shiftDate,
            ],
            [
                'shift_pattern_id' => $shiftPattern?->id,
                'is_day_off' => $options['is_day_off'] ?? false,
                'override_start_time' => $options['override_start_time'] ?? null,
                'override_end_time' => $options['override_end_time'] ?? null,
                'notes' => $options['notes'] ?? null,
            ]
        );
    }

    /**
     * Bulk assign a shift pattern for an employee across a date range.
     *
     * The days are written in one transaction, so a failure on any day leaves
     * none of the range assigned.
     */
    public function bulkAssignShift(
        ShiftRoster $roster,
        Employee $employee,
        ShiftPattern $pattern,
        string $fromDate,
        string $toDate
    ): int {
        if (!$roster->isDraft()) {
            throw new \InvalidArgumentException('Shifts can only be assigned to draft rosters.');
        }

        return DB::transaction(function () use ($roster, $employee, $pattern, $fromDate, $toDate): int {
            $current = Carbon::parse($fromDate);
            $end = Carbon::parse($toDate);
            $daysOfWeek = $pattern->days_of_week ?? [];
            $count = 0;

            while ($current->lte($end)) {
                $isWorkingDay = in_array(strtolower($current->format('l')), $daysOfWeek, true);

                ShiftRosterLine::updateOrCreate(
                    [
                        'roster_id' => $roster->id,
                        'employee_id' => $employee->id,
                        'shift_date' => $current->toDateString(),
                    ],
                    [
                        'shift_pattern_id' => $isWorkingDay ? $pattern->id : null,
                        'is_day_off' => !$isWorkingDay,
                    ]
                );

                $count++;
                $current->addDay();
            }

            return $count;
        });
    }

    /**
     * Request a shift swap between two employees.
     */
    public function requestSwap(
        Employee $requester,
        Employee $requestedEmployee,
        string $requesterShiftDate,
        string $requestedShiftDate,
        ?string $reason = null
    ): ShiftSwapRequest {
        if ($requester->id === $requestedEmployee->id) {
            throw new \InvalidArgumentException('Cannot request swap with yourself.');
        }

        if ($requester->organization_id !== $requestedEmployee->organization_id) {
            throw new \InvalidArgumentException('Employees must belong to the same organization.');
        }

        $requesterLine = ShiftRosterLine::where('employee_id', $requester->id)
            ->where('shift_date', $requesterShiftDate)
            ->whereHas('roster', fn($q) => $q->where('status', ShiftRoster::STATUS_PUBLISHED))
            ->first();

        $requestedLine = ShiftRosterLine::where('employee_id', $requestedEmployee->id)
            ->where('shift_date', $requestedShiftDate)
            ->whereHas('roster', fn($q) => $q->where('status', ShiftRoster::STATUS_PUBLISHED))
            ->first();

        return ShiftSwapRequest::create([
            'organization_id' => $requester->organization_id,
            'requester_id' => $requester->id,
            'requested_employee_id' => $requestedEmployee->id,
            'requester_roster_line_id' => $requesterLine?->id,
            'requested_roster_line_id' => $requestedLine?->id,
            'requester_shift_date' => $requesterShiftDate,
            'requested_shift_date' => $requestedShiftDate,
            'reason' => $reason,
            'status' => ShiftSwapRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Accept a swap request (by the requested employee).
     */
    public function acceptSwap(ShiftSwapRequest $swapRequest): ShiftSwapRequest
    {
        if (!$swapRequest->isPending()) {
            throw new \InvalidArgumentException('Only pending swap requests can be accepted.');
        }

        $swapRequest->update(['status' => ShiftSwapRequest::STATUS_ACCEPTED]);

        return $swapRequest->fresh();
    }

    /**
     * Approve a swap request (by a manager) and exchange the two shifts.
     *
     * The status is checked on the locked row: approving from a copy read
     * before another approval would otherwise exchange the shifts back.
     */
    public function approveSwap(ShiftSwapRequest $swapRequest): ShiftSwapRequest
    {
        return $swapRequest->lockForTransition(function (ShiftSwapRequest $swap): ShiftSwapRequest {
            if (!$swap->canBeApproved()) {
                throw new \InvalidArgumentException('Swap request must be accepted before it can be approved.');
            }

            $swap->update([
                'status' => ShiftSwapRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $requesterLine = $swap->requesterRosterLine;
            $requestedLine = $swap->requestedRosterLine;

            if ($requesterLine && $requestedLine) {
                $requesterPatternId = $requesterLine->shift_pattern_id;

                $requesterLine->update(['shift_pattern_id' => $requestedLine->shift_pattern_id]);
                $requestedLine->update(['shift_pattern_id' => $requesterPatternId]);
            }

            return $swap->fresh();
        });
    }

    /**
     * Reject a swap request that is still pending, checked on the locked row.
     */
    public function rejectSwap(ShiftSwapRequest $swapRequest, string $reason): ShiftSwapRequest
    {
        return $swapRequest->lockForTransition(function (ShiftSwapRequest $swap) use ($reason): ShiftSwapRequest {
            if (!$swap->isPending()) {
                throw new \InvalidArgumentException('Only pending swap requests can be rejected.');
            }

            $swap->update([
                'status' => ShiftSwapRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $swap->fresh();
        });
    }
}
