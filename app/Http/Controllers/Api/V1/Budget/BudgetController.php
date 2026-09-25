<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Accounting\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BudgetController extends Controller
{
    use ReportsBusinessRules;
    use ValidatesOwnedRows;

    public function __construct(
        private readonly BudgetService $budgetService,
    ) {}

    // ----------------------------------------------------------------
    // Budgets CRUD
    // ----------------------------------------------------------------

    /**
     * GET /budget/budgets
     */
    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status'         => $request->filled('status') ? $request->input('status') : null,
            'budget_type'    => $request->filled('budget_type') ? $request->input('budget_type') : null,
            'fiscal_year_id' => $request->filled('fiscal_year_id') ? $request->integer('fiscal_year_id') : null,
            'search'         => $request->filled('search') ? $request->input('search') : null,
        ], fn ($value) => $value !== null);

        return $this->paginated($this->budgetService->paginateBudgets($filters, $request->integer('per_page', 20)));
    }

    /**
     * POST /budget/budgets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id'         => ['nullable', $this->ownedBy('fiscal_years')],
            'name'                   => ['required', 'string', 'max:255'],
            'budget_type'            => ['nullable', 'string', 'in:annual,quarterly,project,department'],
            'period_start'           => ['required', 'date'],
            'period_end'             => ['required', 'date', 'after_or_equal:period_start'],
            'currency_code'          => ['nullable', 'string', 'size:3'],
            'description'            => ['nullable', 'string'],
            'lines'                  => ['nullable', 'array'],
            'lines.*.name'           => ['required_with:lines', 'string', 'max:255'],
            'lines.*.account_id'     => ['nullable', $this->ownedBy('chart_of_accounts')],
            'lines.*.cost_center_id' => ['nullable', $this->ownedBy('cost_centers')],
            'lines.*.department_id'  => ['nullable', $this->ownedBy('departments')],
            'lines.*.q1_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.q2_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.q3_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.q4_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $budget = $this->budgetService->createBudget($validated, $validated['lines'] ?? [], auth()->id());

        return $this->created($budget->load(['lines', 'fiscalYear']));
    }

    /**
     * GET /budget/budgets/{id}
     */
    public function show(int $id): JsonResponse
    {
        $budget = $this->budgetService->findBudgetWithDetails($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        return $this->success($budget);
    }

    /**
     * PUT /budget/budgets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $budget = $this->budgetService->findBudget($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', $this->ownedBy('fiscal_years')],
            'name'           => ['sometimes', 'string', 'max:255'],
            'budget_type'    => ['sometimes', 'string', 'in:annual,quarterly,project,department'],
            'period_start'   => ['sometimes', 'date'],
            'period_end'     => ['sometimes', 'date', 'after_or_equal:period_start'],
            'currency_code'  => ['sometimes', 'string', 'size:3'],
            'description'    => ['nullable', 'string'],
        ]);

        try {
            $budget = $this->budgetService->updateBudget($budget, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($budget);
    }

    /**
     * DELETE /budget/budgets/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $budget = $this->budgetService->findBudget($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        try {
            $this->budgetService->deleteBudget($budget);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Budget deleted successfully.');
    }

    // ----------------------------------------------------------------
    // Lifecycle transitions
    // ----------------------------------------------------------------

    /**
     * POST /budget/budgets/{id}/submit
     */
    public function submit(int $id): JsonResponse
    {
        return $this->transition($id, 'submitBudget', 'Budget submitted for approval.');
    }

    /**
     * POST /budget/budgets/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        return $this->transition($id, 'approveBudget', 'Budget approved.');
    }

    /**
     * POST /budget/budgets/{id}/activate
     */
    public function activate(int $id): JsonResponse
    {
        return $this->transition($id, 'activateBudget', 'Budget activated.');
    }

    // ----------------------------------------------------------------
    // Lines
    // ----------------------------------------------------------------

    /**
     * POST /budget/budgets/{id}/lines
     */
    public function storeLine(Request $request, int $id): JsonResponse
    {
        $budget = $this->budgetService->findBudget($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'account_id'     => ['nullable', $this->ownedBy('chart_of_accounts')],
            'cost_center_id' => ['nullable', $this->ownedBy('cost_centers')],
            'department_id'  => ['nullable', $this->ownedBy('departments')],
            'q1_amount'      => ['nullable', 'numeric', 'min:0'],
            'q2_amount'      => ['nullable', 'numeric', 'min:0'],
            'q3_amount'      => ['nullable', 'numeric', 'min:0'],
            'q4_amount'      => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $line = $this->budgetService->addLine($budget, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->created($line);
    }

    /**
     * PUT /budget/budgets/{budgetId}/lines/{lineId}
     */
    public function updateLine(Request $request, int $budgetId, int $lineId): JsonResponse
    {
        $budget = $this->budgetService->findBudget($budgetId);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $line = $this->budgetService->findLine($budget, $lineId);

        if ($line === null) {
            return $this->notFound('Budget line not found.');
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'account_id'     => ['nullable', $this->ownedBy('chart_of_accounts')],
            'cost_center_id' => ['nullable', $this->ownedBy('cost_centers')],
            'department_id'  => ['nullable', $this->ownedBy('departments')],
            'q1_amount'      => ['sometimes', 'numeric', 'min:0'],
            'q2_amount'      => ['sometimes', 'numeric', 'min:0'],
            'q3_amount'      => ['sometimes', 'numeric', 'min:0'],
            'q4_amount'      => ['sometimes', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $line = $this->budgetService->updateLine($line, $validated, auth()->id());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($line->load(['account', 'costCenter', 'department']));
    }

    /**
     * DELETE /budget/budgets/{budgetId}/lines/{lineId}
     */
    public function destroyLine(int $budgetId, int $lineId): JsonResponse
    {
        $budget = $this->budgetService->findBudget($budgetId);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $line = $this->budgetService->findLine($budget, $lineId);

        if ($line === null) {
            return $this->notFound('Budget line not found.');
        }

        try {
            $this->budgetService->removeLine($budget, $line);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Budget line deleted successfully.');
    }

    // ----------------------------------------------------------------
    // Revisions
    // ----------------------------------------------------------------

    /**
     * POST /budget/budgets/{id}/revisions
     */
    public function storeRevision(Request $request, int $id): JsonResponse
    {
        $budget = $this->budgetService->findBudget($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        // A line total is the sum of its quarters, so only quarters are revised.
        $validated = $request->validate([
            'reason'                         => ['required', 'string'],
            'line_changes'                   => ['required', 'array', 'min:1'],
            'line_changes.*.budget_line_id'  => ['required', $this->ownedThrough('budget_lines', 'budget_id', 'budgets')],
            'line_changes.*.field_changed'   => ['required', 'string', 'in:q1_amount,q2_amount,q3_amount,q4_amount'],
            'line_changes.*.new_value'       => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $revision = $this->budgetService->reviseBudget(
                $budget,
                $validated['line_changes'],
                $validated['reason'],
                auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'REVISION_ERROR', 422);
        }

        return $this->created($revision->load(['lines', 'creator']));
    }

    // ----------------------------------------------------------------
    // Commitments
    // ----------------------------------------------------------------

    /**
     * GET /budget/budgets/{id}/commitments
     */
    public function commitments(int $id): JsonResponse
    {
        $budget = $this->budgetService->findBudget($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        return $this->success($this->budgetService->commitmentsFor($budget));
    }

    // ----------------------------------------------------------------
    // Reports
    // ----------------------------------------------------------------

    /**
     * GET /budget/budgets/vs-actual
     */
    public function vsActual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'budget_id' => ['required', $this->ownedBy('budgets')],
        ]);

        $budget = $this->budgetService->findBudget((int) $validated['budget_id']);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        return $this->success($this->budgetService->getBudgetVsActual($budget));
    }

    /**
     * Runs a lifecycle transition on the budget and reports a refused one as INVALID_TRANSITION.
     *
     * @param  'submitBudget'|'approveBudget'|'activateBudget'  $method
     */
    private function transition(int $id, string $method, string $message): JsonResponse
    {
        $budget = $this->budgetService->findBudget($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        try {
            $budget = $this->budgetService->{$method}($budget, auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_TRANSITION', 422);
        }

        return $this->success($budget, $message);
    }
}
