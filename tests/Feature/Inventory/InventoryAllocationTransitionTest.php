<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Exceptions\ERP\InsufficientStockException;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryAllocationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Allocation reserves stock on locked stock levels and batches: stock is
 * promised once, and an allocation that cannot reserve all it asks for
 * reserves nothing.
 */
class InventoryAllocationTransitionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private InventoryAllocationService $service;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->warehouse = $this->warehouse();
        $this->service = app(InventoryAllocationService::class);
    }

    public function test_a_stale_second_allocation_does_not_over_reserve(): void
    {
        $product = $this->stockedProduct(['track_batches' => true]);
        $batch = $this->batch($product, $this->warehouse, 10);
        $level = $this->stockLevel($product, $this->warehouse, 10);

        $succeeded = 0;
        $allocate = function () use ($product, &$succeeded): void {
            try {
                $this->service->allocate($product->id, '6', $this->warehouse->id);
                $succeeded++;
            } catch (InsufficientStockException) {
            }
        };

        // A competing allocation of the same stock commits in the gap between
        // an allocation reading the batches without a lock and reserving. An
        // allocation that reads only locked rows, inside its transaction,
        // leaves no such gap, and the competitor runs after it instead.
        $baseline = DB::transactionLevel();
        $competed = false;

        DB::listen(function (QueryExecuted $query) use ($baseline, $allocate, &$competed): void {
            if ($competed || DB::transactionLevel() !== $baseline || ! str_contains($query->sql, 'inventory_batches')) {
                return;
            }

            $competed = true;
            $allocate();
        });

        $allocate();

        if (! $competed) {
            $competed = true;
            $allocate();
        }

        $this->assertSame(1, $succeeded);
        $this->assertEquals(6, (float) $batch->fresh()->reserved_quantity);
        $this->assertEquals(6, (float) $level->fresh()->reserved_quantity);
    }

    public function test_a_reservation_short_of_the_requested_quantity_throws_and_reserves_nothing(): void
    {
        $product = $this->stockedProduct(['track_batches' => true, 'allow_negative_stock' => true]);
        $batch = $this->batch($product, $this->warehouse, 10);
        $level = $this->stockLevel($product, $this->warehouse, 10);

        try {
            $this->service->allocate($product->id, '12', $this->warehouse->id);
            $this->fail('An allocation of more than is available was expected to throw.');
        } catch (InsufficientStockException) {
        }

        $this->assertEquals(0, (float) $batch->fresh()->reserved_quantity);
        $this->assertEquals(0, (float) $level->fresh()->reserved_quantity);
    }

    public function test_confirming_an_allocation_deducts_it_from_the_batch_and_the_stock_level(): void
    {
        $product = $this->stockedProduct(['track_batches' => true]);
        $batch = $this->batch($product, $this->warehouse, 10);
        $level = $this->stockLevel($product, $this->warehouse, 10);

        $allocation = $this->service->allocate($product->id, '6', $this->warehouse->id);
        $this->service->confirm($product->id, '6', $this->warehouse->id, $allocation->allocations);

        $this->assertEquals(4, (float) $batch->fresh()->quantity);
        $this->assertEquals(0, (float) $batch->fresh()->reserved_quantity);
        $this->assertEquals(4, (float) $level->fresh()->quantity);
        $this->assertEquals(0, (float) $level->fresh()->reserved_quantity);
    }
}
