<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\Reports\SavedReport;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\ReportExecutionService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\SavedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{
    public function __construct(
        protected FinancialReportService $financialService,
        protected InventoryReportService $inventoryService,
        protected SalesReportService $salesService,
        protected ReportExportService $exportService,
        protected ReportDataService $reportData,
        protected SavedReportService $savedReports,
        protected ReportExecutionService $executions,
    ) {}

    /**
     * Get available report types.
     */
    public function types(): JsonResponse
    {
        return $this->success([
            'data' => SavedReport::getReportTypes(),
            'schedules' => SavedReport::getScheduleOptions(),
            'formats' => SavedReport::getExportFormats(),
        ]);
    }

    // ==========================================
    // Financial Reports
    // ==========================================

    /**
     * Get Balance Sheet.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'as_of_date' => 'required|date',
            'compare_to' => 'nullable|date',
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
        ]);

        $data = $this->financialService->getBalanceSheet(
            Carbon::parse($request->get('as_of_date'))
        );

        return $this->success($data);
    }

    /**
     * Get Income Statement (P&L).
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data = $this->financialService->getProfitAndLoss(
            Carbon::parse($request->get('start_date')),
            Carbon::parse($request->get('end_date'))
        );

        return $this->success($data);
    }

    /**
     * Get Trial Balance.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $request->validate([
            'as_of_date' => 'required|date',
            'show_zero_balances' => 'nullable|boolean',
        ]);

        $data = $this->financialService->getTrialBalance(
            Carbon::parse($request->get('as_of_date'))
        );

        return $this->success($data);
    }

    /**
     * Get Cash Flow Statement.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data = $this->financialService->getCashFlow(
            Carbon::parse($request->get('start_date')),
            Carbon::parse($request->get('end_date'))
        );

        return $this->success($data);
    }

    /**
     * Get Aged Receivables.
     */
    public function agedReceivables(Request $request): JsonResponse
    {
        $data = $this->financialService->getReceivableAging();

        return $this->success($data);
    }

    /**
     * Get Aged Payables.
     */
    public function agedPayables(Request $request): JsonResponse
    {
        $data = $this->financialService->getPayableAging();

        return $this->success($data);
    }

    // ==========================================
    // Inventory Reports
    // ==========================================

    /**
     * Get Stock Valuation Report.
     */
    public function stockValuation(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->inventoryService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->inventoryService->generateStockValuation(
            $request->get('warehouse_id'),
            $request->get('category_id'),
            $request->get('valuation_method')
        );

        return $this->success($data);
    }

    /**
     * Get Stock Movement Report.
     */
    public function stockMovement(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'product_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'movement_type' => 'nullable|string',
        ]);

        $user = $request->user();

        $this->inventoryService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->inventoryService->generateStockMovement(
            $request->get('start_date'),
            $request->get('end_date'),
            $request->get('product_id'),
            $request->get('warehouse_id'),
            $request->get('movement_type')
        );

        return $this->success($data);
    }

    /**
     * Get Low Stock Report.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->inventoryService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->inventoryService->generateLowStockReport(
            $request->get('warehouse_id')
        );

        return $this->success($data);
    }

    /**
     * Get Inventory Turnover Report.
     */
    public function inventoryTurnover(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $this->inventoryService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->inventoryService->generateInventoryTurnover(
            $request->get('start_date'),
            $request->get('end_date')
        );

        return $this->success($data);
    }

    /**
     * Get Batch Expiry Report.
     */
    public function batchExpiry(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->inventoryService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->inventoryService->generateExpiryReport(
            $request->get('days_ahead', 90),
            $request->get('warehouse_id')
        );

        return $this->success($data);
    }

    // ==========================================
    // Sales Reports
    // ==========================================

    /**
     * Get Sales by Customer Report.
     */
    public function salesByCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'customer_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $user = $request->user();

        $this->salesService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->salesService->generateSalesByCustomer(
            $request->get('start_date'),
            $request->get('end_date'),
            $request->get('customer_id'),
            $request->get('limit', 50)
        );

        return $this->success($data);
    }

    /**
     * Get Sales by Product Report.
     */
    public function salesByProduct(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $user = $request->user();

        $this->salesService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->salesService->generateSalesByProduct(
            $request->get('start_date'),
            $request->get('end_date'),
            $request->get('category_id'),
            $request->get('product_id'),
            $request->get('limit', 50)
        );

        return $this->success($data);
    }

    /**
     * Get Sales by Salesperson Report.
     */
    public function salesBySalesperson(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $this->salesService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->salesService->generateSalesBySalesperson(
            $request->get('start_date'),
            $request->get('end_date')
        );

        return $this->success($data);
    }

    /**
     * Get Sales Trend Report.
     */
    public function salesTrend(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|string|in:day,week,month',
        ]);

        $user = $request->user();

        $this->salesService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->salesService->generateSalesTrend(
            $request->get('start_date'),
            $request->get('end_date'),
            $request->get('group_by', 'day')
        );

        return $this->success($data);
    }

    /**
     * Get Sales Summary Dashboard.
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $this->salesService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->salesService->generateSalesSummary(
            $request->get('start_date'),
            $request->get('end_date')
        );

        return $this->success($data);
    }

    // ==========================================
    // Export
    // ==========================================

    /**
     * Export report.
     */
    public function export(Request $request): JsonResponse|BinaryFileResponse
    {
        $request->validate([
            'report_type' => ['required', 'string', Rule::in(ReportDataService::TYPES)],
            'format' => 'required|string|in:pdf,xlsx,csv,json',
            'parameters' => 'required|array',
        ]);

        $user = $request->user();
        $reportType = $request->get('report_type');
        $format = $request->get('format');
        $parameters = $request->get('parameters');

        $data = $this->reportData->generate($reportType, $parameters, $user->organization_id, $user->current_branch_id);

        $execution = $this->executions->startManual($user, $reportType, $format, $parameters);

        $this->exportService->setContext($user->organization_id, $this->executions->organizationData($user->organization_id));

        try {
            $filePath = $this->exportService->export($reportType, $data, $format, $execution);

            if ($request->boolean('download')) {
                return response()->download(
                    storage_path('app/'.$filePath),
                    basename($filePath)
                )->deleteFileAfterSend(false);
            }

            return $this->success([
                'execution_id' => $execution->id,
                'file_path' => $filePath,
                'download_url' => route('api.v1.reports.download', $execution->id),
                'expires_at' => $execution->expires_at?->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError();
        }
    }

    /**
     * Download exported report.
     */
    public function download(Request $request, int $executionId): BinaryFileResponse|JsonResponse
    {
        $execution = $this->executions->findForOrganization($request->user()->organization_id, $executionId);

        if (! $execution->isFileAvailable()) {
            return $this->notFound('File not available or expired');
        }

        return response()->download(
            storage_path('app/'.$execution->file_path),
            basename($execution->file_path)
        );
    }

    // ==========================================
    // Saved Reports
    // ==========================================

    /**
     * List saved reports.
     */
    public function savedReports(Request $request): JsonResponse
    {
        return $this->success($this->savedReports->visibleTo($request->user()));
    }

    /**
     * Create saved report.
     */
    public function createSavedReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'report_type' => ['required', 'string', Rule::in(ReportDataService::TYPES)],
            'parameters' => 'nullable|array',
            'columns' => 'nullable|array',
            'schedule_frequency' => 'nullable|string|in:daily,weekly,monthly,quarterly',
            'schedule_day' => 'nullable|string|max:10',
            'schedule_time' => 'nullable|date_format:H:i',
            'recipients' => 'nullable|array',
            'recipients.*' => 'email',
            'export_format' => 'nullable|string|in:pdf,xlsx,csv,json',
            'is_shared' => 'nullable|boolean',
        ]);

        return $this->created($this->savedReports->create($request->user(), $validated));
    }

    /**
     * Update saved report.
     */
    public function updateSavedReport(Request $request, int $id): JsonResponse
    {
        $report = $this->savedReports->findOwned($request->user(), $id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parameters' => 'nullable|array',
            'columns' => 'nullable|array',
            'schedule_frequency' => 'nullable|string|in:daily,weekly,monthly,quarterly',
            'schedule_day' => 'nullable|string',
            'schedule_time' => 'nullable|date_format:H:i',
            'recipients' => 'nullable|array',
            'export_format' => 'nullable|string|in:pdf,xlsx,csv,json',
            'is_shared' => 'nullable|boolean',
            'is_scheduled' => 'nullable|boolean',
        ]);

        return $this->success($this->savedReports->update($report, $validated));
    }

    /**
     * Delete saved report.
     */
    public function deleteSavedReport(Request $request, int $id): JsonResponse
    {
        $this->savedReports->delete($this->savedReports->findOwned($request->user(), $id));

        return $this->success(null, 'Report deleted');
    }

    /**
     * Run saved report.
     */
    public function runSavedReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $report = $this->savedReports->findRunnable($user, $id);

        $data = $this->reportData->generate(
            $report->report_type,
            $report->parameters ?? [],
            $user->organization_id,
            $user->current_branch_id
        );

        $this->savedReports->markRun($report);

        return $this->success($data);
    }

    /**
     * Get report execution history.
     */
    public function executionHistory(Request $request): JsonResponse
    {
        return $this->paginated($this->executions->paginateForUser($request->user(), $request->get('per_page', 20)));
    }
}
