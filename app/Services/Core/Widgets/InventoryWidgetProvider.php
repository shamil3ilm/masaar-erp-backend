<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\StockLevel;
use Illuminate\Support\Carbon;

/**
 * Dashboard widgets that report on stock levels, value, and expiry.
 */
class InventoryWidgetProvider extends WidgetProvider
{
    public function getLowStockCount(array $config = []): array
    {
        $count = StockLevel::where('organization_id', $this->organizationId)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->count();

        return [
            'value' => $count,
            'label' => 'Low Stock Items',
            'severity' => $count > 10 ? 'danger' : ($count > 5 ? 'warning' : 'normal'),
        ];
    }

    public function getInventoryValue(array $config = []): array
    {
        $value = StockLevel::where('organization_id', $this->organizationId)
            ->selectRaw('SUM(quantity * average_cost) as total_value')
            ->value('total_value') ?? 0;

        return [
            'value' => (float) $value,
            'label' => 'Inventory Value',
        ];
    }

    public function getExpiringProducts(array $config = []): array
    {
        $days = $config['days'] ?? 30;

        $expiring = InventoryBatch::where('organization_id', $this->organizationId)
            ->where('status', 'available')
            ->where('expiry_date', '<=', Carbon::now()->addDays($days))
            ->where('expiry_date', '>', Carbon::now())
            ->with('product:id,name,sku')
            ->orderBy('expiry_date')
            ->limit(10)
            ->get();

        return [
            'items' => $expiring->map(fn($b) => [
                'product' => $b->product->name ?? 'Unknown',
                'sku' => $b->product->sku ?? '',
                'batch' => $b->batch_number,
                'expiry_date' => $b->expiry_date->format('Y-m-d'),
                'days_until_expiry' => $b->expiry_date->diffInDays(Carbon::now()),
                'quantity' => (float) $b->quantity,
            ])->toArray(),
            'total_count' => InventoryBatch::where('organization_id', $this->organizationId)
                ->where('status', 'available')
                ->where('expiry_date', '<=', Carbon::now()->addDays($days))
                ->where('expiry_date', '>', Carbon::now())
                ->count(),
        ];
    }

    public function getStockMovement(array $config = []): array
    {
        // Simplified - would need StockMovement model data
        return [
            'labels' => ['In', 'Out', 'Adjustments'],
            'data' => [0, 0, 0],
            'message' => 'Stock movement tracking not yet implemented',
        ];
    }
}
