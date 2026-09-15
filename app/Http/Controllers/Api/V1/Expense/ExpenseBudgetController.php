<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Expense;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseBudget;
use App\Services\Expense\ExpenseBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseBudgetController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ExpenseBudgetService $budgetService
    ) {}

    /**
     * List expense budgets.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];

        foreach (['year', 'month'] as $key) {
            if ($request->has($key)) {
                $filters[$key] = $request->integer($key);
            }
        }

        foreach (['category_id', 'department_id'] as $key) {
            if ($request->has($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        return $this->paginated($this->budgetService->paginateBudgets($filters, $request->integer('per_page', 20)));
    }

    /**
     * Create or update a budget.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', $this->ownedBy('expense_categories')],
            'department_id' => ['nullable', $this->ownedBy('departments')],
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'alert_at_80' => ['nullable', 'boolean'],
            'alert_at_100' => ['nullable', 'boolean'],
        ]);

        $budget = $this->budgetService->create([
            ...$validated,
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->created($budget->load('category:id,name,code'), 'Budget saved successfully');
    }

    /**
     * Show a single budget.
     */
    public function show(ExpenseBudget $expenseBudget): JsonResponse
    {
        $expenseBudget->load('category:id,name,code');

        $data = $expenseBudget->toArray();
        $data['remaining'] = $expenseBudget->getRemainingBudget();
        $data['utilization_percentage'] = $expenseBudget->getUtilizationPercentage();
        $data['is_exceeded'] = $expenseBudget->isExceeded();

        return $this->success($data);
    }

    /**
     * Update a budget.
     */
    public function update(Request $request, ExpenseBudget $expenseBudget): JsonResponse
    {
        $validated = $request->validate([
            'budget_amount' => ['sometimes', 'numeric', 'min:0'],
            'alert_at_80' => ['nullable', 'boolean'],
            'alert_at_100' => ['nullable', 'boolean'],
        ]);

        return $this->success($this->budgetService->update($expenseBudget, $validated), 'Budget updated successfully');
    }

    /**
     * Delete a budget.
     */
    public function destroy(ExpenseBudget $expenseBudget): JsonResponse
    {
        $this->budgetService->delete($expenseBudget);

        return $this->success(null, 'Budget deleted successfully');
    }

    /**
     * Check budget for a potential expense.
     */
    public function checkBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', $this->ownedBy('expense_categories')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
        ]);

        $result = $this->budgetService->checkBudget(
            $this->organizationId($request),
            (int) $validated['category_id'],
            (float) $validated['amount'],
            $validated['date'] ?? null
        );

        return $this->success($result);
    }

    /**
     * Get budget utilization summary.
     */
    public function utilization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $result = $this->budgetService->getUtilization(
            $this->organizationId($request),
            (int) $validated['year'],
            isset($validated['month']) ? (int) $validated['month'] : null
        );

        return $this->success($result);
    }
}
