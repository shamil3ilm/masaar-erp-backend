<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

use App\Models\Inventory\Product;
use App\Models\Sales\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widgets that report on sales: revenue, invoices, and customers.
 */
class SalesWidgetProvider extends WidgetProvider
{
    public function getTodaySales(array $config = []): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todaySales = Invoice::where('organization_id', $this->organizationId)
            ->whereDate('invoice_date', $today)
            ->whereNotIn('status', ['draft', 'voided'])
            ->sum('total');

        $yesterdaySales = Invoice::where('organization_id', $this->organizationId)
            ->whereDate('invoice_date', $yesterday)
            ->whereNotIn('status', ['draft', 'voided'])
            ->sum('total');

        $change = $yesterdaySales > 0
            ? (float) bcdiv(bcmul(bcsub((string) $todaySales, (string) $yesterdaySales, 4), '100', 4), (string) $yesterdaySales, 4)
            : ($todaySales > 0 ? 100 : 0);

        return [
            'value' => (float) $todaySales,
            'previous_value' => (float) $yesterdaySales,
            'change_percentage' => round($change, 1),
            'trend' => $change >= 0 ? 'up' : 'down',
            'label' => 'Today\'s Sales',
            'comparison_label' => 'vs Yesterday',
        ];
    }

    public function getMonthlySales(array $config = []): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $thisMonth = Invoice::where('organization_id', $this->organizationId)
            ->where('invoice_date', '>=', $startOfMonth)
            ->whereNotIn('status', ['draft', 'voided'])
            ->sum('total');

        $lastMonth = Invoice::where('organization_id', $this->organizationId)
            ->whereBetween('invoice_date', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotIn('status', ['draft', 'voided'])
            ->sum('total');

        $change = $lastMonth > 0
            ? (float) bcdiv(bcmul(bcsub((string) $thisMonth, (string) $lastMonth, 4), '100', 4), (string) $lastMonth, 4)
            : ($thisMonth > 0 ? 100 : 0);

        return [
            'value' => (float) $thisMonth,
            'previous_value' => (float) $lastMonth,
            'change_percentage' => round($change, 1),
            'trend' => $change >= 0 ? 'up' : 'down',
            'label' => 'Monthly Sales',
            'comparison_label' => 'vs Last Month',
        ];
    }

    public function getPendingInvoices(array $config = []): array
    {
        $pending = Invoice::where('organization_id', $this->organizationId)
            ->whereIn('status', ['sent', 'partial'])
            ->count();

        $overdue = Invoice::where('organization_id', $this->organizationId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('due_date', '<', Carbon::today())
            ->count();

        return [
            'value' => $pending,
            'overdue_count' => $overdue,
            'label' => 'Pending Invoices',
        ];
    }

    public function getOutstandingReceivables(array $config = []): array
    {
        $total = Invoice::where('organization_id', $this->organizationId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->sum('amount_due');

        $overdue = Invoice::where('organization_id', $this->organizationId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('due_date', '<', Carbon::today())
            ->sum('amount_due');

        return [
            'value' => (float) $total,
            'overdue_amount' => (float) $overdue,
            'label' => 'Outstanding Receivables',
            'show_overdue' => $config['show_overdue'] ?? true,
        ];
    }

    public function getSalesTrend(array $config = []): array
    {
        $period  = $config['period'] ?? '30days';
        $groupBy = $config['group_by'] ?? 'day';

        // Allowlist guard: ensure $groupBy is a known value before it reaches DB::raw
        if (!in_array($groupBy, ['day', 'week', 'month'], true)) {
            $groupBy = 'day';
        }

        $startDate = match ($period) {
            '7days' => Carbon::now()->subDays(7),
            '30days' => Carbon::now()->subDays(30),
            '90days' => Carbon::now()->subDays(90),
            '12months' => Carbon::now()->subMonths(12),
            default => Carbon::now()->subDays(30),
        };

        $dateFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $driver = DB::connection()->getDriverName();
        $periodExpr = $driver === 'sqlite'
            ? "strftime('{$dateFormat}', invoice_date)"
            : "DATE_FORMAT(invoice_date, '{$dateFormat}')";

        $data = Invoice::where('organization_id', $this->organizationId)
            ->where('invoice_date', '>=', $startDate)
            ->whereNotIn('status', ['draft', 'voided'])
            ->select(
                DB::raw("{$periodExpr} as period"),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'labels' => $data->pluck('period')->toArray(),
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $data->pluck('total')->map(fn($v) => (float)$v)->toArray(),
                ],
            ],
            'summary' => [
                'total' => $data->sum('total'),
                'count' => $data->sum('count'),
                'average' => $data->count() > 0 ? $data->sum('total') / $data->count() : 0,
            ],
        ];
    }

    public function getTopProducts(array $config = []): array
    {
        $limit = $config['limit'] ?? 10;
        $period = $config['period'] ?? 'month';

        $startDate = match ($period) {
            'week' => Carbon::now()->subWeek(),
            'month' => Carbon::now()->subMonth(),
            'quarter' => Carbon::now()->subQuarter(),
            'year' => Carbon::now()->subYear(),
            default => Carbon::now()->subMonth(),
        };

        $products = DB::table('invoice_lines')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_lines.product_id', '=', 'products.id')
            ->where('invoices.organization_id', $this->organizationId)
            ->where('invoices.invoice_date', '>=', $startDate)
            ->whereNotIn('invoices.status', ['draft', 'voided'])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(invoice_lines.quantity) as total_quantity'),
                DB::raw('SUM(invoice_lines.total) as total_amount')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get();

        return [
            'columns' => ['Product', 'SKU', 'Qty Sold', 'Revenue'],
            'rows' => $products->map(fn($p) => [
                'name' => $p->name,
                'sku' => $p->sku,
                'quantity' => (float) $p->total_quantity,
                'revenue' => (float) $p->total_amount,
            ])->toArray(),
        ];
    }

    public function getTopCustomers(array $config = []): array
    {
        $limit = $config['limit'] ?? 10;
        $period = $config['period'] ?? 'month';

        $startDate = match ($period) {
            'week' => Carbon::now()->subWeek(),
            'month' => Carbon::now()->subMonth(),
            'quarter' => Carbon::now()->subQuarter(),
            'year' => Carbon::now()->subYear(),
            default => Carbon::now()->subMonth(),
        };

        $customers = Invoice::where('organization_id', $this->organizationId)
            ->where('invoice_date', '>=', $startDate)
            ->whereNotIn('status', ['draft', 'voided'])
            ->select(
                'customer_id',
                'customer_name',
                DB::raw('SUM(total) as total_amount'),
                DB::raw('COUNT(*) as invoice_count')
            )
            ->groupBy('customer_id', 'customer_name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get();

        return [
            'columns' => ['Customer', 'Orders', 'Revenue'],
            'rows' => $customers->map(fn($c) => [
                'name' => $c->customer_name,
                'orders' => $c->invoice_count,
                'revenue' => (float) $c->total_amount,
            ])->toArray(),
        ];
    }

    public function getSalesByCategory(array $config = []): array
    {
        $period = $config['period'] ?? 'month';
        $startDate = Carbon::now()->subMonth();

        $data = DB::table('invoice_lines')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_lines.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('invoices.organization_id', $this->organizationId)
            ->where('invoices.invoice_date', '>=', $startDate)
            ->whereNotIn('invoices.status', ['draft', 'voided'])
            ->select(
                DB::raw('COALESCE(categories.name, \'Uncategorized\') as category'),
                DB::raw('SUM(invoice_lines.total) as total')
            )
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $data->pluck('category')->toArray(),
            'data' => $data->pluck('total')->map(fn($v) => (float)$v)->toArray(),
        ];
    }

    public function getRecentInvoices(array $config = []): array
    {
        $limit = $config['limit'] ?? 10;

        $invoices = Invoice::where('organization_id', $this->organizationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'invoice_number', 'customer_name', 'total', 'status', 'invoice_date']);

        return [
            'items' => $invoices->map(fn($i) => [
                'id' => $i->id,
                'number' => $i->invoice_number,
                'customer' => $i->customer_name,
                'amount' => (float) $i->total,
                'status' => $i->status,
                'date' => $i->invoice_date->format('Y-m-d'),
            ])->toArray(),
        ];
    }

    public function getSalesForecast(array $config = []): array
    {
        // Premium feature - simple linear forecast
        return [
            'message' => 'Sales forecast - Premium feature',
            'labels' => [],
            'datasets' => [],
        ];
    }

    public function getCustomerAnalytics(array $config = []): array
    {
        return [
            'message' => 'Customer analytics - Premium feature',
        ];
    }

    public function getRegionalSales(array $config = []): array
    {
        return [
            'message' => 'Regional sales map - Premium feature',
        ];
    }
}
