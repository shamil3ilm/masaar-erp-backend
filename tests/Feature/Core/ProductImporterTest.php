<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ImportJob;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Tax\TaxCategory;
use App\Services\Core\Importers\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A product row is written whole or not at all.
 *
 * The import commits a failed row's partial writes, so anything a row can be
 * refused for is checked before its category or unit is created.
 */
class ProductImporterTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ImportJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_PRODUCTS,
        ]);
    }

    public function test_a_row_sets_its_tax_category_and_reorder_level(): void
    {
        $standard = TaxCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'S',
            'name' => 'Standard Rate',
        ]);

        $byCode = $this->import(['sku' => 'P-1', 'unit' => 'Piece', 'tax_category' => 'S', 'reorder_level' => 5]);
        $byName = $this->import(['sku' => 'P-2', 'unit' => 'Piece', 'tax_category' => 'Standard Rate']);

        $this->assertSame($standard->id, $byCode->fresh()->tax_category_id);
        $this->assertSame($standard->id, $byName->fresh()->tax_category_id);
        $this->assertEquals(5, $byCode->fresh()->reorder_level);
        $this->assertSame(1, UnitOfMeasure::where('organization_id', $this->organization->id)->count());
    }

    public function test_an_unknown_tax_category_is_refused_before_anything_is_written(): void
    {
        $this->assertRefused('Tax category', [
            'sku' => 'P-1',
            'unit' => 'Box',
            'category' => 'Fresh Category',
            'tax_category' => 'X',
        ]);

        $this->assertSame(0, Category::where('name', 'Fresh Category')->count());
        $this->assertSame(0, UnitOfMeasure::where('name', 'Box')->count());
    }

    public function test_a_new_product_without_a_unit_is_refused(): void
    {
        $this->assertRefused('unit of measure', ['sku' => 'P-1']);
    }

    public function test_opening_stock_is_recorded_as_an_opening_movement_in_the_default_warehouse(): void
    {
        $warehouse = $this->defaultWarehouse();

        $product = $this->import(['sku' => 'P-1', 'unit' => 'Piece', 'opening_stock' => 12, 'purchase_price' => 4]);

        $level = StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->sole();
        $this->assertEquals(12, $level->quantity);
        $this->assertEquals(4, $level->average_cost);

        $movement = StockMovement::where('product_id', $product->id)->sole();
        $this->assertSame(StockMovement::TYPE_OPENING, $movement->movement_type);
        $this->assertSame(StockMovement::DIRECTION_IN, $movement->direction);
        $this->assertEquals(12, $movement->quantity);
    }

    public function test_opening_stock_without_a_default_warehouse_is_refused(): void
    {
        $this->assertRefused('default warehouse', ['sku' => 'P-1', 'unit' => 'Piece', 'opening_stock' => 12]);
    }

    public function test_a_reimport_does_not_add_opening_stock_again(): void
    {
        $this->defaultWarehouse();
        $row = ['sku' => 'P-1', 'unit' => 'Piece', 'opening_stock' => 12];

        $first = $this->import($row);
        $second = $this->import($row + ['name' => 'Renamed'], ['update_existing' => true]);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(12, StockLevel::where('product_id', $first->id)->sole()->quantity);
        $this->assertSame(1, StockMovement::where('product_id', $first->id)->count());
    }

    public function test_units_that_share_a_prefix_stay_separate(): void
    {
        $gram = $this->import(['sku' => 'P-1', 'unit' => 'Kilogram']);
        $grams = $this->import(['sku' => 'P-2', 'unit' => 'Kilograms']);

        $this->assertNotSame($gram->unit_id, $grams->unit_id);
    }

    private function import(array $row, array $options = []): Product
    {
        return (new ProductImporter)->importRow($row + ['name' => 'Widget'], $this->job, $options);
    }

    private function assertRefused(string $reason, array $row): void
    {
        try {
            $this->import($row);
            $this->fail('The row was imported.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($reason, $e->getMessage());
        }

        $this->assertSame(0, Product::where('organization_id', $this->organization->id)->count());
    }

    private function defaultWarehouse(): Warehouse
    {
        return Warehouse::factory()->create([
            'organization_id' => $this->organization->id,
            'is_default' => true,
        ]);
    }
}
