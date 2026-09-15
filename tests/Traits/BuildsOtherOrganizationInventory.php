<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\User;

/**
 * Inventory rows that belong to a second organization, for tests that check a
 * request cannot reach or link another organization's data. Variants and
 * locations have no organization column; they belong to whoever owns their
 * product or warehouse.
 */
trait BuildsOtherOrganizationInventory
{
    private ?Organization $otherOrganization = null;

    protected function otherOrganization(): Organization
    {
        return $this->otherOrganization ??= Organization::factory()->create();
    }

    protected function foreignWarehouse(): Warehouse
    {
        $organization = $this->otherOrganization();

        return Warehouse::create([
            'organization_id' => $organization->id,
            'branch_id' => Branch::factory()->create(['organization_id' => $organization->id])->id,
            'name' => 'Their warehouse',
            'code' => 'WH-'.fake()->unique()->numerify('#####'),
            'is_active' => true,
        ]);
    }

    protected function foreignUnit(): UnitOfMeasure
    {
        return UnitOfMeasure::firstOrCreate(
            ['organization_id' => $this->otherOrganization()->id, 'symbol' => 'pc'],
            ['name' => 'Piece', 'conversion_factor' => 1, 'is_active' => true],
        );
    }

    protected function foreignProduct(): Product
    {
        return Product::create([
            'organization_id' => $this->otherOrganization()->id,
            'sku' => 'THEIR-'.fake()->unique()->numerify('#####'),
            'name' => 'Their product',
            'type' => Product::TYPE_GOODS,
            'unit_id' => $this->foreignUnit()->id,
            'purchase_price' => 5,
            'selling_price' => 10,
            'costing_method' => Product::COSTING_FIFO,
            'track_inventory' => true,
            'is_active' => true,
        ]);
    }

    protected function foreignBatch(): InventoryBatch
    {
        return InventoryBatch::create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'warehouse_id' => $this->foreignWarehouse()->id,
            'batch_number' => 'THEIR-B-'.fake()->unique()->numerify('#####'),
            'received_date' => now()->subDay()->toDateString(),
            'quantity' => 10,
            'reserved_quantity' => 0,
            'unit_cost' => 5,
            'status' => InventoryBatch::STATUS_AVAILABLE,
        ]);
    }

    protected function foreignUser(): User
    {
        return User::factory()->create(['organization_id' => $this->otherOrganization()->id]);
    }

    protected function variantOf(Product $product): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-'.fake()->unique()->numerify('#####'),
            'name' => 'Variant',
            'attributes' => ['size' => 'L'],
            'is_active' => true,
        ]);
    }

    protected function locationIn(Warehouse $warehouse): WarehouseLocation
    {
        return WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'name' => 'Bin',
            'code' => 'LOC-'.fake()->unique()->numerify('#####'),
            'type' => 'bin',
            'is_active' => true,
        ]);
    }
}
