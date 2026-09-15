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
 * Pins the movement statistics: the organization's movements inside the
 * look-back window, grouped by type, most frequent first.
 */
class MovementTypeEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    public function test_statistics_group_the_organizations_recent_movements_by_type(): void
    {
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['inventory.stock.view']);

        $store = $this->warehouse();
        $product = $this->stockedProduct();

        $this->movement($this->organization->id, $store, $product, StockMovement::TYPE_ADJUSTMENT, -3, now()->subDay());
        $this->movement($this->organization->id, $store, $product, StockMovement::TYPE_ADJUSTMENT, 2, now()->subDays(2));
        $this->movement($this->organization->id, $store, $product, StockMovement::TYPE_PURCHASE, 5, now()->subDays(3));
        $this->movement($this->organization->id, $store, $product, StockMovement::TYPE_SALE, 7, now()->subDays(40));
        $this->movement($this->otherOrganization()->id, $this->foreignWarehouse(), $this->foreignProduct(), StockMovement::TYPE_SALE, 1, now()->subDay());

        $response = $this->apiGet('/inventory/movement-types/statistics?days=30');

        $response->assertOk()->assertJsonPath('data.period_days', 30);
        $movements = $response->json('data.movements');
        $this->assertSame([StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_PURCHASE], array_column($movements, 'movement_type'));
        $this->assertEquals(2, $movements[0]['count']);
        $this->assertEquals(5, $movements[0]['total_quantity']);
    }

    private function movement(int $organizationId, Warehouse $warehouse, Product $product, string $type, float $quantity, \DateTimeInterface $at): void
    {
        StockMovement::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => $type,
            'direction' => $quantity < 0 ? 'out' : 'in',
            'quantity' => $quantity,
            'unit_cost' => 1,
            'total_cost' => abs($quantity),
            'balance_after' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
