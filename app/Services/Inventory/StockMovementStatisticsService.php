<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StockMovement;
use Illuminate\Support\Collection;

/**
 * Summaries of stock movements for reporting.
 */
class StockMovementStatisticsService
{
    /**
     * The organization's movements of the last $days days grouped by movement
     * type, with how many there were and their total absolute quantity, most
     * frequent type first.
     *
     * @return Collection<int, StockMovement>
     */
    public function countsByMovementType(int $organizationId, int $days): Collection
    {
        return StockMovement::where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('movement_type, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity')
            ->groupBy('movement_type')
            ->orderByDesc('count')
            ->get();
    }
}
