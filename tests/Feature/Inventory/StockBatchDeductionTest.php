<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * An outbound movement of a batch-tracked product takes the quantity off the
 * batches it draws from and off the stock level together, or off neither.
 */
class StockBatchDeductionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private StockService $service;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->warehouse = $this->warehouse();
        $this->product = $this->stockedProduct(['track_batches' => true]);
        $this->service = app(StockService::class);
    }

    public function test_an_outbound_movement_draws_on_the_oldest_batches_with_the_stock_level(): void
    {
        $older = $this->batch($this->product, $this->warehouse, 5, '-10 days');
        $newer = $this->batch($this->product, $this->warehouse, 10, '-1 day');
        $this->stockLevel($this->product, $this->warehouse, 15);

        $this->service->recordMovement($this->product->id, $this->warehouse->id, StockMovement::TYPE_SALE, StockMovement::DIRECTION_OUT, 8);

        $this->assertEquals(0, (float) $older->fresh()->quantity);
        $this->assertSame(InventoryBatch::STATUS_DEPLETED, $older->fresh()->status);
        $this->assertEquals(7, (float) $newer->fresh()->quantity);
        $this->assertEquals(7, $this->quantityOf($this->product, $this->warehouse));
    }

    public function test_a_batch_short_of_the_quantity_rolls_the_movement_back(): void
    {
        $batch = $this->batch($this->product, $this->warehouse, 5);
        $this->stockLevel($this->product, $this->warehouse, 10);

        $this->assertRejected(fn () => $this->service->recordMovement(
            $this->product->id, $this->warehouse->id, StockMovement::TYPE_SALE, StockMovement::DIRECTION_OUT, 8,
            batchId: $batch->id,
        ));

        $this->assertEquals(5, (float) $batch->fresh()->quantity);
        $this->assertEquals(10, $this->quantityOf($this->product, $this->warehouse));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_batch_held_in_another_warehouse_is_refused(): void
    {
        $elsewhere = $this->batch($this->product, $this->warehouse('WH-OTHER'), 10);
        $this->stockLevel($this->product, $this->warehouse, 10);

        $this->assertRejected(fn () => $this->service->recordMovement(
            $this->product->id, $this->warehouse->id, StockMovement::TYPE_SALE, StockMovement::DIRECTION_OUT, 4,
            batchId: $elsewhere->id,
        ));

        $this->assertEquals(10, (float) $elsewhere->fresh()->quantity);
        $this->assertEquals(10, $this->quantityOf($this->product, $this->warehouse));
    }
}
