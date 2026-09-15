<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomCoProduct;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderCoProductActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Co/by-product actuals are recorded only against the organization's own work
 * orders and BOM co-products.
 */
class CoProductActualsTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;
    private BomTemplate $bom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);

        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->bom = BomTemplate::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_actuals_for_another_organizations_work_order_are_refused(): void
    {
        $theirWorkOrder = WorkOrder::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $response = $this->apiPost("/manufacturing/co-products/work-order/{$theirWorkOrder->id}/actuals", [
            'actuals' => [['product_id' => $this->product->id, 'actual_quantity' => 2]],
        ]);

        $response->assertNotFound();
        $this->assertSame(0, WorkOrderCoProductActual::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_bom_co_product_is_refused(): void
    {
        $workOrder = $this->workOrder();
        $theirCoProduct = BomCoProduct::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'bom_template_id' => BomTemplate::factory()->create(['organization_id' => $this->otherOrganization()->id])->id,
            'product_id' => $this->foreignProduct()->id,
            'quantity_per_base' => 1,
        ]);

        $response = $this->apiPost("/manufacturing/co-products/work-order/{$workOrder->id}/actuals", [
            'actuals' => [[
                'product_id' => $this->product->id,
                'bom_co_product_id' => $theirCoProduct->id,
                'actual_quantity' => 2,
            ]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['actuals.0.bom_co_product_id']);
    }

    public function test_actuals_are_recorded_against_the_work_order(): void
    {
        $workOrder = $this->workOrder();

        $this->apiPost("/manufacturing/co-products/work-order/{$workOrder->id}/actuals", [
            'actuals' => [['product_id' => $this->product->id, 'actual_quantity' => 2]],
        ])->assertOk();

        $this->assertDatabaseHas('work_order_co_product_actuals', [
            'organization_id' => $this->organization->id,
            'work_order_id' => $workOrder->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_an_actual_of_another_work_order_is_not_posted_to_stock(): void
    {
        $workOrder = $this->workOrder();
        $actualId = WorkOrderCoProductActual::create([
            'organization_id' => $this->organization->id,
            'work_order_id' => $this->workOrder()->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 2,
        ])->id;

        $this->apiPost("/manufacturing/co-products/work-order/{$workOrder->id}/actuals/{$actualId}/post-stock")
            ->assertNotFound();
    }

    private function workOrder(): WorkOrder
    {
        return WorkOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->bom->id,
            'product_id' => $this->product->id,
        ]);
    }
}
