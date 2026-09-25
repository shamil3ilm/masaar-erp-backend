<?php

declare(strict_types=1);

namespace App\Services\Expense;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseReport;
use App\Models\Expense\ExpenseReportItem;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseReportService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a new expense report.
     */
    public function create(array $data): ExpenseReport
    {
        return DB::transaction(function () use ($data) {
            $report = ExpenseReport::create([
                'organization_id' => $data['organization_id'],
                // ER-2026-000001 from a counter of its own: travel expense reports
                // are numbered ER- from theirs.
                'report_number' => $this->numberGenerator->generate('expense_report', 'ER-{year}-{number:6}', $data['organization_id']),
                'employee_id' => $data['employee_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
            ]);

            // Add initial expenses if provided
            if (!empty($data['expense_ids'])) {
                $this->addExpenses($report, $data['expense_ids']);
            }

            return $report->fresh(['reportItems.expense']);
        });
    }

    /**
     * The organization's expense reports, newest first.
     *
     * @param  array<string, mixed>  $filters  status, employee_id, start_date, end_date and search, each applied when present
     */
    public function paginateReports(array $filters, int $perPage): LengthAwarePaginator
    {
        return ExpenseReport::with(['approvedBy:id,name'])
            ->orderByDesc('created_at')
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('employee_id', $filters), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(array_key_exists('start_date', $filters), fn ($q) => $q->whereDate('period_start', '>=', $filters['start_date']))
            ->when(array_key_exists('end_date', $filters), fn ($q) => $q->whereDate('period_end', '<=', $filters['end_date']))
            ->when(array_key_exists('search', $filters), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('report_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);
    }

    /**
     * Add expenses to a report.
     *
     * This and each transition below check the report's status on its locked
     * row, so a report two requests act on at once changes once.
     */
    public function addExpenses(ExpenseReport $report, array $expenseIds): ExpenseReport
    {
        return $report->lockForTransition(function (ExpenseReport $locked) use ($expenseIds): ExpenseReport {
            if ($locked->status !== ExpenseReport::STATUS_DRAFT) {
                throw new InvalidArgumentException('Can only add expenses to draft reports.');
            }

            foreach ($expenseIds as $expenseId) {
                $expense = Expense::findOrFail($expenseId);

                // Verify expense belongs to same organization
                if ($expense->organization_id !== $locked->organization_id) {
                    throw new InvalidArgumentException("Expense #{$expenseId} does not belong to this organization.");
                }

                // Check if expense is already in a report
                $existsInReport = ExpenseReportItem::where('expense_id', $expenseId)->exists();
                if ($existsInReport) {
                    throw new InvalidArgumentException("Expense #{$expenseId} is already in another report.");
                }

                // Verify expense is in valid status
                if (!in_array($expense->status, [Expense::STATUS_APPROVED, Expense::STATUS_DRAFT, Expense::STATUS_SUBMITTED])) {
                    throw new InvalidArgumentException("Expense #{$expenseId} has invalid status for reporting.");
                }

                $locked->reportItems()->create([
                    'expense_id' => $expenseId,
                    'approved_amount' => null,
                ]);
            }

            $locked->recalculateTotals();

            return $locked->fresh(['reportItems.expense']);
        });
    }

    /**
     * Submit a report for approval.
     */
    public function submit(ExpenseReport $report): ExpenseReport
    {
        return $report->lockForTransition(function (ExpenseReport $locked): ExpenseReport {
            if ($locked->status !== ExpenseReport::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft reports can be submitted.');
            }

            if ($locked->reportItems()->count() === 0) {
                throw new InvalidArgumentException('Cannot submit an empty report.');
            }

            $locked->update(['status' => ExpenseReport::STATUS_SUBMITTED]);

            return $locked->fresh();
        });
    }

    /**
     * Approve a submitted report.
     */
    public function approve(ExpenseReport $report, int $approverId, ?array $itemApprovals = null): ExpenseReport
    {
        return $report->lockForTransition(function (ExpenseReport $locked) use ($approverId, $itemApprovals): ExpenseReport {
            if ($locked->status !== ExpenseReport::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Only submitted reports can be approved.');
            }

            // Apply individual item approvals if provided
            if ($itemApprovals) {
                foreach ($itemApprovals as $approval) {
                    ExpenseReportItem::where('report_id', $locked->id)
                        ->where('expense_id', $approval['expense_id'])
                        ->update([
                            'approved_amount' => $approval['approved_amount'],
                            'notes' => $approval['notes'] ?? null,
                        ]);
                }
            } else {
                // Auto-approve all at expense total amounts
                $locked->reportItems()->with('expense')->each(function ($item) {
                    $item->update([
                        'approved_amount' => $item->expense->total_amount,
                    ]);
                });
            }

            $approvedAmount = $locked->reportItems()->sum('approved_amount');

            $locked->update([
                'status' => ExpenseReport::STATUS_APPROVED,
                'approved_amount' => $approvedAmount,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $locked->fresh(['reportItems.expense', 'approvedBy']);
        });
    }

    /**
     * Reject a submitted report.
     */
    public function reject(ExpenseReport $report, string $reason): ExpenseReport
    {
        return $report->lockForTransition(function (ExpenseReport $locked) use ($reason): ExpenseReport {
            if ($locked->status !== ExpenseReport::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Only submitted reports can be rejected.');
            }

            $locked->update([
                'status' => ExpenseReport::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Mark a report as reimbursed/paid.
     */
    public function reimburse(ExpenseReport $report, array $data = []): ExpenseReport
    {
        return $report->lockForTransition(function (ExpenseReport $locked) use ($data): ExpenseReport {
            if ($locked->status !== ExpenseReport::STATUS_APPROVED) {
                throw new InvalidArgumentException('Only approved reports can be reimbursed.');
            }

            $reimbursedAmount = $data['reimbursed_amount'] ?? $locked->approved_amount;

            $locked->update([
                'status' => ExpenseReport::STATUS_PAID,
                'reimbursed_amount' => $reimbursedAmount,
                'paid_at' => now(),
            ]);

            // Mark associated expenses as paid
            $locked->reportItems()->with('expense')->each(function ($item) {
                $item->expense->update([
                    'status' => Expense::STATUS_PAID,
                    'paid_at' => now(),
                ]);
            });

            return $locked->fresh(['reportItems.expense']);
        });
    }
}
