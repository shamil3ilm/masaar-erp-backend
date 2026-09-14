<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;

/**
 * Warehouses, products, stock levels and batches for the organization set up
 * by TestHelpers, with only the columns a stock movement reads.
 */
trait BuildsInventory
{
    use AssertsRejection;

    protected function warehouse(string $code = 'WH-MAIN', array $overrides = []): Warehouse
    {
        return Warehouse::create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'name' => "Warehouse {$code}",
            'code' => $code,
            'is_active' => true,
        ], $overrides));
    }

    protected function stockedProduct(array $overrides = []): Product
    {
        $unit = UnitOfMeasure::firstOrCreate(
            ['organization_id' => $this->organization->id, 'symbol' => 'pc'],
            ['name' => 'Piece', 'conversion_factor' => 1, 'is_active' => true],
        );

        return Product::create(array_merge([
            'organization_id' => $this->organization->id,
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'name' => 'Stocked product',
            'type' => Product::TYPE_GOODS,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'selling_price' => 10,
            'costing_method' => Product::COSTING_FIFO,
            'track_inventory' => true,
            'is_active' => true,
        ], $overrides));
    }

    protected function stockLevel(Product $product, Warehouse $warehouse, float $quantity, float $averageCost = 5): StockLevel
    {
        return StockLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'average_cost' => $averageCost,
            'total_value' => $quantity * $averageCost,
        ]);
    }

    protected function batch(Product $product, Warehouse $warehouse, float $quantity, string $receivedDate = '-1 day'): InventoryBatch
    {
        return InventoryBatch::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-'.fake()->unique()->numerify('#####'),
            'received_date' => now()->modify($receivedDate)->toDateString(),
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'unit_cost' => 5,
            'status' => InventoryBatch::STATUS_AVAILABLE,
        ]);
    }

    protected function quantityOf(Product $product, Warehouse $warehouse): float
    {
        return (float) StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity');
    }
}
