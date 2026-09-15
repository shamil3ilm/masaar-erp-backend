<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\SalesOrderCostEstimate;
use App\Models\Sales\SalesOrderCostEstimateItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesOrderCostingService
{
    // ----------------------------------------------------------------
    // Estimate CRUD
    // ----------------------------------------------------------------

    public function createEstimate(array $data): SalesOrderCostEstimate
    {
        return DB::transaction(function () use ($data): SalesOrderCostEstimate {
            return SalesOrderCostEstimate::create(array_merge($data, [
                'status'    => SalesOrderCostEstimate::STATUS_DRAFT,
                'costed_at' => now(),
            ]));
        });
    }

    /**
     * An estimate of the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function estimateOf(int $id): SalesOrderCostEstimate
    {
        return SalesOrderCostEstimate::findOrFail($id);
    }

    /**
     * An estimate with its items, their products and cost elements, its order
     * and the name of the user who costed it.
     *
     * @throws ModelNotFoundException
     */
    public function estimateDetails(int $id): SalesOrderCostEstimate
    {
        return SalesOrderCostEstimate::with(['items.product', 'items.costElement', 'salesOrder', 'costedBy:id,name'])
            ->findOrFail($id);
    }

    /**
     * Update an estimate. The released check runs on the locked row, so an
     * estimate released by a concurrent request is not changed.
     */
    public function update(SalesOrderCostEstimate $estimate, array $data): SalesOrderCostEstimate
    {
        return DB::transaction(function () use ($estimate, $data): SalesOrderCostEstimate {
            $estimate = $this->locked($estimate);

            if ($estimate->isReleased()) {
                throw new InvalidArgumentException('Cannot modify a released cost estimate.');
            }

            $estimate->update($data);

            return $estimate->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Items
    // ----------------------------------------------------------------

    /**
     * Add a cost item and recalculate the estimate's totals, with the estimate
     * locked so the released check and the totals see its current state.
     */
    public function addItem(SalesOrderCostEstimate $estimate, array $data): SalesOrderCostEstimateItem
    {
        return DB::transaction(function () use ($estimate, $data): SalesOrderCostEstimateItem {
            $estimate = $this->locked($estimate);

            if ($estimate->isReleased()) {
                throw new InvalidArgumentException('Cannot add items to a released cost estimate.');
            }

            $quantity    = (float) $data['quantity'];
            $costPerUnit = (float) $data['cost_per_unit'];
            $totalCost   = round($quantity * $costPerUnit, 4);

            $item = SalesOrderCostEstimateItem::create(array_merge($data, [
                'organization_id'              => $estimate->organization_id,
                'sales_order_cost_estimate_id' => $estimate->id,
                'total_cost'                   => $totalCost,
                'revenue'                      => $data['revenue'] ?? 0,
            ]));

            $this->recalculate($estimate);

            return $item->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Recalculation
    // ----------------------------------------------------------------

    public function recalculate(SalesOrderCostEstimate $estimate): void
    {
        $items = SalesOrderCostEstimateItem::withoutGlobalScope('organization')
            ->where('sales_order_cost_estimate_id', $estimate->id)
            ->get();

        $totalCost    = round($items->sum(fn ($i) => (float) $i->total_cost), 4);
        $totalRevenue = round($items->sum(fn ($i) => (float) $i->revenue), 4);
        $grossMargin  = round($totalRevenue - $totalCost, 4);
        $marginPct    = $totalRevenue > 0
            ? round(($grossMargin / $totalRevenue) * 100, 4)
            : 0.0;

        $estimate->update([
            'total_cost'           => $totalCost,
            'total_revenue'        => $totalRevenue,
            'gross_margin'         => $grossMargin,
            'gross_margin_percent' => $marginPct,
        ]);
    }

    // ----------------------------------------------------------------
    // Release
    // ----------------------------------------------------------------

    /**
     * Release a draft estimate. The draft check runs on the locked row, so two
     * requests cannot both release it.
     */
    public function release(SalesOrderCostEstimate $estimate): SalesOrderCostEstimate
    {
        return DB::transaction(function () use ($estimate): SalesOrderCostEstimate {
            $estimate = $this->locked($estimate);

            if (!$estimate->isDraft()) {
                throw new InvalidArgumentException('Only draft estimates can be released.');
            }

            $this->recalculate($estimate);

            $estimate->update(['status' => SalesOrderCostEstimate::STATUS_RELEASED]);

            return $estimate->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Listing
    // ----------------------------------------------------------------

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SalesOrderCostEstimate::with(['salesOrder', 'costedBy:id,name'])
            ->orderBy('id', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        if (!empty($filters['quotation_id'])) {
            $query->where('quotation_id', $filters['quotation_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * The estimate re-read and locked until the surrounding transaction ends.
     */
    private function locked(SalesOrderCostEstimate $estimate): SalesOrderCostEstimate
    {
        return SalesOrderCostEstimate::query()->lockForUpdate()->findOrFail($estimate->id);
    }
}
