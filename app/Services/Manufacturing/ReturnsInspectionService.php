<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Inventory\StockMovement;
use App\Models\Manufacturing\ReturnsInspectionDefect;
use App\Models\Manufacturing\ReturnsInspectionLot;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ReturnsInspectionService
{
    /** The reference a lot's stock movements carry, so they can be traced back to it. */
    public const STOCK_REFERENCE_TYPE = 'returns_inspection_lot';

    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
    ) {}

    /**
     * List returns inspection lots with optional filters.
     */
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return ReturnsInspectionLot::with(['product', 'warehouse'])
            ->withCount('defects')
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['return_type']),
                fn ($q) => $q->where('return_type', $filters['return_type'])
            )
            ->when(
                isset($filters['product_id']),
                fn ($q) => $q->where('product_id', $filters['product_id'])
            )
            ->when(
                isset($filters['warehouse_id']),
                fn ($q) => $q->where('warehouse_id', $filters['warehouse_id'])
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where('lot_number', 'like', '%' . $filters['search'] . '%')
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a new returns inspection lot.
     */
    public function create(array $data): ReturnsInspectionLot
    {
        $data['lot_number'] = $this->numberGenerator->generate('RIL');

        return ReturnsInspectionLot::create($data);
    }

    /**
     * Find a lot by integer ID or UUID string.
     */
    public function show(int|string $id): ReturnsInspectionLot
    {
        if (is_int($id) || ctype_digit((string) $id)) {
            $lot = ReturnsInspectionLot::with(['product', 'warehouse', 'defects', 'qualityPlan'])
                ->find((int) $id);
        } else {
            $lot = ReturnsInspectionLot::with(['product', 'warehouse', 'defects', 'qualityPlan'])
                ->where('uuid', $id)
                ->first();
        }

        if ($lot === null) {
            throw new InvalidArgumentException('Returns inspection lot not found.');
        }

        return $lot;
    }

    /**
     * A defect of the given lot, by integer ID or UUID string.
     *
     * The defect is looked up through the lot, so an id that belongs to
     * another lot is not found.
     */
    public function findDefect(ReturnsInspectionLot $lot, int|string $defectId): ReturnsInspectionDefect
    {
        $column = is_int($defectId) || ctype_digit((string) $defectId) ? 'id' : 'uuid';

        $defect = $lot->defects()->where($column, $defectId)->first();

        if ($defect === null) {
            throw new InvalidArgumentException('Defect record not found.');
        }

        return $defect;
    }

    /**
     * Transition a lot from open → in_inspection.
     */
    public function startInspection(ReturnsInspectionLot $lot): ReturnsInspectionLot
    {
        return $lot->lockForTransition(function (ReturnsInspectionLot $lot): ReturnsInspectionLot {
            if (! $lot->canStartInspection()) {
                throw new InvalidArgumentException(
                    "Inspection can only be started when the lot is in 'open' status. "
                    . "Current status: {$lot->status}."
                );
            }

            $lot->startInspection();

            return $lot->fresh();
        });
    }

    /**
     * Add a defect record to an inspection lot.
     */
    public function addDefect(ReturnsInspectionLot $lot, array $data): ReturnsInspectionDefect
    {
        $data['returns_inspection_lot_id'] = $lot->id;
        $data['organization_id']           = $lot->organization_id;

        return ReturnsInspectionDefect::create($data);
    }

    /**
     * Update an existing defect record.
     */
    public function updateDefect(ReturnsInspectionDefect $defect, array $data): ReturnsInspectionDefect
    {
        $defect->update($data);

        return $defect->fresh();
    }

    /**
     * Remove a defect record.
     */
    public function removeDefect(ReturnsInspectionDefect $defect): void
    {
        $defect->delete();
    }

    /**
     * Record the usage decision for a lot.
     *
     * The status is checked on the locked lot, so a stale copy cannot replace
     * the quantities of a lot that was already decided.
     */
    public function makeUsageDecision(ReturnsInspectionLot $lot, array $data): ReturnsInspectionLot
    {
        return $lot->lockForTransition(function (ReturnsInspectionLot $lot) use ($data): ReturnsInspectionLot {
            if (! $lot->canMakeUsageDecision()) {
                throw new InvalidArgumentException(
                    "Usage decision can only be made when the lot is 'in_inspection'. "
                    . "Current status: {$lot->status}."
                );
            }

            $lot->makeUsageDecision(
                decision: $data['usage_decision'],
                accepted: (float) ($data['accepted_quantity'] ?? 0),
                rejected: (float) ($data['rejected_quantity'] ?? 0),
                rework:   (float) ($data['rework_quantity']   ?? 0),
                userId:   (int) ($data['user_id'] ?? auth()->id()),
                notes:    $data['notes'] ?? null,
            );

            return $lot->fresh();
        });
    }

    /**
     * Post the lot's stock movement and close it.
     *
     * Returned goods reach the lot from outside stock. The accepted quantity
     * goes back into the lot's warehouse as a return receipt; the rejected and
     * rework quantities never become usable stock, so they move nothing. The
     * guard is checked on the locked lot and the receipt and the posted flag
     * are written in one transaction, so the quantity is received once.
     */
    public function postStockMovements(ReturnsInspectionLot $lot): ReturnsInspectionLot
    {
        return $lot->lockForTransition(function (ReturnsInspectionLot $lot): ReturnsInspectionLot {
            if (! $lot->canPostStock()) {
                throw new InvalidArgumentException(
                    "Stock can only be posted after a usage decision has been made and has not yet been posted."
                );
            }

            $accepted = (float) $lot->accepted_quantity;

            if ($accepted > 0 && $lot->warehouse_id === null) {
                throw new InvalidArgumentException(
                    'A warehouse is required to put the accepted quantity back into stock.'
                );
            }

            if ($accepted > 0) {
                $this->stockService->recordMovement(
                    productId: $lot->product_id,
                    warehouseId: $lot->warehouse_id,
                    movementType: StockMovement::TYPE_RETURN_IN,
                    direction: StockMovement::DIRECTION_IN,
                    quantity: $accepted,
                    referenceType: self::STOCK_REFERENCE_TYPE,
                    referenceId: $lot->id,
                    referenceNumber: $lot->lot_number,
                    notes: "Accepted quantity from returns inspection lot {$lot->lot_number}",
                );
            }

            $lot->update([
                'stock_posted'    => true,
                'stock_posted_at' => now(),
                'status'          => ReturnsInspectionLot::STATUS_CLOSED,
            ]);

            return $lot->fresh();
        });
    }

    /**
     * Cancel an open inspection lot.
     */
    public function cancel(ReturnsInspectionLot $lot): ReturnsInspectionLot
    {
        return $lot->lockForTransition(function (ReturnsInspectionLot $lot): ReturnsInspectionLot {
            if ($lot->status !== ReturnsInspectionLot::STATUS_OPEN) {
                throw new InvalidArgumentException(
                    "Only lots in 'open' status can be cancelled. Current status: {$lot->status}."
                );
            }

            $lot->update(['status' => ReturnsInspectionLot::STATUS_CANCELLED]);

            return $lot->fresh();
        });
    }
}
