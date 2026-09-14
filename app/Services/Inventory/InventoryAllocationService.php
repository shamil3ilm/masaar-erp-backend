<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\ERP\InsufficientStockException;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryAllocationService
{
    /**
     * Allocation methods.
     */
    public const METHOD_FIFO = 'fifo';    // First In, First Out
    public const METHOD_LIFO = 'lifo';    // Last In, First Out
    public const METHOD_FEFO = 'fefo';    // First Expired, First Out
    public const METHOD_MANUAL = 'manual'; // Manual batch selection

    /**
     * Check if sufficient stock is available.
     */
    public function checkAvailability(
        int $productId,
        string $quantity,
        ?int $warehouseId = null,
        bool $checkBatches = false
    ): AvailabilityResult {
        $product = Product::find($productId);

        if (!$product) {
            return new AvailabilityResult(false, '0', '0', 'Product not found');
        }

        // Get stock levels
        $query = StockLevel::where('product_id', $productId);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stockLevels = $query->get();
        $totalAvailable = '0';
        $totalReserved = '0';

        foreach ($stockLevels as $level) {
            $available = bcsub($level->quantity, $level->reserved_quantity, 4);
            $totalAvailable = bcadd($totalAvailable, $available, 4);
            $totalReserved = bcadd($totalReserved, $level->reserved_quantity, 4);
        }

        // If batch tracking, verify batch availability
        if ($checkBatches && $product->track_batches) {
            $batchAvailable = $this->getBatchAvailability($productId, $warehouseId);
            if (bccomp($batchAvailable, $totalAvailable, 4) < 0) {
                $totalAvailable = $batchAvailable;
            }
        }

        $isAvailable = bccomp($totalAvailable, $quantity, 4) >= 0;

        return new AvailabilityResult(
            isAvailable: $isAvailable,
            availableQuantity: $totalAvailable,
            reservedQuantity: $totalReserved,
            message: $isAvailable ? null : 'Insufficient stock',
            allowNegative: $product->allow_negative_stock
        );
    }

    /**
     * Get available quantity from batches.
     */
    protected function getBatchAvailability(int $productId, ?int $warehouseId): string
    {
        $query = InventoryBatch::where('product_id', $productId)
            ->available()
            ->notExpired();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $batches = $query->get();
        $total = '0';

        foreach ($batches as $batch) {
            $total = bcadd($total, $batch->getAvailableQuantity(), 4);
        }

        return $total;
    }

    /**
     * Allocate stock for a sale/transfer.
     *
     * The stock levels and then the batches are locked before anything is read
     * from them, and the reservation is made on those locked rows, so two
     * allocations of the same stock run one after the other and the second sees
     * what the first reserved. An allocation that cannot reserve the whole
     * quantity throws, and the transaction takes back whatever part of it was
     * reserved. Reserving stock that is not there is refused even when the
     * product allows negative stock, which is about issuing, not promising.
     */
    public function allocate(
        int $productId,
        string $quantity,
        ?int $warehouseId = null,
        string $method = self::METHOD_FIFO,
        ?array $batchIds = null
    ): AllocationResult {
        $product = Product::find($productId);

        if (!$product) {
            throw new \RuntimeException("Product not found: {$productId}");
        }

        if (bccomp($quantity, '0', 4) <= 0) {
            throw new \InvalidArgumentException('The quantity to allocate must be positive.');
        }

        return DB::transaction(function () use ($product, $productId, $quantity, $warehouseId, $method, $batchIds) {
            // Levels before batches, in every method here, so two of them never
            // wait on each other's locks.
            $levels = $this->lockStockLevels($productId, $warehouseId);
            $allocations = [];
            $totalCost = '0';

            if ($product->track_batches) {
                $allocations = $this->allocateFromBatches(
                    $productId,
                    $quantity,
                    $warehouseId,
                    $method,
                    $batchIds
                );

                $allocated = '0';

                foreach ($allocations as $allocation) {
                    $allocated = bcadd($allocated, $allocation['quantity'], 4);
                    $totalCost = bcadd($totalCost, $allocation['total_cost'], 4);
                }

                if (bccomp($allocated, $quantity, 4) < 0) {
                    throw $this->shortage($productId, $quantity, $allocated, $warehouseId);
                }
            }

            $this->updateStockLevels($levels, $productId, $quantity, $warehouseId, 'reserve');

            // Calculate average cost if no batches
            if (empty($allocations)) {
                $unitCost = $levels->first()?->average_cost ?? $product->purchase_price ?? '0';
                $totalCost = bcmul($quantity, $unitCost, 4);

                $allocations[] = [
                    'batch_id' => null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                ];
            }

            return new AllocationResult(
                allocations: $allocations,
                totalQuantity: $quantity,
                totalCost: $totalCost,
                averageCost: bcdiv($totalCost, $quantity, 4),
                method: $method
            );
        });
    }

    /**
     * Reserve from batches, read and reserved under lock. Returns what was
     * reserved, which may be less than $quantity; the caller decides.
     */
    protected function allocateFromBatches(
        int $productId,
        string $quantity,
        ?int $warehouseId,
        string $method,
        ?array $batchIds
    ): array {
        if ($method === self::METHOD_MANUAL && !empty($batchIds)) {
            return $this->allocateManual($productId, $quantity, $batchIds);
        }

        $query = InventoryBatch::where('product_id', $productId)
            ->available()
            ->notExpired()
            ->lockForUpdate();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Apply ordering based on method
        match ($method) {
            self::METHOD_FEFO => $query->fefo(),
            self::METHOD_LIFO => $query->lifo(),
            default => $query->fifo(),
        };

        $batches = $query->get();
        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $available = $batch->getAvailableQuantity();
            $toAllocate = bccomp($available, $remaining, 4) >= 0 ? $remaining : $available;

            if (bccomp($toAllocate, '0', 4) > 0) {
                $this->reserveBatch($batch, $toAllocate);

                $allocations[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'quantity' => $toAllocate,
                    'unit_cost' => $batch->unit_cost,
                    'total_cost' => bcmul($toAllocate, $batch->unit_cost, 4),
                ];

                $remaining = bcsub($remaining, $toAllocate, 4);
            }
        }

        return $allocations;
    }

    /**
     * Manually allocate from specific batches.
     */
    protected function allocateManual(int $productId, string $quantity, array $batchIds): array
    {
        $allocations = [];
        $remaining = $quantity;

        foreach ($batchIds as $batchAllocation) {
            $batchId = $batchAllocation['batch_id'];
            $allocateQty = $batchAllocation['quantity'] ?? null;

            $batch = InventoryBatch::lockForUpdate()->find($batchId);

            if (!$batch || (int) $batch->product_id !== $productId) {
                continue;
            }

            $available = $batch->getAvailableQuantity();
            $toAllocate = bccomp($available, $remaining, 4) < 0 ? $available : $remaining;

            if ($allocateQty !== null && bccomp((string) $allocateQty, $toAllocate, 4) < 0) {
                $toAllocate = (string) $allocateQty;
            }

            if (bccomp($toAllocate, '0', 4) > 0) {
                $this->reserveBatch($batch, $toAllocate);

                $allocations[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'quantity' => $toAllocate,
                    'unit_cost' => $batch->unit_cost,
                    'total_cost' => bcmul($toAllocate, $batch->unit_cost, 4),
                ];

                $remaining = bcsub($remaining, $toAllocate, 4);
            }

            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }
        }

        return $allocations;
    }

    /**
     * Release allocation (cancel reservation).
     */
    public function release(int $productId, string $quantity, ?int $warehouseId = null, ?array $allocations = null): void
    {
        DB::transaction(function () use ($productId, $quantity, $warehouseId, $allocations) {
            $levels = $this->lockStockLevels($productId, $warehouseId);

            foreach ($allocations ?? [] as $allocation) {
                if ($allocation['batch_id']) {
                    InventoryBatch::lockForUpdate()->find($allocation['batch_id'])?->release($allocation['quantity']);
                }
            }

            $this->updateStockLevels($levels, $productId, $quantity, $warehouseId, 'release');
        });
    }

    /**
     * Confirm allocation (actual stock deduction), on the locked stock levels
     * and batches. A batch that no longer holds the reservation or the
     * quantity throws, so the stock level is not deducted without it.
     */
    public function confirm(int $productId, string $quantity, ?int $warehouseId = null, ?array $allocations = null): void
    {
        DB::transaction(function () use ($productId, $quantity, $warehouseId, $allocations) {
            $levels = $this->lockStockLevels($productId, $warehouseId);

            foreach ($allocations ?? [] as $allocation) {
                if (! $allocation['batch_id']) {
                    continue;
                }

                $batch = InventoryBatch::lockForUpdate()->find($allocation['batch_id']);

                if ($batch === null || ! $batch->release($allocation['quantity'])) {
                    throw new \InvalidArgumentException(
                        "Batch #{$allocation['batch_id']} does not hold a reservation of {$allocation['quantity']}."
                    );
                }

                $batch->deductOrFail($allocation['quantity']);
            }

            $this->updateStockLevels($levels, $productId, $quantity, $warehouseId, 'deduct');
        });
    }

    /**
     * The product's stock levels, in the warehouse when one is given, locked
     * until the surrounding transaction ends.
     */
    protected function lockStockLevels(int $productId, ?int $warehouseId): Collection
    {
        return StockLevel::where('product_id', $productId)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Reserves, releases or deducts $quantity across locked stock levels.
     * Reserving takes only what each level has available and deducting needs
     * a level to take from; either throws when it cannot place the whole
     * quantity, rather than doing part of it.
     */
    protected function updateStockLevels(Collection $levels, int $productId, string $quantity, ?int $warehouseId, string $operation): void
    {
        $remaining = $quantity;

        foreach ($levels as $level) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $applied = match ($operation) {
                'reserve' => $this->lesser($remaining, bcsub((string) $level->quantity, (string) $level->reserved_quantity, 4)),
                'release' => $this->lesser($remaining, (string) $level->reserved_quantity),
                'deduct' => $remaining,
            };

            if (bccomp($applied, '0', 4) <= 0) {
                continue;
            }

            if ($operation === 'reserve') {
                $level->reserved_quantity = bcadd((string) $level->reserved_quantity, $applied, 4);
            } elseif ($operation === 'release') {
                $level->reserved_quantity = bcsub((string) $level->reserved_quantity, $applied, 4);
            } else {
                $level->quantity = bcsub((string) $level->quantity, $applied, 4);

                if (bccomp((string) $level->reserved_quantity, $applied, 4) >= 0) {
                    $level->reserved_quantity = bcsub((string) $level->reserved_quantity, $applied, 4);
                }
            }

            $level->save();
            $remaining = bcsub($remaining, $applied, 4);
        }

        if ($operation !== 'release' && bccomp($remaining, '0', 4) > 0) {
            throw $this->shortage($productId, $quantity, bcsub($quantity, $remaining, 4), $warehouseId);
        }
    }

    private function reserveBatch(InventoryBatch $batch, string $quantity): void
    {
        if (! $batch->reserve($quantity)) {
            throw $this->shortage((int) $batch->product_id, $quantity, $batch->getAvailableQuantity(), (int) $batch->warehouse_id);
        }
    }

    private function shortage(int $productId, string $requested, string $available, ?int $warehouseId): InsufficientStockException
    {
        return InsufficientStockException::forProduct(
            $productId,
            Product::find($productId)?->name ?? "#{$productId}",
            (float) $requested,
            (float) $available,
            $warehouseId,
        );
    }

    private function lesser(string $a, string $b): string
    {
        return bccomp($a, $b, 4) <= 0 ? $a : $b;
    }

    /**
     * Get expiring batches.
     */
    public function getExpiringBatches(int $organizationId, int $days = 30): Collection
    {
        return InventoryBatch::where('organization_id', $organizationId)
            ->available()
            ->expiringSoon($days)
            ->with(['product', 'warehouse'])
            ->orderBy('expiry_date')
            ->get();
    }

    /**
     * Get expired batches.
     */
    public function getExpiredBatches(int $organizationId): Collection
    {
        return InventoryBatch::where('organization_id', $organizationId)
            ->expired()
            ->where('status', '!=', InventoryBatch::STATUS_EXPIRED)
            ->with(['product', 'warehouse'])
            ->get();
    }

    /**
     * Mark expired batches as expired.
     */
    public function processExpiredBatches(int $organizationId): int
    {
        $count = 0;

        InventoryBatch::where('organization_id', $organizationId)
            ->expired()
            ->where('status', InventoryBatch::STATUS_AVAILABLE)
            ->chunk(100, function ($batches) use (&$count) {
                foreach ($batches as $batch) {
                    $batch->markAsExpired();
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Convert quantity between units.
     */
    public function convertQuantity(string $quantity, int $fromUnitId, int $toUnitId): string
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        $fromUnit = \App\Models\Inventory\UnitOfMeasure::find($fromUnitId);
        $toUnit = \App\Models\Inventory\UnitOfMeasure::find($toUnitId);

        if (!$fromUnit || !$toUnit) {
            throw new \RuntimeException('Invalid unit conversion');
        }

        // Convert to base unit first, then to target unit
        $baseQuantity = bcmul($quantity, (string) $fromUnit->conversion_factor, 6);
        return bcdiv($baseQuantity, (string) $toUnit->conversion_factor, 6);
    }

    /**
     * Calculate inventory valuation.
     */
    public function calculateValuation(
        int $productId,
        ?int $warehouseId = null,
        string $method = 'weighted_average'
    ): ValuationResult {
        $product = Product::find($productId);

        if ($product->track_batches) {
            return $this->calculateBatchValuation($productId, $warehouseId);
        }

        $query = StockLevel::where('product_id', $productId);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stockLevels = $query->get();
        $totalQuantity = '0';
        $totalValue = '0';

        foreach ($stockLevels as $level) {
            $totalQuantity = bcadd($totalQuantity, $level->quantity, 4);
            $value = bcmul($level->quantity, $level->average_cost, 4);
            $totalValue = bcadd($totalValue, $value, 4);
        }

        $averageCost = bccomp($totalQuantity, '0', 4) > 0
            ? bcdiv($totalValue, $totalQuantity, 4)
            : '0';

        return new ValuationResult(
            quantity: $totalQuantity,
            totalValue: $totalValue,
            averageCost: $averageCost,
            method: $method
        );
    }

    /**
     * Calculate valuation from batches.
     */
    protected function calculateBatchValuation(int $productId, ?int $warehouseId): ValuationResult
    {
        $query = InventoryBatch::where('product_id', $productId)
            ->where('status', InventoryBatch::STATUS_AVAILABLE);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $batches = $query->get();
        $totalQuantity = '0';
        $totalValue = '0';

        foreach ($batches as $batch) {
            $available = $batch->getAvailableQuantity();
            $totalQuantity = bcadd($totalQuantity, $available, 4);
            $value = bcmul($available, $batch->unit_cost, 4);
            $totalValue = bcadd($totalValue, $value, 4);
        }

        $averageCost = bccomp($totalQuantity, '0', 4) > 0
            ? bcdiv($totalValue, $totalQuantity, 4)
            : '0';

        return new ValuationResult(
            quantity: $totalQuantity,
            totalValue: $totalValue,
            averageCost: $averageCost,
            method: 'batch_actual'
        );
    }
}

// Result classes

class AvailabilityResult
{
    public function __construct(
        public readonly bool $isAvailable,
        public readonly string $availableQuantity,
        public readonly string $reservedQuantity,
        public readonly ?string $message = null,
        public readonly bool $allowNegative = false
    ) {}
}

class AllocationResult
{
    public function __construct(
        public readonly array $allocations,
        public readonly string $totalQuantity,
        public readonly string $totalCost,
        public readonly string $averageCost,
        public readonly string $method
    ) {}
}

class ValuationResult
{
    public function __construct(
        public readonly string $quantity,
        public readonly string $totalValue,
        public readonly string $averageCost,
        public readonly string $method
    ) {}
}
