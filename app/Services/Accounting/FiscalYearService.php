<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates, maintains and closes an organization's fiscal years.
 *
 * Every change runs in one transaction, so a year is never left without its
 * periods, and an organization never loses its current year half way through
 * a switch. Changes to an existing year run on its locked row, so a close and
 * a concurrent delete or rename cannot both pass their checks.
 */
class FiscalYearService
{
    /**
     * @return Collection<int, FiscalYear>
     */
    public function list(): Collection
    {
        return FiscalYear::orderByDesc('start_date')->get();
    }

    public function current(int $organizationId): ?FiscalYear
    {
        return FiscalYear::current($organizationId);
    }

    /**
     * The id of the organization's current fiscal year, or 0 when none is set.
     */
    public function currentId(int $organizationId): int
    {
        return (int) FiscalYear::where('organization_id', $organizationId)
            ->where('is_current', true)
            ->value('id');
    }

    /**
     * @param  array{name: string, start_date: string, end_date: string, is_current?: bool, create_periods?: bool}  $data
     *
     * @throws BusinessRuleException when the dates overlap another fiscal year
     */
    public function create(int $organizationId, array $data): FiscalYear
    {
        return DB::transaction(function () use ($organizationId, $data): FiscalYear {
            if ($this->overlapsExistingYear($data['start_date'], $data['end_date'])) {
                throw new BusinessRuleException('Fiscal year dates overlap with an existing fiscal year', 'OVERLAP', 422);
            }

            $fiscalYear = FiscalYear::create([
                'organization_id' => $organizationId,
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            if ($data['is_current'] ?? false) {
                $fiscalYear->setAsCurrent();
            }

            if ($data['create_periods'] ?? false) {
                $this->createMonthlyPeriods($fiscalYear);
            }

            return $fiscalYear->fresh(['periods']);
        });
    }

    /**
     * @param  array{name?: string}  $data
     *
     * @throws BusinessRuleException when the year is closed
     */
    public function update(FiscalYear $fiscalYear, array $data): FiscalYear
    {
        return $fiscalYear->lockForTransition(function (FiscalYear $locked) use ($data): FiscalYear {
            if ($locked->is_closed) {
                throw new BusinessRuleException('Closed fiscal years cannot be modified', 'CLOSED', 400);
            }

            $locked->update($data);

            return $locked;
        });
    }

    /**
     * @throws BusinessRuleException when the year is closed
     */
    public function setCurrent(FiscalYear $fiscalYear): FiscalYear
    {
        return $fiscalYear->lockForTransition(function (FiscalYear $locked): FiscalYear {
            if ($locked->is_closed) {
                throw new BusinessRuleException('Closed fiscal years cannot be set as current', 'CLOSED', 400);
            }

            $locked->setAsCurrent();

            return $locked;
        });
    }

    /**
     * Close the year once all its periods are closed and no draft entry remains.
     *
     * @throws BusinessRuleException when the year is closed or still has open work
     */
    public function close(FiscalYear $fiscalYear, ?int $userId): FiscalYear
    {
        return $fiscalYear->lockForTransition(function (FiscalYear $locked) use ($userId): FiscalYear {
            if ($locked->is_closed) {
                throw new BusinessRuleException('Fiscal year is already closed', 'ALREADY_CLOSED', 400);
            }

            if ($locked->periods()->where('is_closed', false)->exists()) {
                throw new BusinessRuleException('All accounting periods must be closed first', 'OPEN_PERIODS', 400);
            }

            if ($locked->journalEntries()->where('status', JournalEntry::STATUS_DRAFT)->exists()) {
                throw new BusinessRuleException('There are still draft journal entries in this fiscal year', 'DRAFT_ENTRIES', 400);
            }

            $locked->close($userId);

            return $locked->fresh();
        });
    }

    /**
     * Delete an open year that holds no journal entries, with its periods.
     *
     * @throws BusinessRuleException when the year is closed or has entries
     */
    public function delete(FiscalYear $fiscalYear): void
    {
        $fiscalYear->lockForTransition(function (FiscalYear $locked): void {
            if ($locked->is_closed) {
                throw new BusinessRuleException('Closed fiscal years cannot be deleted', 'CLOSED', 400);
            }

            if ($locked->journalEntries()->exists()) {
                throw new BusinessRuleException('Cannot delete fiscal year with journal entries', 'HAS_ENTRIES', 400);
            }

            $locked->periods()->delete();
            $locked->delete();
        });
    }

    /**
     * Whether the range touches a year of the organization in scope: either
     * end falls inside a year, or the range encloses one.
     */
    private function overlapsExistingYear(string $startDate, string $endDate): bool
    {
        return FiscalYear::where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($inner) use ($startDate, $endDate) {
                    $inner->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        })->lockForUpdate()->exists();
    }

    /**
     * One period per calendar month, the last one ending on the year's end date.
     */
    private function createMonthlyPeriods(FiscalYear $fiscalYear): void
    {
        $start = $fiscalYear->start_date->copy();
        $end = $fiscalYear->end_date;
        $periodNumber = 1;

        while ($start->lte($end)) {
            $periodEnd = $start->copy()->endOfMonth();
            if ($periodEnd->gt($end)) {
                $periodEnd = $end;
            }

            $fiscalYear->periods()->create([
                'period_number' => $periodNumber,
                'period_type' => 'month',
                'start_date' => $start->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ]);

            $start->addMonth()->startOfMonth();
            $periodNumber++;
        }
    }
}
