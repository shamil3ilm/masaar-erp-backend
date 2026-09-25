<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Expense;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseCategory;
use App\Services\Expense\ExpenseCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    use ReportsBusinessRules;
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ExpenseCategoryService $categories
    ) {}

    /**
     * List expense categories (tree structure).
     */
    public function index(Request $request): JsonResponse
    {
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;

        if ($request->boolean('flat', false)) {
            // Flat list for dropdowns
            return $this->success($this->categories->allCategories($isActive));
        }

        return $this->paginated($this->categories->paginateRootCategories($isActive, $request->integer('per_page', 15)));
    }

    /**
     * Create a new expense category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', $this->ownedBy('expense_categories')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('expense_categories', 'code')->where('organization_id', auth()->user()->organization_id)],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
            'default_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'is_active' => ['nullable', 'boolean'],
            'requires_receipt' => ['nullable', 'boolean'],
            'budget_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        return $this->created($this->categories->create($validated), 'Expense category created successfully');
    }

    /**
     * Show a single expense category.
     */
    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->load(['parent', 'children', 'defaultAccount:id,code,name']);

        return $this->success($expenseCategory);
    }

    /**
     * Update an expense category.
     */
    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', $this->ownedBy('expense_categories')],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
            'default_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'is_active' => ['nullable', 'boolean'],
            'requires_receipt' => ['nullable', 'boolean'],
            'budget_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $category = $this->categories->update($expenseCategory, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($category, 'Expense category updated successfully');
    }

    /**
     * Delete an expense category.
     */
    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        try {
            $this->categories->delete($expenseCategory);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Expense category deleted successfully');
    }
}
