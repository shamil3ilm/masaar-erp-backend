<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

use App\Models\Manufacturing\WorkOrder;
use Illuminate\Support\Carbon;

/**
 * Dashboard widgets that report on work orders and production output.
 */
class ManufacturingWidgetProvider extends WidgetProvider
{
    public function getWorkOrdersSummary(array $config = []): array
    {
        $query = WorkOrder::where('organization_id', $this->organizationId);

        $total = (clone $query)->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $inProgress = (clone $query)->where('status', 'in_progress')->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $overdue = (clone $query)
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', Carbon::today())
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'overdue' => $overdue,
            'label' => 'Work Orders Summary',
        ];
    }

    public function getActiveWorkOrders(array $config = []): array
    {
        $limit = $config['limit'] ?? 10;

        $workOrders = WorkOrder::where('organization_id', $this->organizationId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['product:id,name,sku'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get();

        return [
            'items' => $workOrders->map(fn($wo) => [
                'id' => $wo->id,
                'work_order_number' => $wo->work_order_number,
                'product' => $wo->product->name ?? 'Unknown',
                'quantity' => (float) $wo->quantity,
                'completed_quantity' => (float) $wo->completed_quantity,
                'progress' => $wo->quantity > 0
                    ? round(($wo->completed_quantity / $wo->quantity) * 100, 1)
                    : 0,
                'status' => $wo->status,
                'due_date' => $wo->due_date?->format('Y-m-d'),
                'is_overdue' => $wo->due_date && $wo->due_date->isPast() && $wo->status !== 'completed',
            ])->toArray(),
            'label' => 'Active Work Orders',
        ];
    }

    public function getProductionEfficiency(array $config = []): array
    {
        $thisMonth = Carbon::now()->startOfMonth();

        $completed = WorkOrder::where('organization_id', $this->organizationId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $thisMonth)
            ->get();

        $onTime = $completed->filter(fn($wo) =>
            $wo->completed_at && $wo->due_date && $wo->completed_at->lte($wo->due_date)
        )->count();

        $totalCompleted = $completed->count();
        $onTimeRate = $totalCompleted > 0 ? round(($onTime / $totalCompleted) * 100, 1) : 0;

        return [
            'completed_this_month' => $totalCompleted,
            'on_time_count' => $onTime,
            'on_time_rate' => $onTimeRate,
            'label' => 'Production Efficiency (MTD)',
        ];
    }
}
