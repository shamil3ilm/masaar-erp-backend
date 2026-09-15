<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\PickingList;
use App\Models\Inventory\PickingListLine;
use App\Models\Inventory\Product;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\WarehouseLocation;
use App\Models\Inventory\WavePlan;
use App\Models\Inventory\WavePlanOrder;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WaveManagementService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a wave plan and attach the provided order IDs.
     */
    public function createWavePlan(array $data, array $orderIds, int $userId): WavePlan
    {
        return DB::transaction(function () use ($data, $orderIds, $userId) {
            if (empty($data['wave_number'])) {
                $data['wave_number'] = $this->numberGenerator->generate('WAVE');
            }

            $data['created_by'] = $userId;
            $data['status']     = WavePlan::STATUS_DRAFT;

            $wave = WavePlan::create($data);

            foreach ($orderIds as $orderEntry) {
                WavePlanOrder::create([
                    'wave_plan_id' => $wave->id,
                    'order_type'   => $orderEntry['order_type'] ?? WavePlanOrder::ORDER_TYPE_SALES_ORDER,
                    'order_id'     => $orderEntry['order_id'],
                ]);
            }

            $this->recalculateTotals($wave);

            return $wave->refresh();
        });
    }

    /**
     * Transition a wave from draft/released to released status and generate picking lists.
     */
    public function releaseWave(WavePlan $wave, int $userId): WavePlan
    {
        // Checked again on the locked row: two releases of one draft would
        // otherwise both generate picking lists.
        return $wave->lockForTransition(function (WavePlan $wave) use ($userId): WavePlan {
            if (!$wave->isDraft()) {
                throw new \InvalidArgumentException('Only draft wave plans can be released.');
            }

            $wave->release($userId);
            $this->generatePickingLists($wave, $userId);

            // Transition to picking state now that lists exist
            $wave->status = WavePlan::STATUS_PICKING;
            $wave->save();

            return $wave->refresh()->load(['pickingLists.lines']);
        });
    }

    /**
     * Generate picking lists for a released wave.
     * Groups lines by zone (zone/cluster types) or creates one list per order.
     *
     * @return PickingList[]
     */
    public function generatePickingLists(WavePlan $wave, int $userId): array
    {
        $pickingLists = [];
        $pickingType  = $this->resolvePickingType($wave->wave_type);

        if (in_array($wave->wave_type, [WavePlan::TYPE_OUTBOUND], true)) {
            // Group all order lines together into zone-based or cluster picking lists
            $pickingLists = $this->buildPickingListsFromOrders($wave, $pickingType, $userId);
        } else {
            // For replenishment/returns: one picking list per order
            $pickingLists = $this->buildPickingListsOnePerOrder($wave, $pickingType, $userId);
        }

        return $pickingLists;
    }

    /**
     * Assign a picker user to a picking list.
     */
    public function assignPicker(PickingList $list, int $pickerId, int $userId): PickingList
    {
        return $list->lockForTransition(function (PickingList $list) use ($pickerId, $userId): PickingList {
            if (!in_array($list->status, [PickingList::STATUS_PENDING, PickingList::STATUS_ASSIGNED], true)) {
                throw new \InvalidArgumentException('Only pending or assigned lists can have a picker assigned.');
            }

            $list->assign($pickerId, $userId);
            return $list->refresh();
        });
    }

    /**
     * Mark a picking list as in-progress.
     */
    public function startPicking(PickingList $list, int $userId): PickingList
    {
        return $list->lockForTransition(function (PickingList $list) use ($userId): PickingList {
            if ($list->status !== PickingList::STATUS_ASSIGNED) {
                throw new \InvalidArgumentException('Only assigned lists can be started.');
            }

            $list->start($userId);
            return $list->refresh();
        });
    }

    /**
     * Record a pick quantity on a single line.
     * Auto-completes the parent list when all lines are resolved.
     */
    public function pickLine(PickingListLine $line, float $quantity, int $userId): PickingListLine
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Pick quantity must be greater than zero.');
        }

        // The completed check runs on the locked line, so two picks of the
        // last units cannot both be recorded.
        return $line->lockForTransition(function (PickingListLine $line) use ($quantity, $userId): PickingListLine {
            if ($line->isCompleted()) {
                throw new \InvalidArgumentException('This line has already been fully picked.');
            }

            $line->pick($quantity, $userId);

            // Recalculate list picked_lines counter
            $list = $line->pickingList;
            $this->updateListProgress($list);

            return $line->refresh();
        });
    }

    /**
     * Complete a picking list (mark remaining lines partial/complete).
     */
    public function completePicking(PickingList $list, int $userId): PickingList
    {
        return $list->lockForTransition(function (PickingList $list) use ($userId): PickingList {
            if (!in_array($list->status, [PickingList::STATUS_IN_PROGRESS, PickingList::STATUS_ASSIGNED], true)) {
                throw new \InvalidArgumentException('Only in-progress or assigned lists can be completed.');
            }

            $list->complete($userId);
            $this->checkWaveCompletion($list->wave_plan_id, $userId);

            return $list->refresh();
        });
    }

    /**
     * Resolve the best putaway location for a product using active rules.
     */
    public function getPutawayLocation(int $warehouseId, int $productId, int $categoryId): ?WarehouseLocation
    {
        $rules = PutawayRule::active()
            ->forWarehouse($warehouseId)
            ->byPriority()
            ->with('preferredLocation')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matches($productId, $categoryId) && $rule->preferredLocation !== null) {
                return $rule->preferredLocation;
            }
        }

        return null;
    }

    /**
     * Create a new putaway rule.
     */
    public function createPutawayRule(array $data, int $userId): PutawayRule
    {
        return DB::transaction(function () use ($data) {
            return PutawayRule::create($data);
        });
    }

    /**
     * Aggregate wave statistics for an organisation over a date range.
     */
    public function getWaveStats(int $orgId, string $from, string $to): array
    {
        $waves = WavePlan::where('organization_id', $orgId)
            ->whereBetween('planned_date', [$from, $to])
            ->get();

        $totalWaves     = $waves->count();
        $completedWaves = $waves->where('status', WavePlan::STATUS_COMPLETED)->count();

        $avgCompletionMinutes = $waves
            ->whereNotNull('released_at')
            ->whereNotNull('completed_at')
            ->avg(fn($w) => $w->released_at->diffInMinutes($w->completed_at));

        $totalLines  = $waves->sum('total_lines');
        $pickedLines = PickingListLine::whereHas(
            'pickingList',
            fn($q) => $q->whereIn('wave_plan_id', $waves->pluck('id'))
        )
            ->where('status', PickingListLine::STATUS_COMPLETED)
            ->count();

        $pickRate = $totalLines > 0
            ? round(($pickedLines / $totalLines) * 100, 2)
            : 0.0;

        return [
            'total_waves'              => $totalWaves,
            'completed_waves'          => $completedWaves,
            'completion_rate_percent'  => $totalWaves > 0
                ? round(($completedWaves / $totalWaves) * 100, 2)
                : 0.0,
            'total_lines'              => $totalLines,
            'picked_lines'             => $pickedLines,
            'pick_rate_percent'        => $pickRate,
            'avg_completion_minutes'   => $avgCompletionMinutes ? round((float) $avgCompletionMinutes, 1) : null,
            'period'                   => ['from' => $from, 'to' => $to],
        ];
    }


    // -------------------------------------------------------------------------
    // Lookups and lists
    // -------------------------------------------------------------------------

    /**
     * Putaway rules of the current organization with their references, in
     * priority order. warehouse_id applies when present; active_only when true.
     *
     * @param  array{warehouse_id?: int, active_only?: bool}  $filters
     */
    public function paginatePutawayRules(array $filters, int $perPage): LengthAwarePaginator
    {
        return PutawayRule::with(['warehouse', 'product', 'productCategory', 'preferredLocation'])
            ->orderBy('priority')
            ->when(array_key_exists('warehouse_id', $filters), fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->paginate($perPage);
    }

    public function findPutawayRuleOrFail(int $id): PutawayRule
    {
        return PutawayRule::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePutawayRule(PutawayRule $rule, array $data): PutawayRule
    {
        $rule->update($data);

        return $rule->load(['warehouse', 'product', 'productCategory', 'preferredLocation']);
    }

    public function deletePutawayRule(PutawayRule $rule): void
    {
        $rule->delete();
    }

    /**
     * Wave plans of the current organization with their warehouse and
     * creator, newest first. Each filter applies when its key is present.
     *
     * @param  array{status?: string, warehouse_id?: int, wave_type?: string, from_date?: string, to_date?: string}  $filters
     */
    public function paginateWaves(array $filters, int $perPage): LengthAwarePaginator
    {
        return WavePlan::with(['warehouse', 'creator'])
            ->latest()
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('warehouse_id', $filters), fn ($q) => $q->forWarehouse($filters['warehouse_id']))
            ->when(array_key_exists('wave_type', $filters), fn ($q) => $q->where('wave_type', $filters['wave_type']))
            ->when(array_key_exists('from_date', $filters), fn ($q) => $q->where('planned_date', '>=', $filters['from_date']))
            ->when(array_key_exists('to_date', $filters), fn ($q) => $q->where('planned_date', '<=', $filters['to_date']))
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $with
     */
    public function findWaveOrFail(int $id, array $with = []): WavePlan
    {
        return WavePlan::with($with)->findOrFail($id);
    }

    /**
     * Complete a wave on its locked row; a wave already completed is returned
     * as it is.
     */
    public function completeWave(WavePlan $wave, int $userId): WavePlan
    {
        return $wave->lockForTransition(function (WavePlan $wave) use ($userId): WavePlan {
            if (! $wave->isCompleted()) {
                $wave->complete($userId);
            }

            return $wave->refresh();
        });
    }

    /**
     * Picking lists of the current organization with their wave, warehouse and
     * picker, newest first. Each filter applies when its key is present.
     *
     * @param  array{status?: string, warehouse_id?: int, picker_id?: int, wave_id?: int}  $filters
     */
    public function paginatePickingLists(array $filters, int $perPage): LengthAwarePaginator
    {
        return PickingList::with(['wave', 'warehouse', 'picker'])
            ->latest()
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('warehouse_id', $filters), fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(array_key_exists('picker_id', $filters), fn ($q) => $q->forPicker($filters['picker_id']))
            ->when(array_key_exists('wave_id', $filters), fn ($q) => $q->where('wave_plan_id', $filters['wave_id']))
            ->paginate($perPage);
    }

    /**
     * @param  array<int|string, mixed>  $with
     */
    public function findPickingListOrFail(int $id, array $with = []): PickingList
    {
        return PickingList::with($with)->findOrFail($id);
    }

    /**
     * A picking list line of the current organization. Lines carry no
     * organization column, so the line is found through its picking list,
     * which does.
     */
    public function findPickingLineOrFail(int $id): PickingListLine
    {
        return PickingListLine::whereHas('pickingList')->findOrFail($id);
    }

    /**
     * Record a pick with optional notes; the notes and the pick are kept
     * together or not at all.
     */
    public function recordPick(PickingListLine $line, float $quantity, ?string $notes, int $userId): PickingListLine
    {
        return DB::transaction(function () use ($line, $quantity, $notes, $userId): PickingListLine {
            if (! empty($notes)) {
                $line->notes = $notes;
                $line->save();
            }

            return $this->pickLine($line, $quantity, $userId);
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolvePickingType(string $waveType): string
    {
        return match ($waveType) {
            WavePlan::TYPE_REPLENISHMENT => PickingList::TYPE_ZONE,
            WavePlan::TYPE_RETURNS       => PickingList::TYPE_SINGLE_ORDER,
            default                      => PickingList::TYPE_MULTI_ORDER,
        };
    }

    /**
     * Build multi-order picking lists grouped by warehouse zone.
     * Each unique zone in the wave's orders gets its own picking list.
     * Falls back to a single multi-order list when no zones are defined.
     *
     * @return PickingList[]
     */
    private function buildPickingListsFromOrders(WavePlan $wave, string $pickingType, int $userId): array
    {
        $orders = $wave->waveOrders;

        if ($orders->isEmpty()) {
            return [];
        }

        // Collect all stock-level location data for the wave's products
        $productLocations = $this->resolveProductLocationsForWave($wave);

        // Group by zone code (using warehouse_zone from location or 'DEFAULT')
        $zoneGroups = [];
        foreach ($productLocations as $entry) {
            $zone = $entry['zone'] ?? 'DEFAULT';
            $zoneGroups[$zone][] = $entry;
        }

        $lists = [];
        foreach ($zoneGroups as $zone => $entries) {
            $list = $this->createPickingList($wave, $pickingType, $userId);
            $this->attachLinesToList($list, $entries, $wave, $userId);
            $lists[] = $list;
        }

        // If nothing resolved, create one empty placeholder list
        if (empty($lists)) {
            $lists[] = $this->createPickingList($wave, $pickingType, $userId);
        }

        return $lists;
    }

    /**
     * Build one picking list per wave order (replenishment / returns style).
     *
     * @return PickingList[]
     */
    private function buildPickingListsOnePerOrder(WavePlan $wave, string $pickingType, int $userId): array
    {
        $lists = [];

        foreach ($wave->waveOrders as $waveOrder) {
            $list = $this->createPickingList($wave, $pickingType, $userId);

            // Attach a generic line per order — real ERP would expand order lines
            $this->attachOrderLine($list, $waveOrder, $userId);

            $lists[] = $list;
        }

        return $lists;
    }

    private function createPickingList(WavePlan $wave, string $pickingType, int $userId): PickingList
    {
        return PickingList::create([
            'organization_id' => $wave->organization_id,
            'wave_plan_id'    => $wave->id,
            'warehouse_id'    => $wave->warehouse_id,
            'list_number'     => $this->numberGenerator->generate('PKL'),
            'status'          => PickingList::STATUS_PENDING,
            'picking_type'    => $pickingType,
            'total_lines'     => 0,
            'picked_lines'    => 0,
            'created_by'      => $userId,
        ]);
    }

    /**
     * Resolve product → location mappings for all orders in the wave.
     * Uses StockLevel to find actual storage locations and sorts by location sort_order.
     */
    private function resolveProductLocationsForWave(WavePlan $wave): array
    {
        // Gather order IDs grouped by type
        $salesOrderIds = $wave->waveOrders
            ->where('order_type', WavePlanOrder::ORDER_TYPE_SALES_ORDER)
            ->pluck('order_id')
            ->all();

        if (empty($salesOrderIds)) {
            return [];
        }

        // Pull stock levels for the warehouse — join with location for sort_order
        $stockLevels = StockLevel::where('warehouse_id', $wave->warehouse_id)
            ->where('quantity', '>', 0)
            ->with(['location', 'product.category'])
            ->orderBy('location_id')
            ->get();

        $entries = [];
        foreach ($stockLevels as $sl) {
            $entries[] = [
                'product_id'   => $sl->product_id,
                'variant_id'   => $sl->variant_id,
                'location_id'  => $sl->location_id,
                'zone'         => $sl->location?->type ?? 'DEFAULT',
                'sort_order'   => $sl->location?->id ?? 0,
                'quantity'     => (float) $sl->quantity,
                'source_type'  => WavePlanOrder::ORDER_TYPE_SALES_ORDER,
                'source_ids'   => $salesOrderIds,
            ];
        }

        // Sort by sort_order for optimal pick path
        usort($entries, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $entries;
    }

    /**
     * Attach resolved stock entries as picking list lines.
     */
    private function attachLinesToList(PickingList $list, array $entries, WavePlan $wave, int $userId): void
    {
        $sortOrder = 0;
        foreach ($entries as $entry) {
            foreach ($entry['source_ids'] as $sourceId) {
                PickingListLine::create([
                    'picking_list_id'   => $list->id,
                    'source_type'       => $entry['source_type'],
                    'source_id'         => $sourceId,
                    'product_id'        => $entry['product_id'],
                    'variant_id'        => $entry['variant_id'] ?? null,
                    'from_location_id'  => $entry['location_id'] ?? null,
                    'to_location_id'    => null,
                    'required_quantity' => $entry['quantity'],
                    'picked_quantity'   => 0,
                    'status'            => PickingListLine::STATUS_PENDING,
                    'sort_order'        => $sortOrder++,
                ]);
            }
        }

        $lineCount = $list->lines()->count();
        $list->total_lines = $lineCount;
        $list->save();
    }

    private function attachOrderLine(PickingList $list, WavePlanOrder $waveOrder, int $userId): void
    {
        PickingListLine::create([
            'picking_list_id'   => $list->id,
            'source_type'       => $waveOrder->order_type,
            'source_id'         => $waveOrder->order_id,
            'product_id'        => 0, // Placeholder — real implementation expands order lines
            'from_location_id'  => null,
            'to_location_id'    => null,
            'required_quantity' => 1,
            'picked_quantity'   => 0,
            'status'            => PickingListLine::STATUS_PENDING,
            'sort_order'        => 0,
        ]);

        $list->total_lines = 1;
        $list->save();
    }

    private function updateListProgress(PickingList $list): void
    {
        $pickedLines = $list->lines()
            ->where('status', PickingListLine::STATUS_COMPLETED)
            ->count();

        $list->picked_lines = $pickedLines;
        $list->save();

        // Auto-complete list when all lines are resolved (completed or skipped)
        $unresolvedLines = $list->lines()
            ->whereIn('status', [PickingListLine::STATUS_PENDING, PickingListLine::STATUS_PARTIAL])
            ->count();

        if ($unresolvedLines === 0 && $list->total_lines > 0) {
            $list->complete(0);
            $this->checkWaveCompletion($list->wave_plan_id, 0);
        }
    }

    private function checkWaveCompletion(?int $wavePlanId, int $userId): void
    {
        if ($wavePlanId === null) {
            return;
        }

        $wave = WavePlan::find($wavePlanId);
        if (!$wave || $wave->isCompleted()) {
            return;
        }

        $incompleteLists = $wave->pickingLists()
            ->whereNotIn('status', [PickingList::STATUS_COMPLETED, PickingList::STATUS_PARTIAL, PickingList::STATUS_CANCELLED])
            ->count();

        if ($incompleteLists === 0) {
            $wave->complete($userId);
        }
    }

    private function recalculateTotals(WavePlan $wave): void
    {
        $orderCount = $wave->waveOrders()->count();
        $wave->total_orders = $orderCount;
        $wave->save();
    }
}
