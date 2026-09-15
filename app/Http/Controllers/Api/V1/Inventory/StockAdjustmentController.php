<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\StockAdjustmentResource;
use App\Models\Inventory\StockAdjustment;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private StockAdjustmentService $adjustmentService
    ) {
    }

    /**
     * List stock adjustments.
     */
    public function index(Request $request): JsonResponse
    {
        $adjustments = $this->adjustmentService->list(
            $request->only(['warehouse_id', 'status', 'reason', 'from_date', 'to_date']),
            $request->integer('per_page', 15)
        );

        return $this->paginated($adjustments, StockAdjustmentResource::class);
    }

    /**
     * Create a new stock adjustment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            'adjustment_date' => 'required|date',
            'reason' => 'required|in:damage,theft,expiry,count_correction,opening_balance,other',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            ...$this->lineRules(),
        ]);

        try {
            $adjustment = $this->adjustmentService->create(
                collect($validated)->except('lines')->toArray(),
                $validated['lines']
            );

            return $this->created(new StockAdjustmentResource($adjustment), 'Stock adjustment created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a stock adjustment.
     */
    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $stockAdjustment->load(['warehouse', 'lines.product', 'lines.variant', 'creator', 'poster']);

        return $this->success(new StockAdjustmentResource($stockAdjustment));
    }

    /**
     * Update a draft stock adjustment.
     */
    public function update(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->isEditable()) {
            return $this->error(
                'Only draft adjustments can be updated.',
                'INVALID_STATUS',
                422
            );
        }

        $validated = $request->validate([
            'adjustment_date' => 'sometimes|date',
            'reason' => 'sometimes|in:damage,theft,expiry,count_correction,opening_balance,other',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'nullable|array|min:1',
            ...$this->lineRules(),
        ]);

        try {
            $adjustment = $this->adjustmentService->update(
                $stockAdjustment,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );

            return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Post a stock adjustment.
     */
    public function post(StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->canPost()) {
            return $this->error(
                'Adjustment cannot be posted. Only draft adjustments with lines can be posted.',
                'INVALID_STATUS',
                422
            );
        }

        try {
            $adjustment = $this->adjustmentService->post($stockAdjustment, auth()->id());

            return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment posted successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Cancel a draft stock adjustment.
     */
    public function cancel(StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== StockAdjustment::STATUS_DRAFT) {
            return $this->error(
                'Only draft adjustments can be cancelled.',
                'INVALID_STATUS',
                422
            );
        }

        try {
            $adjustment = $this->adjustmentService->cancel($stockAdjustment);

            return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment cancelled.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Get adjustment summary.
     */
    public function summary(StockAdjustment $stockAdjustment): JsonResponse
    {
        $summary = $this->adjustmentService->getSummary($stockAdjustment);

        return $this->success($summary);
    }

    /**
     * Quick adjustment for a single product, created and posted together.
     */
    public function quickAdjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', $this->ownedBy('products')],
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            'actual_quantity' => 'required|numeric|min:0',
            'reason' => 'required|in:damage,theft,expiry,count_correction,opening_balance,other',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $adjustment = $this->adjustmentService->quickAdjust(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['actual_quantity'],
                $validated['reason'],
                auth()->id(),
                $validated['notes'] ?? null
            );

            return $this->success(new StockAdjustmentResource($adjustment->fresh()), 'Stock adjusted successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Rules for adjustment lines; every referenced row must belong to the
     * caller's organization.
     *
     * @return array<string, mixed>
     */
    private function lineRules(): array
    {
        return [
            'lines.*.product_id' => ['required', 'integer', $this->ownedBy('products')],
            'lines.*.variant_id' => ['nullable', 'integer', $this->ownedVariant()],
            'lines.*.location_id' => ['nullable', 'integer', $this->ownedLocation()],
            'lines.*.actual_quantity' => 'required|numeric|min:0',
            'lines.*.system_quantity' => 'nullable|numeric',
            'lines.*.unit_cost' => 'nullable|numeric',
            'lines.*.notes' => 'nullable|string|max:255',
        ];
    }
}
