<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Inventory\ShelfLabel;
use App\Services\Inventory\ShelfLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShelfLabelController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ShelfLabelService $shelfLabelService
    ) {}

    /**
     * List shelf labels.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['label_type', 'aisle']);
        $filters['needs_reprint'] = $request->boolean('needs_reprint');

        foreach (['branch_id', 'product_id'] as $id) {
            if ($request->has($id)) {
                $filters[$id] = $request->integer($id);
            }
        }

        foreach (['is_digital', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $filters[$flag] = $request->boolean($flag);
            }
        }

        return $this->success($this->shelfLabelService->list($filters));
    }

    /**
     * Create a shelf label.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', $this->ownedBy('branches')],
            'product_id' => ['required', 'integer', $this->ownedBy('products')],
            'variant_id' => ['nullable', 'integer', $this->ownedVariant()],
            'product_name' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode_value' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'unit_label' => 'nullable|string|max:50',
            'price_per_unit' => 'nullable|numeric|min:0',
            'unit_measure_label' => 'nullable|string|max:50',
            'aisle' => 'nullable|string|max:50',
            'shelf' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
            'is_digital' => 'boolean',
            'esl_device_id' => 'nullable|string|max:255',
        ]);

        $label = $this->shelfLabelService->create($validated);

        return $this->created($label, 'Shelf label created successfully.');
    }

    /**
     * Show a shelf label.
     */
    public function show(ShelfLabel $shelfLabel): JsonResponse
    {
        $shelfLabel->load(['product', 'variant', 'branch']);

        return $this->success($shelfLabel);
    }

    /**
     * Update a shelf label.
     */
    public function update(Request $request, ShelfLabel $shelfLabel): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode_value' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'unit_label' => 'nullable|string|max:50',
            'price_per_unit' => 'nullable|numeric|min:0',
            'unit_measure_label' => 'nullable|string|max:50',
            'aisle' => 'nullable|string|max:50',
            'shelf' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
            'is_active' => 'boolean',
        ]);

        return $this->success($this->shelfLabelService->update($shelfLabel, $validated), 'Shelf label updated successfully.');
    }

    /**
     * Delete a shelf label.
     */
    public function destroy(ShelfLabel $shelfLabel): JsonResponse
    {
        $shelfLabel->delete();

        return $this->success(null, 'Shelf label deleted successfully.');
    }

    /**
     * Generate shelf labels for given products.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => ['integer', $this->ownedBy('products')],
            'branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
            'currency_code' => 'nullable|string|size:3',
            'label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
        ]);

        $branchId = $validated['branch_id']
            ?? (int) $request->header('X-Branch-Id')
            ?: auth()->user()->branches()->wherePivot('is_default', true)->first()?->id;

        $currencyCode = $validated['currency_code']
            ?? auth()->user()->organization->base_currency
            ?? 'SAR';

        $items = [];
        foreach ($validated['product_ids'] as $productId) {
            $items[] = [
                'product_id' => $productId,
                'label_type' => $validated['label_type'] ?? 'standard',
                'label_size' => $validated['label_size'] ?? 'standard',
            ];
        }

        $results = $this->shelfLabelService->bulkCreate($items, $branchId, $currencyCode);

        $successCount = collect($results)->where('success', true)->count();
        $failedCount = collect($results)->where('success', false)->count();

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ], "Generated {$successCount} shelf labels.");
    }

    /**
     * Bulk create shelf labels.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', $this->ownedBy('branches')],
            'currency_code' => 'required|string|size:3',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => ['required', 'integer', $this->ownedBy('products')],
            'items.*.variant_id' => ['nullable', 'integer', $this->ownedVariant()],
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.compare_at_price' => 'nullable|numeric|min:0',
            'items.*.label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'items.*.label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
            'items.*.aisle' => 'nullable|string|max:50',
            'items.*.shelf' => 'nullable|string|max:50',
            'items.*.position' => 'nullable|string|max:50',
        ]);

        $results = $this->shelfLabelService->bulkCreate(
            $validated['items'],
            $validated['branch_id'],
            $validated['currency_code']
        );

        $successCount = collect($results)->where('success', true)->count();
        $failedCount = collect($results)->where('success', false)->count();

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ], "Bulk creation completed: {$successCount} succeeded, {$failedCount} failed.");
    }

    /**
     * Mark labels for reprint.
     */
    public function reprint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label_ids' => 'required|array|min:1|max:100',
            'label_ids.*' => ['integer', $this->ownedBy('shelf_labels')],
        ]);

        $count = $this->shelfLabelService->markForReprint($validated['label_ids']);

        return $this->success([
            'marked_count' => $count,
        ], "{$count} labels marked for reprint.");
    }
}
