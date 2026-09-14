<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Shipping, receiving and cancelling a transfer check its status on the
 * locked transfer, so stock leaves the source once and a transfer that has
 * been received cannot also be returned to the source.
 */
class StockTransferTransitionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private StockTransferService $service;
    private Warehouse $source;
    private Warehouse $destination;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->source = $this->warehouse('WH-SRC');
        $this->destination = $this->warehouse('WH-DST');
        $this->product = $this->stockedProduct();
        $this->stockLevel($this->product, $this->source, 10);

        $this->service = app(StockTransferService::class);
    }

    public function test_a_second_ship_from_a_stale_transfer_is_rejected(): void
    {
        $transfer = $this->draftTransfer(4);
        $stale = StockTransfer::findOrFail($transfer->id);

        $this->service->ship($transfer, $this->user->id);

        $this->assertRejected(fn () => $this->service->ship($stale, $this->user->id));
        $this->assertEquals(6, $this->quantityOf($this->product, $this->source));
    }

    public function test_cancelling_a_stale_transfer_that_was_received_is_rejected(): void
    {
        $transfer = $this->service->ship($this->draftTransfer(4), $this->user->id);
        $stale = StockTransfer::findOrFail($transfer->id);

        $this->service->receive($transfer, [], $this->user->id);

        $this->assertRejected(fn () => $this->service->cancel($stale));
        $this->assertEquals(6, $this->quantityOf($this->product, $this->source));
        $this->assertEquals(4, $this->quantityOf($this->product, $this->destination));
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $transfer->fresh()->status);
    }

    public function test_cancelling_a_transfer_in_transit_returns_its_stock_to_the_source(): void
    {
        $transfer = $this->service->ship($this->draftTransfer(4), $this->user->id);

        $cancelled = $this->service->cancel($transfer);

        $this->assertSame(StockTransfer::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals(10, $this->quantityOf($this->product, $this->source));
    }

    private function draftTransfer(float $quantity): StockTransfer
    {
        return $this->service->create([
            'organization_id' => $this->organization->id,
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->destination->id,
            'transfer_date' => now()->toDateString(),
            'status' => StockTransfer::STATUS_DRAFT,
        ], [[
            'product_id' => $this->product->id,
            'quantity_sent' => $quantity,
        ]]);
    }
}
