<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\FiscalYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private AccountBalanceService $balanceService,
        private FiscalYearService $fiscalYears,
    ) {}

    /**
     * Get trial balance.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', $this->ownedBy('fiscal_years')],
            'as_of_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $fiscalYearId = (int) ($validated['fiscal_year_id'] ?? $this->getCurrentFiscalYearId());

        $trialBalance = $this->balanceService->getTrialBalance(
            auth()->user()->organization_id,
            $fiscalYearId,
            $validated['as_of_date'] ?? null
        );

        return $this->success($trialBalance);
    }

    /**
     * Get balance sheet summary.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', $this->ownedBy('fiscal_years')],
            'as_of_date' => ['nullable', 'date'],
        ]);

        $fiscalYearId = (int) ($validated['fiscal_year_id'] ?? $this->getCurrentFiscalYearId());

        $balanceSheet = $this->balanceService->getBalanceSheetSummary(
            auth()->user()->organization_id,
            $fiscalYearId,
            $validated['as_of_date'] ?? null
        );

        return $this->success($balanceSheet);
    }

    /**
     * Get income statement (P&L) summary.
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', $this->ownedBy('fiscal_years')],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $fiscalYearId = (int) ($validated['fiscal_year_id'] ?? $this->getCurrentFiscalYearId());

        $incomeStatement = $this->balanceService->getIncomeStatementSummary(
            auth()->user()->organization_id,
            $fiscalYearId,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return $this->success($incomeStatement);
    }

    private function getCurrentFiscalYearId(): int
    {
        return $this->fiscalYears->currentId(auth()->user()->organization_id);
    }
}
