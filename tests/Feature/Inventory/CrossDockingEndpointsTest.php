<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\CrossDockingOrder;
use App\Models\Inventory\CrossDockingOrderLine;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\CrossDockingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the cross-docking list, keeps order references inside the caller's
 * organization, and never transfers more than a line holds.
 */
class CrossDockingEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.cross-docking.view',
            'inventory.cross-docking.create',
            'inventory.cross-docking.manage',
        ]);
        $this->actingAs($this->user, 'api');

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
    }

    public function test_the_list_filters_by_status(): void
    {
        $planned = $this->order();
        $started = $this->order();
        app(CrossDockingService::class)->startTransfer($started);

        $response = $this->apiGet('/inventory/cross-docking?status=planned');

        $response->assertOk();
        $this->assertSame([$planned->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_warehouse_or_product_is_refused(): void
    {
        $response = $this->apiPost('/inventory/cross-docking', [
            ...$this->payload(),
            'warehouse_id' => $this->foreignWarehouse()->id,
            'lines' => [['product_id' => $this->foreignProduct()->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['warehouse_id', 'lines.0.product_id']);
        $this->assertSame(0, CrossDockingOrder::withoutGlobalScopes()->count());
    }

    public function test_an_order_is_created_for_the_organization(): void
    {
        $this->apiPost('/inventory/cross-docking', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.lines.0.product_id', $this->product->id);
    }

    public function test_a_transfer_from_a_stale_line_does_not_exceed_its_quantity(): void
    {
        $line = $this->order()->lines()->firstOrFail();
        $stale = CrossDockingOrderLine::findOrFail($line->id);
        $service = app(CrossDockingService::class);

        $service->transferLine($line, 4);

        try {
            $service->transferLine($stale, 4);
            $this->fail('A transfer from a stale line was expected to be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('exceeds remaining', $e->getMessage());
        }

        $this->assertEquals(4, (float) $line->fresh()->quantity_transferred);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'warehouse_id' => $this->store->id,
            'inbound_source_type' => 'purchase_order',
            'inbound_source_id' => 1,
            'outbound_dest_type' => 'sales_order',
            'outbound_dest_id' => 2,
            'planned_date' => now()->addDay()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ];
    }

    private function order(): CrossDockingOrder
    {
        return app(CrossDockingService::class)->createCrossDockingOrder([
            ...$this->payload(),
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
    }
}
