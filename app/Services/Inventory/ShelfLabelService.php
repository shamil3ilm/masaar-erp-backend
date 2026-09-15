<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ShelfLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class ShelfLabelService
{
    public function __construct() {}

    /**
     * Create a shelf label.
     */
    public function create(array $data): ShelfLabel
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);

            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['product_name'] = $data['product_name'] ?? $product->name;
            $data['sku'] = $data['sku'] ?? $product->sku;
            $data['price'] = $data['price'] ?? $product->selling_price;
            $data['barcode_value'] = $data['barcode_value'] ?? $product->barcode;
            $data['needs_reprint'] = true;

            return ShelfLabel::create($data);
        });
    }

    /**
     * Bulk create shelf labels for multiple products.
     */
    public function bulkCreate(array $items, int $branchId, string $currencyCode = 'SAR'): array
    {
        $results = [];

        foreach ($items as $item) {
            try {
                $item['branch_id'] = $branchId;
                $item['currency_code'] = $item['currency_code'] ?? $currencyCode;

                $results[] = [
                    'product_id' => $item['product_id'],
                    'label' => $this->create($item),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'product_id' => $item['product_id'],
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Mark labels for reprint.
     */
    public function markForReprint(array $labelIds): int
    {
        return ShelfLabel::whereIn('id', $labelIds)
            ->where('is_digital', false)
            ->update(['needs_reprint' => true]);
    }

    /**
     * Sync a digital (ESL) label.
     */
    public function syncDigitalLabel(ShelfLabel $label): ShelfLabel
    {
        if (!$label->isDigital()) {
            throw new \InvalidArgumentException('Label is not a digital/ESL label.');
        }

        return DB::transaction(function () use ($label) {
            // Refresh data from the product
            $product = $label->product;

            $label->update([
                'product_name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->selling_price,
                'barcode_value' => $product->barcode,
                'last_synced_at' => now(),
            ]);

            return $label->fresh();
        });
    }

    /**
     * Shelf labels of the current organization with their product, variant and
     * branch, newest first. Each filter applies when its key is present;
     * needs_reprint only when true.
     *
     * @param  array{branch_id?: int, product_id?: int, label_type?: string, aisle?: string, needs_reprint?: bool, is_digital?: bool, is_active?: bool}  $filters
     * @return Collection<int, ShelfLabel>
     */
    public function list(array $filters): Collection
    {
        return ShelfLabel::with(['product', 'variant', 'branch'])
            ->latest()
            ->when(array_key_exists('branch_id', $filters), fn ($q) => $q->byBranch($filters['branch_id']))
            ->when(array_key_exists('product_id', $filters), fn ($q) => $q->forProduct($filters['product_id']))
            ->when(array_key_exists('label_type', $filters), fn ($q) => $q->byLabelType($filters['label_type']))
            ->when(array_key_exists('aisle', $filters), fn ($q) => $q->inAisle($filters['aisle']))
            ->when($filters['needs_reprint'] ?? false, fn ($q) => $q->needsReprint())
            ->when(array_key_exists('is_digital', $filters), fn ($q) => $filters['is_digital'] ? $q->digital() : $q->where('is_digital', false))
            ->when(array_key_exists('is_active', $filters), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->get();
    }

    /**
     * Update a label; a changed price marks it for reprint.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ShelfLabel $label, array $data): ShelfLabel
    {
        if (isset($data['price']) && $data['price'] != $label->price) {
            $data['needs_reprint'] = true;
        }

        $label->update($data);

        return $label->fresh();
    }
}
