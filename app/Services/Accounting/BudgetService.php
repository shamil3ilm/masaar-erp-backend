<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Budget\Budget;
use App\Models\Budget\BudgetCommitment;
use App\Models\Budget\BudgetLine;
use App\Models\Budget\BudgetRevision;
use App\Models\Budget\BudgetRevisionLine;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Budgets, their lines, lifecycle, revisions, commitments and reports.
 *
 * Every change to a budget or its lines runs on the locked budget row and
 * checks its status there, so a line cannot be added to a budget that another
 * request is approving, and two approvals cannot both pass their guard.
 */
class BudgetService
{
    /** The amounts a line is planned in; its total is always their sum. */
    private const QUARTER_FIELDS = ['q1_amount', 'q2_amount', 'q3_amount', 'q4_amount'];

    // ----------------------------------------------------------------
    // Queries
    // ----------------------------------------------------------------

    /**
     * The organization's budgets, newest first.
     *
     * @param  array{status?: string, budget_type?: string, fiscal_year_id?: int, search?: string}  $filters
     */
    public function paginateBudgets(array $filters, int $perPage): LengthAwarePaginator
    {
        return Budget::with(['fiscalYear:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['budget_type']), fn ($q) => $q->where('budget_type', $filters['budget_type']))
            ->when(isset($filters['fiscal_year_id']), fn ($q) => $q->where('fiscal_year_id', $filters['fiscal_year_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where('name', 'like', "%{$filters['search']}%"))
            ->paginate($perPage);
    }

    public function findBudget(int $id): ?Budget
    {
        return Budget::find($id);
    }

    /**
     * A budget with its lines, revisions and the people who created and approved it.
     */
    public function findBudgetWithDetails(int $id): ?Budget
    {
        return Budget::with([
            'fiscalYear:id,name',
            'lines.account:id,code,name',
            'lines.costCenter:id,code,name',
            'lines.department:id,name',
            'revisions.creator:id,name',
            'approver:id,name',
            'creator:id,name',
        ])->find($id);
    }

    /**
     * A line of this budget, or null when the id belongs to another budget.
     */
    public function findLine(Budget $budget, int $lineId): ?BudgetLine
    {
        return $budget->lines()->find($lineId);
    }

    /**
     * The commitments recorded against the budget's lines, latest first.
     *
     * @return Collection<int, BudgetCommitment>
     */
    public function commitmentsFor(Budget $budget): Collection
    {
        return BudgetCommitment::with(['budgetLine', 'creator:id,name'])
            ->whereIn('budget_line_id', $budget->lines()->select('id'))
            ->orderByDesc('committed_at')
            ->get();
    }

    // ----------------------------------------------------------------
    // Budget lifecycle
    // ----------------------------------------------------------------

    /**
     * Create a budget together with its lines.
     *
     * @param  array  $data   Budget header fields.
     * @param  array  $lines  Array of line data (each must have name + q1..q4).
     */
    public function createBudget(array $data, array $lines, int $userId): Budget
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            $budget = Budget::create([
                'organization_id' => $data['organization_id'],
                'fiscal_year_id'  => $data['fiscal_year_id'] ?? null,
                'name'            => $data['name'],
                'budget_type'     => $data['budget_type'] ?? Budget::TYPE_ANNUAL,
                'status'          => Budget::STATUS_DRAFT,
                'period_start'    => $data['period_start'],
                'period_end'      => $data['period_end'],
                'currency_code'   => $data['currency_code'] ?? 'SAR',
                'description'     => $data['description'] ?? null,
                'created_by'      => $userId,
                'total_amount'    => 0,
            ]);

            $totalBudget = '0';

            foreach ($lines as $lineData) {
                $lineTotal = $this->quarterTotal($lineData);

                $budget->lines()->create([
                    'account_id'      => $lineData['account_id'] ?? null,
                    'cost_center_id'  => $lineData['cost_center_id'] ?? null,
                    'department_id'   => $lineData['department_id'] ?? null,
                    'name'            => $lineData['name'],
                    'q1_amount'       => (string) ($lineData['q1_amount'] ?? 0),
                    'q2_amount'       => (string) ($lineData['q2_amount'] ?? 0),
                    'q3_amount'       => (string) ($lineData['q3_amount'] ?? 0),
                    'q4_amount'       => (string) ($lineData['q4_amount'] ?? 0),
                    'total_amount'    => $lineTotal,
                    'committed_amount' => 0,
                    'actual_amount'   => 0,
                    'notes'           => $lineData['notes'] ?? null,
                ]);

                $totalBudget = bcadd($totalBudget, $lineTotal, 4);
            }

            $budget->update(['total_amount' => $totalBudget]);

            return $budget->fresh(['lines', 'fiscalYear', 'creator']);
        });
    }

    /**
     * Changes the header of a draft or submitted budget.
     *
     * @throws BusinessRuleException when the budget is past submission
     */
    public function updateBudget(Budget $budget, array $data): Budget
    {
        return $budget->lockForTransition(function (Budget $locked) use ($data): Budget {
            $this->assertEditable($locked, 'Only draft or submitted budgets can be edited.', 'BUDGET_NOT_EDITABLE');

            $locked->update($data);

            return $locked->fresh(['lines', 'fiscalYear']);
        });
    }

    /**
     * Deletes a draft or submitted budget.
     *
     * @throws BusinessRuleException when the budget is past submission
     */
    public function deleteBudget(Budget $budget): void
    {
        $budget->lockForTransition(function (Budget $locked): void {
            $this->assertEditable($locked, 'Only draft or submitted budgets can be deleted.', 'BUDGET_NOT_DELETABLE');

            $locked->delete();
        });
    }

    /**
     * Transition budget from draft → submitted.
     */
    public function submitBudget(Budget $budget, int $userId): Budget
    {
        return $budget->lockForTransition(function (Budget $locked): Budget {
            if ($locked->status !== Budget::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft budgets can be submitted.');
            }

            $locked->update(['status' => Budget::STATUS_SUBMITTED]);

            return $locked->fresh();
        });
    }

    /**
     * Transition budget from submitted → approved.
     */
    public function approveBudget(Budget $budget, int $userId): Budget
    {
        return $budget->lockForTransition(function (Budget $locked) use ($userId): Budget {
            if ($locked->status !== Budget::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Only submitted budgets can be approved.');
            }

            $locked->update([
                'status'          => Budget::STATUS_APPROVED,
                'approved_amount' => $locked->total_amount,
                'approved_by'     => $userId,
                'approved_at'     => now(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Transition budget from approved → active.
     */
    public function activateBudget(Budget $budget, int $userId): Budget
    {
        return $budget->lockForTransition(function (Budget $locked): Budget {
            if ($locked->status !== Budget::STATUS_APPROVED) {
                throw new InvalidArgumentException('Only approved budgets can be activated.');
            }

            $locked->update(['status' => Budget::STATUS_ACTIVE]);

            return $locked->fresh();
        });
    }

    /**
     * Close an active budget.
     */
    public function closeBudget(Budget $budget, int $userId): Budget
    {
        return $budget->lockForTransition(function (Budget $locked): Budget {
            if ($locked->status !== Budget::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Only active budgets can be closed.');
            }

            $locked->update(['status' => Budget::STATUS_CLOSED]);

            return $locked->fresh();
        });
    }

    /**
     * Cancel a budget (draft, submitted, or approved).
     */
    public function cancelBudget(Budget $budget, int $userId): Budget
    {
        return $budget->lockForTransition(function (Budget $locked): Budget {
            $cancellable = [Budget::STATUS_DRAFT, Budget::STATUS_SUBMITTED, Budget::STATUS_APPROVED];

            if (!in_array($locked->status, $cancellable, true)) {
                throw new InvalidArgumentException('Budget cannot be cancelled in its current status.');
            }

            $locked->update(['status' => Budget::STATUS_CANCELLED]);

            return $locked->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Lines
    // ----------------------------------------------------------------

    /**
     * Adds a line to a draft or submitted budget and refreshes the budget total.
     *
     * @throws BusinessRuleException when the budget is past submission
     */
    public function addLine(Budget $budget, array $data): BudgetLine
    {
        return $budget->lockForTransition(function (Budget $locked) use ($data): BudgetLine {
            $this->assertEditable($locked, 'Only draft or submitted budgets can be edited.', 'BUDGET_NOT_EDITABLE');

            $line = $locked->lines()->create(array_merge($data, [
                'total_amount'     => $this->quarterTotal($data),
                'committed_amount' => 0,
                'actual_amount'    => 0,
            ]));

            $locked->update(['total_amount' => $locked->getTotalBudgeted()]);

            return $line;
        });
    }

    /**
     * Removes a line from a draft or submitted budget and refreshes the budget total.
     *
     * @throws BusinessRuleException when the budget is past submission
     */
    public function removeLine(Budget $budget, BudgetLine $line): void
    {
        $budget->lockForTransition(function (Budget $locked) use ($line): void {
            $this->assertEditable($locked, 'Only draft or submitted budgets can be edited.', 'BUDGET_NOT_EDITABLE');

            $line->delete();

            $locked->update(['total_amount' => $locked->getTotalBudgeted()]);
        });
    }

    /**
     * Update a single budget line's quarterly amounts.
     * Recalculates line total and refreshes the budget header total.
     *
     * Only a draft or submitted budget's lines change here; an approved or
     * active budget changes through a revision, which records each change.
     *
     * @throws BusinessRuleException when the budget is past submission
     */
    public function updateLine(BudgetLine $line, array $data, int $userId): BudgetLine
    {
        return DB::transaction(function () use ($line, $data) {
            $budget = $line->budget()->lockForUpdate()->firstOrFail();

            $this->assertEditable($budget, 'Only draft or submitted budgets can be edited.', 'BUDGET_NOT_EDITABLE');

            $amounts = [];

            foreach (self::QUARTER_FIELDS as $field) {
                $amounts[$field] = (string) (isset($data[$field]) ? $data[$field] : $line->{$field});
            }

            $line->update(array_merge(
                array_intersect_key($data, array_flip([
                    'name', 'account_id', 'cost_center_id', 'department_id', 'notes',
                ])),
                $amounts,
                ['total_amount' => $this->quarterTotal($amounts)]
            ));

            $budget->update(['total_amount' => $budget->getTotalBudgeted()]);

            return $line->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Revisions
    // ----------------------------------------------------------------

    /**
     * Revises quarter amounts of an approved or active budget, recording the
     * old and new value of each change as a revision line.
     *
     * @param  list<array{budget_line_id: int, field_changed: string, new_value: float|int|string}>  $lineChanges
     */
    public function reviseBudget(
        Budget $budget,
        array  $lineChanges,
        string $reason,
        int    $userId
    ): BudgetRevision {
        return $budget->lockForTransition(function (Budget $locked) use ($lineChanges, $reason, $userId): BudgetRevision {
            if (!in_array($locked->status, [Budget::STATUS_APPROVED, Budget::STATUS_ACTIVE], true)) {
                throw new InvalidArgumentException('Only approved or active budgets can be revised.');
            }

            $revision = BudgetRevision::create([
                'organization_id' => $locked->organization_id,
                'budget_id'       => $locked->id,
                'revision_number' => $locked->revisions()->max('revision_number') + 1,
                'reason'          => $reason,
                'previous_total'  => $locked->total_amount,
                'new_total'       => $locked->total_amount, // updated below
                'status'          => BudgetRevision::STATUS_DRAFT,
                'created_by'      => $userId,
            ]);

            foreach ($lineChanges as $change) {
                $this->applyRevisionChange($locked, $revision, $change);
            }

            $locked->update(['total_amount' => $locked->getTotalBudgeted()]);
            $locked->refresh();

            $revision->update(['new_total' => $locked->total_amount]);

            return $revision->fresh(['lines']);
        });
    }

    /**
     * @param  array{budget_line_id: int, field_changed: string, new_value: float|int|string}  $change
     */
    private function applyRevisionChange(Budget $budget, BudgetRevision $revision, array $change): void
    {
        $field = $change['field_changed'];

        if (!in_array($field, self::QUARTER_FIELDS, true)) {
            throw new InvalidArgumentException('Only quarter amounts can be revised; a line total is the sum of its quarters.');
        }

        $line = $budget->lines()->find((int) $change['budget_line_id']);

        if ($line === null) {
            throw new InvalidArgumentException("Budget line {$change['budget_line_id']} does not belong to this budget.");
        }

        $oldValue = (string) $line->{$field};
        $newValue = (string) $change['new_value'];

        if (bccomp($newValue, $oldValue, 4) === 0) {
            return;
        }

        BudgetRevisionLine::create([
            'budget_revision_id' => $revision->id,
            'budget_line_id'     => $line->id,
            'field_changed'      => $field,
            'old_value'          => $oldValue,
            'new_value'          => $newValue,
        ]);

        $line->{$field} = $newValue;
        $line->total_amount = $this->quarterTotal($line->only(self::QUARTER_FIELDS));
        $line->save();
    }

    /**
     * Approve a budget revision.
     */
    public function approveRevision(BudgetRevision $revision, int $userId): BudgetRevision
    {
        if ($revision->status !== BudgetRevision::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft revisions can be approved.');
        }

        $revision->update([
            'status'      => BudgetRevision::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $revision->fresh();
    }

    /**
     * @param  array<string, mixed>  $amounts  quarter amounts; a missing quarter counts as zero
     */
    private function quarterTotal(array $amounts): string
    {
        $total = '0';

        foreach (self::QUARTER_FIELDS as $field) {
            $total = bcadd($total, (string) ($amounts[$field] ?? 0), 4);
        }

        return $total;
    }

    private function assertEditable(Budget $budget, string $message, string $errorCode): void
    {
        if (!$budget->isEditable()) {
            throw new BusinessRuleException($message, $errorCode, 422);
        }
    }

    // ----------------------------------------------------------------
    // Commitments
    // ----------------------------------------------------------------

    /**
     * Create a commitment against a budget line.
     * Validates that the committed amount does not exceed available budget.
     */
    public function createCommitment(array $data, int $userId): BudgetCommitment
    {
        return DB::transaction(function () use ($data, $userId) {
            $line   = BudgetLine::where('id', (int) $data['budget_line_id'])->lockForUpdate()->firstOrFail();
            $amount = (float) $data['committed_amount'];

            if ($amount <= 0) {
                throw new InvalidArgumentException('Committed amount must be greater than zero.');
            }

            $available = $line->getAvailableAmount();

            if ($amount > $available) {
                throw new RuntimeException(
                    "Commitment of {$amount} exceeds available budget of {$available} on line '{$line->name}'."
                );
            }

            $commitment = BudgetCommitment::create([
                'organization_id'  => $data['organization_id'],
                'budget_line_id'   => $line->id,
                'source_type'      => $data['source_type'] ?? 'purchase_order',
                'source_id'        => $data['source_id'],
                'committed_amount' => $amount,
                'status'           => BudgetCommitment::STATUS_OPEN,
                'committed_at'     => $data['committed_at'] ?? now(),
                'created_by'       => $userId,
            ]);

            // Update committed total on line
            $line->increment('committed_amount', $amount);

            return $commitment->fresh(['budgetLine']);
        });
    }

    /**
     * Release a commitment when the actual invoice / expense arrives.
     *
     * @param  float  $actualAmount  The real spend to record on the line.
     */
    public function releaseCommitment(
        BudgetCommitment $commitment,
        float            $actualAmount,
        int              $userId
    ): void {
        DB::transaction(function () use ($commitment, $actualAmount, $userId) {
            if (!$commitment->isOpen()) {
                throw new InvalidArgumentException('Only open or partially-used commitments can be released.');
            }

            $commitment->partialRelease($actualAmount, $userId);
        });
    }

    // ----------------------------------------------------------------
    // Actuals sync
    // ----------------------------------------------------------------

    /**
     * Sync actual_amount on budget lines from posted journal entries,
     * matched by account_id and cost_center_id within the budget period.
     */
    public function updateActuals(int $orgId, string $from, string $to): void
    {
        DB::transaction(function () use ($orgId, $from, $to) {
            // Find all active/approved budgets that overlap the given range
            $budgets = Budget::withoutGlobalScope('organization')
                ->where('organization_id', $orgId)
                ->whereIn('status', [Budget::STATUS_ACTIVE, Budget::STATUS_APPROVED])
                ->forPeriod($from, $to)
                ->with('lines')
                ->get();

            foreach ($budgets as $budget) {
                foreach ($budget->lines as $line) {
                    if ($line->account_id === null) {
                        continue;
                    }

                    $query = JournalEntryLine::withoutGlobalScope('organization')
                        ->where('organization_id', $orgId)
                        ->whereHas('journalEntry', function ($q) use ($orgId, $from, $to) {
                            $q->withoutGlobalScope('organization')
                                ->where('organization_id', $orgId)
                                ->where('status', 'posted')
                                ->whereBetween('entry_date', [$from, $to]);
                        })
                        ->where('account_id', $line->account_id);

                    if ($line->cost_center_id !== null) {
                        $query->where('cost_center_id', $line->cost_center_id);
                    }

                    $debits  = (clone $query)->sum('base_debit');
                    $credits = (clone $query)->sum('base_credit');

                    // For expense accounts debit increases spending
                    $diff   = bcsub((string) $debits, (string) $credits, 4);
                    $actual = bccomp($diff, '0', 4) > 0 ? $diff : '0.0000';

                    $line->update(['actual_amount' => $actual]);
                }
            }
        });
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /**
     * Budget vs Actual: returns per-line breakdown.
     */
    public function getBudgetVsActual(Budget $budget): array
    {
        $lines = $budget->lines()->with(['account:id,code,name', 'costCenter:id,code,name', 'department:id,name'])->get();

        $rows = $lines->map(function (BudgetLine $line) {
            $variance        = $line->getVariance();
            $variancePct     = $line->getVariancePercent();

            return [
                'id'               => $line->id,
                'name'             => $line->name,
                'account'          => $line->account
                    ? ['id' => $line->account->id, 'code' => $line->account->code, 'name' => $line->account->name]
                    : null,
                'cost_center'      => $line->costCenter
                    ? ['id' => $line->costCenter->id, 'code' => $line->costCenter->code, 'name' => $line->costCenter->name]
                    : null,
                'department'       => $line->department
                    ? ['id' => $line->department->id, 'name' => $line->department->name]
                    : null,
                'budgeted'         => $line->total_amount,
                'committed'        => $line->committed_amount,
                'actual'           => $line->actual_amount,
                'available'        => $line->getAvailableAmount(),
                'variance'         => $variance,
                'variance_percent' => $variancePct,
                'over_budget'      => $line->isOverBudget(),
                'q1_amount'        => $line->q1_amount,
                'q2_amount'        => $line->q2_amount,
                'q3_amount'        => $line->q3_amount,
                'q4_amount'        => $line->q4_amount,
            ];
        });

        $totalBudgeted  = (string) $budget->getTotalBudgeted();
        $totalCommitted = (string) $budget->getTotalCommitted();
        $totalActual    = (string) $budget->getTotalActual();
        $totalVariance  = bcsub($totalBudgeted, $totalActual, 4);

        return [
            'budget_id'              => $budget->id,
            'budget_uuid'            => $budget->uuid,
            'budget_name'            => $budget->name,
            'currency_code'          => $budget->currency_code,
            'period_start'           => $budget->period_start->toDateString(),
            'period_end'             => $budget->period_end->toDateString(),
            'total_budgeted'         => $totalBudgeted,
            'total_committed'        => $totalCommitted,
            'total_actual'           => $totalActual,
            'total_available'        => $budget->getRemainingBudget(),
            'total_variance'         => $totalVariance,
            'total_variance_percent' => bccomp($totalBudgeted, '0', 4) > 0
                ? round((float) bcdiv(bcmul($totalVariance, '100', 4), $totalBudgeted, 4), 2)
                : 0.0,
            'utilization_percent'    => $budget->getUtilizationPercent(),
            'lines'                  => $rows->toArray(),
        ];
    }

    /**
     * Forecast year-end spend based on actuals to date + run rate.
     */
    public function getForecast(Budget $budget): array
    {
        $today       = Carbon::today();
        $periodStart = $budget->period_start;
        $periodEnd   = $budget->period_end;

        $totalDays     = max(1, $periodStart->diffInDays($periodEnd));
        $elapsedDays   = min($totalDays, $periodStart->diffInDays($today));
        $remainingDays = $totalDays - $elapsedDays;
        $progressPct   = round(
            (float) bcdiv(bcmul((string) $elapsedDays, '100', 4), (string) $totalDays, 4),
            2
        );

        $lines = $budget->lines()->with(['account:id,code,name'])->get();

        $rows = $lines->map(function (BudgetLine $line) use ($elapsedDays, $remainingDays) {
            // Daily run rate based on actuals so far
            $dailyRunRate = $elapsedDays > 0
                ? bcdiv((string) $line->actual_amount, (string) $elapsedDays, 4)
                : '0.0000';

            // Projected remaining spend = run rate × remaining days
            $projectedRemaining = bcmul($dailyRunRate, (string) $remainingDays, 4);
            $projectedTotal     = bcadd((string) $line->actual_amount, $projectedRemaining, 4);
            $projectedVariance  = bcsub((string) $line->total_amount, $projectedTotal, 4);

            return [
                'id'                  => $line->id,
                'name'                => $line->name,
                'budgeted'            => $line->total_amount,
                'actual_to_date'      => $line->actual_amount,
                'daily_run_rate'      => $dailyRunRate,
                'projected_remaining' => $projectedRemaining,
                'projected_total'     => $projectedTotal,
                'projected_variance'  => $projectedVariance,
                'on_track'            => bccomp($projectedTotal, (string) $line->total_amount, 4) <= 0,
            ];
        });

        $totalBudgeted     = (string) $budget->getTotalBudgeted();
        $totalActual       = (string) $budget->getTotalActual();
        $dailyRunRateTotal = $elapsedDays > 0
            ? bcdiv($totalActual, (string) $elapsedDays, 4)
            : '0.0000';
        $projectedTotal = bcadd($totalActual, bcmul($dailyRunRateTotal, (string) $remainingDays, 4), 4);

        return [
            'budget_id'           => $budget->id,
            'budget_uuid'         => $budget->uuid,
            'budget_name'         => $budget->name,
            'currency_code'       => $budget->currency_code,
            'period_start'        => $periodStart->toDateString(),
            'period_end'          => $periodEnd->toDateString(),
            'total_days'          => $totalDays,
            'elapsed_days'        => $elapsedDays,
            'remaining_days'      => $remainingDays,
            'progress_percent'    => $progressPct,
            'total_budgeted'      => $totalBudgeted,
            'total_actual'        => $totalActual,
            'daily_run_rate'      => $dailyRunRateTotal,
            'projected_year_end'  => $projectedTotal,
            'projected_variance'  => bcsub($totalBudgeted, $projectedTotal, 4),
            'on_track'            => bccomp($projectedTotal, $totalBudgeted, 4) <= 0,
            'lines'               => $rows->toArray(),
        ];
    }
}
