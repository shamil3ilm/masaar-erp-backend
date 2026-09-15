<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Expense;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseReport;
use App\Services\Expense\ExpenseReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseReportController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ExpenseReportService $reportService
    ) {}

    /**
     * List expense reports.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->reportService->paginateReports(
            $request->only(['status', 'employee_id', 'start_date', 'end_date', 'search']),
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Create a new expense report.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'expense_ids' => ['nullable', 'array'],
            'expense_ids.*' => [$this->ownedBy('expenses')],
        ]);

        try {
            $report = $this->reportService->create([
                ...$validated,
                'organization_id' => $this->organizationId($request),
            ]);

            return $this->created($report, 'Expense report created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single expense report.
     */
    public function show(ExpenseReport $expenseReport): JsonResponse
    {
        $expenseReport->load([
            'reportItems.expense.category:id,name',
            'reportItems.expense.receipts',
            'approvedBy:id,name',
        ]);

        return $this->success($expenseReport);
    }

    /**
     * Add expenses to a report.
     */
    public function addExpenses(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        $validated = $request->validate([
            'expense_ids' => ['required', 'array', 'min:1'],
            'expense_ids.*' => [$this->ownedBy('expenses')],
        ]);

        return $this->tryAction(
            fn() => $this->reportService->addExpenses($expenseReport, $validated['expense_ids']),
            'Expenses added to report successfully',
            'ADD_EXPENSES_FAILED',
            400
        );
    }

    /**
     * Submit a report for approval.
     */
    public function submit(ExpenseReport $expenseReport): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->reportService->submit($expenseReport),
            'Report submitted for approval',
            'SUBMIT_FAILED',
            400
        );
    }

    /**
     * Approve a report.
     */
    public function approve(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        $validated = $request->validate([
            'item_approvals' => ['nullable', 'array'],
            'item_approvals.*.expense_id' => ['required_with:item_approvals', $this->ownedBy('expenses')],
            'item_approvals.*.approved_amount' => ['required_with:item_approvals', 'numeric', 'min:0'],
            'item_approvals.*.notes' => ['nullable', 'string'],
        ]);

        return $this->tryAction(
            fn() => $this->reportService->approve($expenseReport, $request->user()->id, $validated['item_approvals'] ?? null),
            'Report approved successfully',
            'APPROVAL_FAILED',
            400
        );
    }

    /**
     * Reject a report.
     */
    public function reject(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->tryAction(
            fn() => $this->reportService->reject($expenseReport, $validated['reason']),
            'Report rejected',
            'REJECTION_FAILED',
            400
        );
    }

    /**
     * Reimburse an approved report.
     */
    public function reimburse(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        $validated = $request->validate([
            'reimbursed_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->tryAction(
            fn() => $this->reportService->reimburse($expenseReport, $validated),
            'Report reimbursed successfully',
            'REIMBURSE_FAILED',
            400
        );
    }
}
