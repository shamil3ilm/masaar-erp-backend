<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Controllers\Controller;
use App\Models\Accounting\FiscalYear;
use App\Services\Accounting\ChartOfAccountsService;
use App\Services\Accounting\FiscalYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalYearController extends Controller
{
    use ReportsBusinessRules;

    public function __construct(
        private readonly FiscalYearService $fiscalYears,
        private readonly ChartOfAccountsService $chartOfAccounts,
    ) {}

    /**
     * List fiscal years.
     */
    public function index(): JsonResponse
    {
        return $this->success($this->fiscalYears->list());
    }

    /**
     * Get current fiscal year.
     */
    public function current(): JsonResponse
    {
        $fiscalYear = $this->fiscalYears->current(auth()->user()->organization_id);

        if (!$fiscalYear) {
            return $this->error('No current fiscal year set', 'NOT_FOUND', 404);
        }

        return $this->success($fiscalYear);
    }

    /**
     * Show single fiscal year.
     */
    public function show(FiscalYear $fiscalYear): JsonResponse
    {
        $fiscalYear->load(['periods', 'closedByUser:id,name']);

        return $this->success($fiscalYear);
    }

    /**
     * Create new fiscal year, optionally current and with monthly periods.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['boolean'],
            'create_periods' => ['boolean'],
        ]);

        try {
            $fiscalYear = $this->fiscalYears->create(auth()->user()->organization_id, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($fiscalYear, 'Fiscal year created successfully', 201);
    }

    /**
     * Update fiscal year.
     */
    public function update(Request $request, FiscalYear $fiscalYear): JsonResponse
    {
        // A closed year is refused before the payload is validated, so it
        // reports CLOSED whatever was sent; the service re-checks on the locked row.
        if ($fiscalYear->is_closed) {
            return $this->error('Closed fiscal years cannot be modified', 'CLOSED', 400);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
        ]);

        try {
            $fiscalYear = $this->fiscalYears->update($fiscalYear, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($fiscalYear, 'Fiscal year updated successfully');
    }

    /**
     * Set fiscal year as current.
     */
    public function setCurrent(FiscalYear $fiscalYear): JsonResponse
    {
        try {
            $fiscalYear = $this->fiscalYears->setCurrent($fiscalYear);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($fiscalYear, 'Fiscal year set as current');
    }

    /**
     * Close fiscal year.
     */
    public function close(FiscalYear $fiscalYear): JsonResponse
    {
        try {
            $fiscalYear = $this->fiscalYears->close($fiscalYear, auth()->id());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($fiscalYear, 'Fiscal year closed successfully');
    }

    /**
     * Delete fiscal year (only if no transactions).
     */
    public function destroy(FiscalYear $fiscalYear): JsonResponse
    {
        try {
            $this->fiscalYears->delete($fiscalYear);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Fiscal year deleted successfully');
    }

    /**
     * Initialize organization with chart of accounts.
     */
    public function initializeChartOfAccounts(): JsonResponse
    {
        try {
            $this->chartOfAccounts->initializeDefaults(auth()->user()->organization_id);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Chart of accounts initialized successfully');
    }
}
