<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\Inventory\WarehouseTransferOrder;
use App\Services\Inventory\WarehouseTransferOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Transfer orders are reached by id within the organization, reference only
 * the organization's rows, and move stock once however often they are
 * confirmed.
 */
class WarehouseTransferOrderEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;
    private WarehouseLocation $source;
    private WarehouseLocation $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.transfer-orders.view',
            'inventory.transfer-orders.manage',
        ]);
        $this->actingAs($this->user, 'api');

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
        $this->source = $this->locationIn($this->store);
        $this->destination = $this->locationIn($this->store);

        StockLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'location_id' => $this->source->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'average_cost' => 5,
            'total_value' => 50,
        ]);
    }

    public function test_an_order_is_shown_updated_and_deleted_by_its_id(): void
    {
        $order = $this->order();

        $this->apiGet("/inventory/transfer-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.to_number', $order->to_number);

        $this->apiPut("/inventory/transfer-orders/{$order->id}", ['source_document_ref' => 'PO-1'])
            ->assertOk()
            ->assertJsonPath('data.source_document_ref', 'PO-1');

        $this->apiDelete("/inventory/transfer-orders/{$order->id}")->assertOk();
        $this->assertSoftDeleted($order);
    }

    public function test_references_of_another_organization_are_refused(): void
    {
        $theirWarehouse = $this->foreignWarehouse();

        $response = $this->apiPost('/inventory/transfer-orders', [
            'warehouse_id' => $theirWarehouse->id,
            'source_location_id' => $this->locationIn($theirWarehouse)->id,
            'assigned_to' => $this->foreignUser()->id,
            'items' => [[
                'product_id' => $this->foreignProduct()->id,
                'variant_id' => $this->variantOf($this->foreignProduct())->id,
                'requested_quantity' => 1,
            ]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'warehouse_id', 'source_location_id', 'assigned_to', 'items.0.product_id', 'items.0.variant_id',
        ]);
        $this->assertSame(0, WarehouseTransferOrder::withoutGlobalScopes()->count());
    }

    public function test_a_confirm_from_a_stale_copy_does_not_move_stock_again(): void
    {
        $service = app(WarehouseTransferOrderService::class);
        $order = $service->startTransfer($this->order());
        $stale = WarehouseTransferOrder::with('items')->findOrFail($order->id);
        $quantities = [['item_id' => $order->items()->value('id'), 'transferred_quantity' => 4]];

        $service->confirmTransfer($order, $quantities);

        $this->assertRejected(fn () => $service->confirmTransfer($stale, $quantities));

        $this->assertEquals(6, $this->quantityAt($this->source));
        $this->assertEquals(4, $this->quantityAt($this->destination));
    }

    private function order(): WarehouseTransferOrder
    {
        return app(WarehouseTransferOrderService::class)->create([
            'warehouse_id' => $this->store->id,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
            'items' => [['product_id' => $this->product->id, 'requested_quantity' => 4]],
        ]);
    }

    private function quantityAt(WarehouseLocation $location): float
    {
        return (float) StockLevel::where('product_id', $this->product->id)
            ->where('location_id', $location->id)
            ->value('quantity');
    }
}
