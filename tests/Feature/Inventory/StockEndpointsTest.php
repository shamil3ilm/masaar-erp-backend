<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the stock level and movement filters and keeps reservation and
 * availability requests inside the caller's organization.
 */
class StockEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.stock.view',
            'inventory.stock.reserve',
        ]);

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
    }

    public function test_levels_filter_by_warehouse(): void
    {
        $match = $this->stockLevel($this->product, $this->store, 5);
        $this->stockLevel($this->product, $this->warehouse('WH-2'), 7);

        $response = $this->apiGet("/inventory/stock/levels?warehouse_id={$this->store->id}");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_movements_filter_by_direction(): void
    {
        $this->movement('in');
        $out = $this->movement('out');

        $response = $this->apiGet('/inventory/stock/movements?direction=out');

        $response->assertOk();
        $this->assertSame([$out->id], array_column($response->json('data'), 'id'));
    }

    public function test_stock_of_another_organization_cannot_be_reserved(): void
    {
        $response = $this->apiPost('/inventory/stock/reserve', [
            'product_id' => $this->foreignProduct()->id,
            'warehouse_id' => $this->foreignWarehouse()->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['product_id', 'warehouse_id']);
    }

    public function test_availability_refuses_another_organizations_variant(): void
    {
        $response = $this->apiPost('/inventory/stock/check-availability', [
            'product_id' => $this->product->id,
            'variant_id' => $this->variantOf($this->foreignProduct())->id,
            'warehouse_id' => $this->store->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['variant_id']);
    }

    private function movement(string $direction): StockMovement
    {
        return StockMovement::create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'movement_type' => StockMovement::TYPE_ADJUSTMENT,
            'direction' => $direction,
            'quantity' => 1,
            'unit_cost' => 5,
            'total_cost' => 5,
            'balance_after' => 1,
        ]);
    }
}
