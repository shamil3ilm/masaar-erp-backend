<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferLine;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        private StockService $stockService,
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Stock transfers of the current organization with their warehouses,
     * lines and creator, newest first. Each filter applies when its key is
     * present.
     *
     * @param  array{from_warehouse_id?: mixed, to_warehouse_id?: mixed, status?: mixed, from_date?: mixed, to_date?: mixed}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return StockTransfer::with(['fromWarehouse', 'toWarehouse', 'lines.product', 'lines.variant', 'creator'])
            ->latest()
            ->when(array_key_exists('from_warehouse_id', $filters), fn ($q) => $q->fromWarehouse((int) $filters['from_warehouse_id']))
            ->when(array_key_exists('to_warehouse_id', $filters), fn ($q) => $q->toWarehouse((int) $filters['to_warehouse_id']))
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('from_date', $filters), fn ($q) => $q->where('transfer_date', '>=', $filters['from_date']))
            ->when(array_key_exists('to_date', $filters), fn ($q) => $q->where('transfer_date', '<=', $filters['to_date']))
            ->paginate($perPage);
    }

    /**
     * Draft and in-transit transfers of the current organization, newest first.
     */
    public function paginatePending(int $perPage): LengthAwarePaginator
    {
        return StockTransfer::with(['fromWarehouse', 'toWarehouse', 'lines.product', 'creator'])
            ->whereIn('status', [StockTransfer::STATUS_DRAFT, StockTransfer::STATUS_IN_TRANSIT])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new stock transfer.
     */
    public function create(array $data, array $lines): StockTransfer
    {
        return DB::transaction(function () use ($data, $lines) {
            // Validate different warehouses
            if ($data['from_warehouse_id'] === $data['to_warehouse_id']) {
                throw new \InvalidArgumentException(
                    'Source and destination warehouses must be different.'
                );
            }

            // Validate warehouse existence
            \App\Models\Inventory\Warehouse::findOrFail($data['from_warehouse_id']);
            \App\Models\Inventory\Warehouse::findOrFail($data['to_warehouse_id']);

            // Generate transfer number if not provided
            if (empty($data['transfer_number'])) {
                $data['transfer_number'] = $this->numberGenerator->generate('TRF');
            }

            $transfer = StockTransfer::create($data);

            foreach ($lines as $lineData) {
                // Get unit cost from source warehouse
                $stockLevel = $this->stockService->getStockLevel(
                    $lineData['product_id'],
                    $transfer->from_warehouse_id,
                    $lineData['variant_id'] ?? null
                );

                $lineData['unit_cost'] = $stockLevel?->average_cost ?? 0;
                $transfer->lines()->create($lineData);
            }

            return $transfer->load(['lines.product', 'lines.variant']);
        });
    }

    /**
     * Update a draft transfer.
     */
    public function update(StockTransfer $transfer, array $data, ?array $lines = null): StockTransfer
    {
        if (!$transfer->isEditable()) {
            throw new \InvalidArgumentException('Only draft transfers can be updated.');
        }

        return DB::transaction(function () use ($transfer, $data, $lines) {
            // Validate different warehouses if changing
            if (isset($data['from_warehouse_id']) || isset($data['to_warehouse_id'])) {
                $fromId = $data['from_warehouse_id'] ?? $transfer->from_warehouse_id;
                $toId = $data['to_warehouse_id'] ?? $transfer->to_warehouse_id;

                if ($fromId === $toId) {
                    throw new \InvalidArgumentException(
                        'Source and destination warehouses must be different.'
                    );
                }
            }

            $transfer->update($data);

            if ($lines !== null) {
                $transfer->lines()->delete();

                foreach ($lines as $lineData) {
                    $stockLevel = $this->stockService->getStockLevel(
                        $lineData['product_id'],
                        $transfer->from_warehouse_id,
                        $lineData['variant_id'] ?? null
                    );

                    $lineData['unit_cost'] = $stockLevel?->average_cost ?? 0;
                    $transfer->lines()->create($lineData);
                }
            }

            return $transfer->fresh(['lines.product', 'lines.variant']);
        });
    }

    /**
     * Ship a transfer (move stock out of source warehouse).
     *
     * Runs on the locked transfer, so a second submit waits and then finds it
     * in transit instead of taking the stock out again.
     */
    public function ship(StockTransfer $transfer, int $userId): StockTransfer
    {
        return $transfer->lockForTransition(function (StockTransfer $transfer) use ($userId): StockTransfer {
            if (! $transfer->canShip()) {
                throw new \InvalidArgumentException('Transfer cannot be shipped.');
            }

            // Validate stock availability for all lines
            foreach ($transfer->lines as $line) {
                if (!$this->stockService->hasAvailableStock(
                    $line->product_id,
                    $transfer->from_warehouse_id,
                    (float) $line->quantity_sent,
                    $line->variant_id
                )) {
                    throw new \InvalidArgumentException(
                        "Insufficient stock for product: {$line->product->name}"
                    );
                }
            }

            // Deduct stock from source warehouse
            foreach ($transfer->lines as $line) {
                $this->stockService->recordMovement(
                    productId: $line->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    movementType: StockMovement::TYPE_TRANSFER_OUT,
                    direction: 'out',
                    quantity: (float) $line->quantity_sent,
                    unitCost: (float) $line->unit_cost,
                    variantId: $line->variant_id,
                    referenceType: 'stock_transfer',
                    referenceId: $transfer->id,
                    referenceNumber: $transfer->transfer_number,
                    toWarehouseId: $transfer->to_warehouse_id
                );
            }

            $transfer->transitionTo(StockTransfer::STATUS_IN_TRANSIT, [
                'shipped_at' => now(),
                'shipped_by' => $userId,
            ]);

            return $transfer->fresh(['lines.product', 'lines.variant']);
        });
    }

    /**
     * Receive a transfer (add stock to destination warehouse).
     */
    public function receive(StockTransfer $transfer, array $receivedQuantities = [], int $userId = 0): StockTransfer
    {
        return $transfer->lockForTransition(function (StockTransfer $transfer) use ($receivedQuantities, $userId): StockTransfer {
            // Idempotency guard: prevent double-processing a completed transfer
            if ($transfer->status === StockTransfer::STATUS_RECEIVED) {
                throw new \LogicException('Stock transfer already completed.');
            }

            if (!$transfer->canReceive()) {
                throw new \InvalidArgumentException('Transfer cannot be received.');
            }

            foreach ($transfer->lines as $line) {
                // Use provided quantity or default to sent quantity
                $receivedQty = (float) ($receivedQuantities[$line->id] ?? $line->quantity_sent);

                // Update line with received quantity
                $line->update(['quantity_received' => $receivedQty]);

                // Add stock to destination warehouse
                if ($receivedQty > 0) {
                    $this->stockService->recordMovement(
                        productId: $line->product_id,
                        warehouseId: $transfer->to_warehouse_id,
                        movementType: StockMovement::TYPE_TRANSFER_IN,
                        direction: 'in',
                        quantity: $receivedQty,
                        unitCost: (float) $line->unit_cost,
                        variantId: $line->variant_id,
                        referenceType: 'stock_transfer',
                        referenceId: $transfer->id,
                        referenceNumber: $transfer->transfer_number,
                        fromWarehouseId: $transfer->from_warehouse_id
                    );
                }
            }

            $transfer->transitionTo(StockTransfer::STATUS_RECEIVED, [
                'received_at' => now(),
                'received_by' => $userId,
            ]);

            return $transfer->fresh(['lines.product', 'lines.variant']);
        });
    }

    /**
     * Cancel a transfer.
     *
     * Runs on the locked transfer: a receive committed meanwhile is seen, so
     * stock the destination took in is never also returned to the source.
     */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        return $transfer->lockForTransition(function (StockTransfer $transfer): StockTransfer {
            if ($transfer->status === StockTransfer::STATUS_RECEIVED) {
                throw new \InvalidArgumentException('Received transfers cannot be cancelled.');
            }

            if ($transfer->status === StockTransfer::STATUS_CANCELLED) {
                throw new \InvalidArgumentException('Transfer is already cancelled.');
            }

            // If in transit, return stock to source warehouse
            if ($transfer->status === StockTransfer::STATUS_IN_TRANSIT) {
                foreach ($transfer->lines as $line) {
                    $this->stockService->recordMovement(
                        productId: $line->product_id,
                        warehouseId: $transfer->from_warehouse_id,
                        movementType: StockMovement::TYPE_TRANSFER_IN,
                        direction: 'in',
                        quantity: (float) $line->quantity_sent,
                        unitCost: (float) $line->unit_cost,
                        variantId: $line->variant_id,
                        referenceType: 'stock_transfer',
                        referenceId: $transfer->id,
                        referenceNumber: $transfer->transfer_number . '-CANCEL',
                        notes: 'Transfer cancelled - stock returned'
                    );
                }
            }

            $transfer->transitionTo(StockTransfer::STATUS_CANCELLED);

            return $transfer->fresh(['lines.product', 'lines.variant']);
        });
    }

    /**
     * Get transfer summary.
     */
    public function getSummary(StockTransfer $transfer): array
    {
        $lines = $transfer->lines;

        $summary = [
            'total_lines' => $lines->count(),
            'total_quantity_sent' => $lines->sum('quantity_sent'),
            'total_value' => $lines->sum(fn($l) => $l->getTotalValue()),
        ];

        if ($transfer->status === StockTransfer::STATUS_RECEIVED) {
            $summary['total_quantity_received'] = $lines->sum('quantity_received');
            $summary['fully_received'] = $lines->filter(fn($l) => $l->isFullyReceived())->count();
            $summary['with_shortage'] = $lines->filter(fn($l) => $l->hasShortage())->count();
            $summary['with_excess'] = $lines->filter(fn($l) => $l->hasExcess())->count();
        }

        return $summary;
    }

    /**
     * Get pending transfers for a warehouse.
     */
    public function getPendingForWarehouse(int $warehouseId): array
    {
        $incoming = StockTransfer::with(['fromWarehouse', 'lines.product'])
            ->where('to_warehouse_id', $warehouseId)
            ->where('status', StockTransfer::STATUS_IN_TRANSIT)
            ->get();

        $outgoing = StockTransfer::with(['toWarehouse', 'lines.product'])
            ->where('from_warehouse_id', $warehouseId)
            ->where('status', StockTransfer::STATUS_IN_TRANSIT)
            ->get();

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ];
    }

    /**
     * Get overdue transfers.
     */
    public function getOverdue(): array
    {
        return StockTransfer::with(['fromWarehouse', 'toWarehouse', 'lines.product'])
            ->overdue()
            ->get()
            ->toArray();
    }
}
