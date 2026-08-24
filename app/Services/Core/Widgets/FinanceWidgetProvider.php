<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

use App\Models\Sales\Invoice;
use Illuminate\Support\Carbon;

/**
 * Dashboard widgets that report on cash, profitability, and payables.
 */
class FinanceWidgetProvider extends WidgetProvider
{
    public function getCashBalance(array $config = []): array
    {
        // This would need BankAccount/JournalEntry data
        return [
            'value' => 0,
            'label' => 'Cash Balance',
            'message' => 'Connect bank accounts to see balance',
        ];
    }

    public function getProfitLossSummary(array $config = []): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $revenue = Invoice::where('organization_id', $this->organizationId)
            ->where('invoice_date', '>=', $startOfMonth)
            ->whereNotIn('status', ['draft', 'voided'])
            ->sum('total');

        // Expenses would come from Bills/Purchases
        $expenses = 0;

        return [
            'revenue' => (float) $revenue,
            'expenses' => (float) $expenses,
            'net_profit' => (float) ($revenue - $expenses),
            'label' => 'P&L Summary (MTD)',
        ];
    }

    public function getRevenueVsExpense(array $config = []): array
    {
        $months = [];
        $revenue = [];
        $expenses = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = $date->format('M Y');

            $monthRevenue = Invoice::where('organization_id', $this->organizationId)
                ->whereYear('invoice_date', $date->year)
                ->whereMonth('invoice_date', $date->month)
                ->whereNotIn('status', ['draft', 'voided'])
                ->sum('total');

            $revenue[] = (float) $monthRevenue;
            $expenses[] = 0; // Would need expense data
        }

        return [
            'labels' => $months,
            'datasets' => [
                ['label' => 'Revenue', 'data' => $revenue],
                ['label' => 'Expenses', 'data' => $expenses],
            ],
        ];
    }

    public function getOutstandingPayables(array $config = []): array
    {
        // Would need Bill model
        return [
            'value' => 0,
            'label' => 'Outstanding Payables',
        ];
    }
}
