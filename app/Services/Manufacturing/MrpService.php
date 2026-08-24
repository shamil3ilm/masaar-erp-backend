<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\DemandForecast;
use App\Models\Manufacturing\MrpDemandItem;
use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\PlannedIndependentRequirement;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MrpService
{
    private const MAX_BOM_DEPTH = 50;

    /**
     * Execute an MRP run for the authenticated organization.
     *
     * Steps:
     *   1. Create the run record in pending state.
     *   2. Collect demand from sales orders, forecasts, and safety stock.
     *   3. For each product with net demand, create planned orders.
     *   4. Explode BOMs to cover component demand recursively.
     *   5. Mark the run as completed.
     */
    public function runMrp(array $data, int $userId): MrpRun
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId = auth()->user()->organization_id ?? $data['organization_id'];
            $horizonDays = (int) ($data['planning_horizon_days'] ?? 30);
            $horizonEnd = Carbon::now()->addDays($horizonDays)->toDateString();

            $run = MrpRun::create([
                'organization_id'        => $orgId,
                'run_date'               => now(),
                'planning_horizon_days'  => $horizonDays,
                'status'                 => MrpRun::STATUS_RUNNING,
                'run_by'                 => $userId,
            ]);

            try {
                // Cancel stale planned (but not firmed) orders from previous runs
                MrpPlannedOrder::where('organization_id', $orgId)
                    ->where('mrp_run_id', '!=', $run->id)
                    ->where('status', MrpPlannedOrder::STATUS_PLANNED)
                    ->update(['status' => MrpPlannedOrder::STATUS_CANCELLED]);

                // Step 2: Collect all demand items
                $demandByProduct = $this->collectDemand($run, $orgId, $horizonEnd);

                // Step 3 & 4: Plan orders covering net requirements and explode BOMs
                $plannedCount = $this->planOrders($run, $orgId, $demandByProduct);

                // Step 5: Mark as completed
                $run->update([
                    'status'                  => MrpRun::STATUS_COMPLETED,
                    'total_products_analyzed' => $demandByProduct->count(),
                    'total_planned_orders'    => $plannedCount,
                    'completed_at'            => now(),
                ]);
            } catch (\Throwable $e) {
                $run->update([
                    'status'        => MrpRun::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now(),
                ]);

                Log::error('MRP run failed', [
                    'run_id' => $run->id,
                    'error'  => $e->getMessage(),
                ]);

                throw $e;
            }

            return $run->fresh(['plannedOrders.product', 'runBy']);
        });
    }

    /**
     * Firm a planned order so it cannot be automatically replaced.
     */
    public function firmPlannedOrder(MrpPlannedOrder $order, int $userId): MrpPlannedOrder
    {
        if (!$order->canBeFirmed()) {
            throw new \InvalidArgumentException('Only planned orders in "planned" status can be firmed.');
        }

        return $order->firm($userId);
    }

    /**
     * Convert a planned order to a purchase order or work order based on its type.
     */
    public function convertToOrder(MrpPlannedOrder $order, int $userId): Model
    {
        if (!$order->canBeConverted()) {
            throw new \InvalidArgumentException('Only planned or firmed orders can be converted.');
        }

        return match ($order->order_type) {
            MrpPlannedOrder::TYPE_PURCHASE   => $order->convertToPurchaseOrder($userId),
            MrpPlannedOrder::TYPE_PRODUCTION => $order->convertToWorkOrder($userId),
            default                          => throw new \InvalidArgumentException("Cannot convert order of type '{$order->order_type}'."),
        };
    }

    /**
     * Get current MRP exceptions:
     * - Late planned orders (planned_end_date in the past)
     * - Unfirmed planned orders (still in "planned" status, action required)
     * - Demand exceeding supply (products with active demand but no planned orders)
     */
    public function getMrpExceptions(int $orgId): array
    {
        $today = now()->toDateString();

        $late = MrpPlannedOrder::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('status', [MrpPlannedOrder::STATUS_PLANNED, MrpPlannedOrder::STATUS_FIRMED])
            ->where('planned_end_date', '<', $today)
            ->with('product:id,name,sku')
            ->limit(100)
            ->get()
            ->map(fn ($o) => [
                'id'               => $o->id,
                'uuid'             => $o->uuid,
                'product_name'     => $o->product?->name,
                'sku'              => $o->product?->sku,
                'planned_end_date' => $o->planned_end_date?->toDateString(),
                'order_type'       => $o->order_type,
                'planned_quantity' => $o->planned_quantity,
                'status'           => $o->status,
            ])->all();

        $unfirmed = MrpPlannedOrder::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('status', MrpPlannedOrder::STATUS_PLANNED)
            ->where('planned_start_date', '<=', now()->addDays(7)->toDateString())
            ->with('product:id,name,sku')
            ->limit(100)
            ->get()
            ->map(fn ($o) => [
                'id'                 => $o->id,
                'uuid'               => $o->uuid,
                'product_name'       => $o->product?->name,
                'sku'                => $o->product?->sku,
                'planned_start_date' => $o->planned_start_date?->toDateString(),
                'planned_quantity'   => $o->planned_quantity,
                'order_type'         => $o->order_type,
            ])->all();

        return [
            'late_planned_orders'    => $late,
            'late_count'             => count($late),
            'unfirmed_near_term'     => $unfirmed,
            'unfirmed_count'         => count($unfirmed),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Collect demand from all sources and store demand items on the run.
     * Returns a Collection keyed by product_id => total required quantity.
     */
    private function collectDemand(MrpRun $run, int $orgId, string $horizonEnd): Collection
    {
        $demand = collect();

        // 1) Open sales order lines due within the horizon
        SalesOrderLine::withoutGlobalScope('organization')
            ->whereHas('salesOrder', function ($q) use ($orgId, $horizonEnd) {
                $q->withoutGlobalScope('organization')
                    ->where('organization_id', $orgId)
                    ->whereIn('status', [
                        SalesOrder::STATUS_CONFIRMED,
                        SalesOrder::STATUS_PROCESSING,
                    ])
                    ->where(function ($q2) use ($horizonEnd) {
                        $q2->whereNull('expected_delivery_date')
                            ->orWhere('expected_delivery_date', '<=', $horizonEnd);
                    });
            })
            ->with('salesOrder:id,expected_delivery_date')
            ->chunkById(200, function ($salesLines) use ($run, $horizonEnd, &$demand) {
                foreach ($salesLines as $line) {
                    $qty = max(0, (float) $line->quantity - (float) ($line->quantity_delivered ?? 0));

                    if ($qty <= 0) {
                        continue;
                    }

                    MrpDemandItem::create([
                        'mrp_run_id'        => $run->id,
                        'product_id'        => $line->product_id,
                        'source_type'       => MrpDemandItem::SOURCE_SALES_ORDER,
                        'source_id'         => $line->id,
                        'required_date'     => $line->salesOrder->expected_delivery_date ?? $horizonEnd,
                        'required_quantity' => $qty,
                    ]);

                    $demand->put($line->product_id, ($demand->get($line->product_id, 0.0) + $qty));
                }
            });

        // 2) Demand forecasts within the horizon
        DemandForecast::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('forecast_date', '<=', $horizonEnd)
            ->where('forecast_date', '>=', now()->toDateString())
            ->chunkById(200, function ($forecasts) use ($run, &$demand) {
                foreach ($forecasts as $forecast) {
                    $qty = (float) $forecast->forecast_quantity;

                    MrpDemandItem::create([
                        'mrp_run_id'        => $run->id,
                        'product_id'        => $forecast->product_id,
                        'source_type'       => MrpDemandItem::SOURCE_FORECAST,
                        'source_id'         => $forecast->id,
                        'required_date'     => $forecast->forecast_date->toDateString(),
                        'required_quantity' => $qty,
                    ]);

                    $demand->put($forecast->product_id, ($demand->get($forecast->product_id, 0.0) + $qty));
                }
            });

        // 3) Safety stock requirements — products whose stock is below reorder level
        StockLevel::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereRaw('quantity < COALESCE(reorder_level, 0)')
            ->whereRaw('reorder_level > 0')
            ->chunkById(200, function ($lowStockItems) use ($run, &$demand) {
        foreach ($lowStockItems as $sl) {
            $reorderLevel = (string) ($sl->reorder_level ?? '0');

            // Skip items with no meaningful reorder level set
            if (bccomp($reorderLevel, '0', 4) <= 0) {
                continue;
            }

            $reorderQty = (float) ($sl->reorder_quantity ?: $sl->reorder_level ?? 1);
            $gap        = bcsub($reorderLevel, (string) $sl->quantity, 4);

            if (bccomp($gap, '0', 4) <= 0) {
                continue;
            }

            $gapFloat = (float) $gap;

            MrpDemandItem::create([
                'mrp_run_id'        => $run->id,
                'product_id'        => $sl->product_id,
                'source_type'       => MrpDemandItem::SOURCE_SAFETY_STOCK,
                'source_id'         => null,
                'required_date'     => now()->toDateString(),
                'required_quantity' => $gapFloat,
            ]);

            $demand->put($sl->product_id, ($demand->get($sl->product_id, 0.0) + $gapFloat));
        }
        }); // end chunkById for safety stock

        // 4) Planned Independent Requirements (PIR / MD61) — Make-to-Stock demand
        PlannedIndependentRequirement::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('requirement_date', '>=', now()->toDateString())
            ->where('requirement_date', '<=', $horizonEnd)
            ->whereNull('deleted_at')
            ->chunkById(200, function ($pirs) use ($run, &$demand) {
                foreach ($pirs as $pir) {
                    $qty = $pir->openQuantity();

                    if ($qty <= 0) {
                        continue;
                    }

                    MrpDemandItem::create([
                        'mrp_run_id'        => $run->id,
                        'product_id'        => $pir->product_id,
                        'source_type'       => MrpDemandItem::SOURCE_PIR,
                        'source_id'         => $pir->id,
                        'required_date'     => $pir->requirement_date->toDateString(),
                        'required_quantity' => $qty,
                    ]);

                    $demand->put($pir->product_id, ($demand->get($pir->product_id, 0.0) + $qty));
                }
            });

        return $demand;
    }

    /**
     * For each product with demand, compute net requirement and create planned orders.
     * Also explode BOMs to plan component orders recursively.
     *
     * @param  Collection<int, float>  $demandByProduct
     */
    private function planOrders(MrpRun $run, int $orgId, Collection $demandByProduct): int
    {
        $plannedCount = 0;
        $visited      = [];  // Guard against infinite BOM recursion

        foreach ($demandByProduct as $productId => $totalDemand) {
            $plannedCount += $this->planForProduct(
                $run,
                $orgId,
                $productId,
                $totalDemand,
                $visited
            );
        }

        return $plannedCount;
    }

    /**
     * Plan orders for a single product and recursively for its BOM components.
     *
     * @param  array<int, bool>  $visited
     */
    private function planForProduct(
        MrpRun $run,
        int $orgId,
        int $productId,
        float $totalDemand,
        array &$visited,
        int $depth = 0
    ): int {
        if (isset($visited[$productId])) {
            return 0; // Already processed — avoid circular BOM explosion
        }

        $visited[$productId] = true;
        $plannedCount        = 0;

        // Get current available stock across all warehouses (quantity minus reserved)
        $currentStock = (float) StockLevel::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as available')
            ->value('available');

        $netRequirement = (float) bcsub((string) $totalDemand, (string) $currentStock, 4);

        if ($netRequirement > 0) {
            // Round up to order multiple
            $product        = Product::withoutGlobalScope('organization')->find($productId);
            $orderMultiple  = (float) ($product->reorder_quantity ?? 1);
            $orderMultiple  = $orderMultiple > 0 ? $orderMultiple : 1.0;
            $units          = (int) ceil((float) bcdiv((string) $netRequirement, (string) $orderMultiple, 4));
            $plannedQty     = (float) bcmul((string) $units, (string) $orderMultiple, 4);

            // Determine order type: if a BOM exists, produce; otherwise purchase
            $hasBom    = BomTemplate::withoutGlobalScope('organization')
                ->where('organization_id', $orgId)
                ->where('product_id', $productId)
                ->where('status', BomTemplate::STATUS_ACTIVE)
                ->exists();

            $orderType = $hasBom ? MrpPlannedOrder::TYPE_PRODUCTION : MrpPlannedOrder::TYPE_PURCHASE;
            // Use product-level lead time when available; fall back to a configurable default
            $leadDays  = (int) ($product->lead_time_days ?? $product->default_supplier_lead_days ?? config('erp.default_lead_time_days', 7));

            MrpPlannedOrder::create([
                'organization_id'    => $orgId,
                'mrp_run_id'         => $run->id,
                'product_id'         => $productId,
                'order_type'         => $orderType,
                'planned_quantity'   => $plannedQty,
                'planned_start_date' => now()->toDateString(),
                'planned_end_date'   => now()->addDays($leadDays)->toDateString(),
                'status'             => MrpPlannedOrder::STATUS_PLANNED,
            ]);

            $plannedCount++;

            // BOM explosion: if the product is produced, plan for its components
            if ($hasBom) {
                $plannedCount += $this->explodeBom($run, $orgId, $productId, $plannedQty, $visited, $depth);
            }
        }

        return $plannedCount;
    }

    /**
     * Recursively explode a BOM to plan component orders.
     *
     * @param  array<int, bool>  $visited
     */
    private function explodeBom(
        MrpRun $run,
        int $orgId,
        int $productId,
        float $quantity,
        array &$visited,
        int $depth = 0
    ): int {
        if ($depth >= self::MAX_BOM_DEPTH) {
            throw new \RuntimeException('BOM depth limit exceeded — possible circular reference.');
        }
        $bom = BomTemplate::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where('status', BomTemplate::STATUS_ACTIVE)
            ->with('lines')
            ->first();

        if (!$bom) {
            return 0;
        }

        $plannedCount  = 0;
        $outputQty     = (float) ($bom->output_quantity ?: 1);
        $multiplier    = $quantity / $outputQty;

        foreach ($bom->lines as $line) {
            $componentDemand = (float) $line->getAdjustedQuantity($multiplier);

            // Add this component's demand to the run items
            MrpDemandItem::create([
                'mrp_run_id'        => $run->id,
                'product_id'        => $line->product_id,
                'source_type'       => MrpDemandItem::SOURCE_BOM,
                'source_id'         => $bom->id,
                'required_date'     => now()->toDateString(),
                'required_quantity' => $componentDemand,
            ]);

            $plannedCount += $this->planForProduct(
                $run,
                $orgId,
                $line->product_id,
                $componentDemand,
                $visited,
                $depth + 1
            );
        }

        return $plannedCount;
    }
}
