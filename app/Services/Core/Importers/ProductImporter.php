<?php

declare(strict_types=1);

namespace App\Services\Core\Importers;

use App\Models\Core\ImportJob;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Tax\TaxCategory;
use App\Services\Core\ImporterInterface;
use App\Services\Inventory\StockService;
use Illuminate\Support\Str;

class ProductImporter implements ImporterInterface
{
    public function importRow(array $data, ImportJob $importJob, array $options = []): mixed
    {
        $organizationId = $importJob->organization_id;

        $existing = null;
        if ($options['update_existing'] ?? false) {
            $existing = Product::where('organization_id', $organizationId)
                ->where('sku', $data['sku'])
                ->first();
        }

        // Everything a row can be refused for is checked before anything is
        // written, because the import commits what a failed row already saved.
        if (! $existing && empty($data['unit'])) {
            throw new \InvalidArgumentException('A new product needs a unit of measure.');
        }

        $taxCategoryId = empty($data['tax_category'])
            ? null
            : $this->taxCategoryId($organizationId, (string) $data['tax_category']);

        // An opening balance is recorded once, when the product is created. A
        // re-import does not add it again.
        $openingStock = $existing ? 0 : (float) ($data['opening_stock'] ?? 0);
        $warehouse = $openingStock > 0 ? $this->defaultWarehouse($organizationId) : null;

        $categoryId = null;
        if (! empty($data['category'])) {
            $categoryId = Category::firstOrCreate(
                ['organization_id' => $organizationId, 'name' => $data['category']],
                ['slug' => Str::slug($data['category']), 'is_active' => true],
            )->id;
        }

        $productData = [
            'organization_id' => $organizationId,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'goods',
            'category_id' => $categoryId,
            'unit_id' => empty($data['unit']) ? null : $this->unitId($organizationId, (string) $data['unit']),
            'purchase_price' => $data['purchase_price'] ?? 0,
            'selling_price' => $data['selling_price'] ?? 0,
            'tax_category_id' => $taxCategoryId,
            'hsn_code' => $data['hsn_code'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'reorder_level' => $data['reorder_level'] ?? null,
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update(array_filter($productData, fn ($v) => $v !== null));
            $product = $existing;
        } else {
            $product = Product::create($productData);
        }

        // Through the stock ledger, so the balance has a movement behind it
        // and the average cost is kept the way every other receipt keeps it.
        if ($warehouse !== null) {
            app(StockService::class)->recordMovement(
                productId: $product->id,
                warehouseId: $warehouse->id,
                movementType: StockMovement::TYPE_OPENING,
                direction: StockMovement::DIRECTION_IN,
                quantity: $openingStock,
                unitCost: (float) ($data['purchase_price'] ?? 0),
                referenceType: 'import',
                referenceId: $importJob->id,
            );
        }

        return $product;
    }

    /**
     * Matched by code (S, Z, E, O) or by name. Never created here: a category
     * without its rates would tax the product at nothing.
     */
    private function taxCategoryId(int $organizationId, string $value): int
    {
        $category = TaxCategory::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->where('code', strtoupper($value))->orWhere('name', $value))
            ->first();

        if ($category === null) {
            throw new \InvalidArgumentException("Tax category '{$value}' is not set up for this organisation.");
        }

        return $category->id;
    }

    private function defaultWarehouse(int $organizationId): Warehouse
    {
        $warehouse = Warehouse::where('organization_id', $organizationId)
            ->where('is_default', true)
            ->first();

        if ($warehouse === null) {
            throw new \InvalidArgumentException('Opening stock needs a default warehouse, and this organisation has none.');
        }

        return $warehouse;
    }

    /**
     * Matched by name or symbol. A new unit takes the name as its symbol, cut
     * to the column's ten characters, so units that share a prefix stay apart.
     */
    private function unitId(int $organizationId, string $unit): int
    {
        $symbol = mb_substr($unit, 0, 10);

        return UnitOfMeasure::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->where('name', $unit)->orWhere('symbol', $symbol))
            ->first()?->id
            ?? UnitOfMeasure::create([
                'organization_id' => $organizationId,
                'name' => $unit,
                'symbol' => $symbol,
            ])->id;
    }
}
