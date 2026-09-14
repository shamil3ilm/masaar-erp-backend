<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

/**
 * The data behind each report type, for a request, a saved report or a
 * scheduled run alike.
 *
 * The financial reports read the organisation from the signed-in user, so a
 * caller outside a request signs in the report's owner first.
 */
class ReportDataService
{
    public const TYPES = [
        'balance_sheet',
        'income_statement',
        'profit_loss',
        'trial_balance',
        'cash_flow',
        'aged_receivables',
        'aged_payables',
        'stock_valuation',
        'stock_movement',
        'low_stock',
        'inventory_turnover',
        'batch_expiry',
        'sales_by_customer',
        'sales_by_product',
        'sales_by_salesperson',
        'sales_trend',
        'sales_summary',
    ];

    public function __construct(
        private FinancialReportService $financial,
        private InventoryReportService $inventory,
        private SalesReportService $sales,
    ) {}

    public function generate(string $reportType, array $parameters, int $organizationId, ?int $branchId = null): array
    {
        $this->inventory->setContext($organizationId, $branchId);
        $this->sales->setContext($organizationId, $branchId);

        $startDate = $parameters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $parameters['end_date'] ?? now()->format('Y-m-d');

        return match ($reportType) {
            'balance_sheet' => $this->financial->getBalanceSheet(
                Carbon::parse($parameters['as_of_date'] ?? now())
            ),
            'income_statement', 'profit_loss' => $this->financial->getProfitAndLoss(
                Carbon::parse($startDate),
                Carbon::parse($endDate)
            ),
            'trial_balance' => $this->financial->getTrialBalance(
                Carbon::parse($parameters['as_of_date'] ?? now())
            ),
            'cash_flow' => $this->financial->getCashFlow(
                Carbon::parse($startDate),
                Carbon::parse($endDate)
            ),
            'aged_receivables' => $this->financial->getReceivableAging(),
            'aged_payables' => $this->financial->getPayableAging(),
            'stock_valuation' => $this->inventory->generateStockValuation(
                $parameters['warehouse_id'] ?? null,
                $parameters['category_id'] ?? null,
                $parameters['valuation_method'] ?? null
            ),
            'stock_movement' => $this->inventory->generateStockMovement(
                $startDate,
                $endDate,
                $parameters['product_id'] ?? null,
                $parameters['warehouse_id'] ?? null,
                $parameters['movement_type'] ?? null
            ),
            'low_stock' => $this->inventory->generateLowStockReport(
                $parameters['warehouse_id'] ?? null
            ),
            'inventory_turnover' => $this->inventory->generateInventoryTurnover($startDate, $endDate),
            'batch_expiry' => $this->inventory->generateExpiryReport(
                $parameters['days_ahead'] ?? 90,
                $parameters['warehouse_id'] ?? null
            ),
            'sales_by_customer' => $this->sales->generateSalesByCustomer(
                $startDate,
                $endDate,
                $parameters['customer_id'] ?? null,
                $parameters['limit'] ?? 50
            ),
            'sales_by_product' => $this->sales->generateSalesByProduct(
                $startDate,
                $endDate,
                $parameters['category_id'] ?? null,
                $parameters['product_id'] ?? null,
                $parameters['limit'] ?? 50
            ),
            'sales_by_salesperson' => $this->sales->generateSalesBySalesperson($startDate, $endDate),
            'sales_trend' => $this->sales->generateSalesTrend(
                $startDate,
                $endDate,
                $parameters['group_by'] ?? 'day'
            ),
            'sales_summary' => $this->sales->generateSalesSummary($startDate, $endDate),
            default => throw new \InvalidArgumentException("Unknown report type: {$reportType}"),
        };
    }
}
