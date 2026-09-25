<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\VendorSourceList;
use App\Models\Sales\Contact;
use App\Services\Manufacturing\MrpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * MRP planned orders are firmed and converted once, on the locked row, and
 * forecasts name only the organization's own products and warehouses.
 */
class MrpPlanningFlowTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.mrp.view',
            'manufacturing.mrp.run',
            'manufacturing.mrp.edit',
            'manufacturing.mrp.delete',
            'manufacturing.mrp.manage',
            'manufacturing.mrp.convert',
        ]);

        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_production_planned_order_converts_to_a_work_order_from_its_bom(): void
    {
        $bom = $this->activeBom();
        $order = $this->plannedOrder($this->organization->id, $this->product->id);

        $response = $this->apiPost("/manufacturing/mrp/planned-orders/{$order->id}/convert")->assertOk();

        $workOrder = WorkOrder::findOrFail($response->json('data.converted_to.id'));
        $this->assertSame($bom->id, $workOrder->bom_template_id);
        $this->assertNotEmpty($workOrder->work_order_number);
        $this->assertSame(MrpPlannedOrder::STATUS_CONVERTED, $order->fresh()->status);
    }

    public function test_a_stale_copy_cannot_convert_a_planned_order_twice(): void
    {
        $this->activeBom();
        $this->actingAs($this->user);
        $order = $this->plannedOrder($this->organization->id, $this->product->id);
        $stale = MrpPlannedOrder::findOrFail($order->id);
        $service = app(MrpService::class);

        $service->convertToOrder(MrpPlannedOrder::findOrFail($order->id), $this->user->id);

        try {
            $service->convertToOrder($stale, $this->user->id);
            $this->fail('A converted planned order was converted again.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked row, as expected.
        }

        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->where('product_id', $this->product->id)->count());
    }

    public function test_a_stale_copy_cannot_firm_a_planned_order_twice(): void
    {
        $order = $this->plannedOrder($this->organization->id, $this->product->id);
        $stale = MrpPlannedOrder::findOrFail($order->id);
        $service = app(MrpService::class);

        $service->firmPlannedOrder(MrpPlannedOrder::findOrFail($order->id), $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->firmPlannedOrder($stale, $this->user->id);
    }

    public function test_another_organizations_product_is_refused_on_a_forecast(): void
    {
        $this->apiPost('/manufacturing/mrp/forecasts', [
            'product_id' => $this->foreignProduct()->id,
            'forecast_date' => now()->toDateString(),
            'forecast_quantity' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    }

    public function test_another_organizations_planned_order_is_not_found(): void
    {
        $theirs = $this->plannedOrder($this->otherOrganization()->id, $this->foreignProduct()->id);

        $this->apiPost("/manufacturing/mrp/planned-orders/{$theirs->id}/firm")->assertNotFound();
        $this->apiPost("/manufacturing/mrp/planned-orders/{$theirs->id}/convert")->assertNotFound();

        $this->assertSame(MrpPlannedOrder::STATUS_PLANNED, $theirs->fresh()->status);
    }

    public function test_a_purchase_planned_order_converts_to_a_purchase_order_from_the_products_source_list(): void
    {
        $vendor = $this->sourceListVendor();
        $order = $this->plannedOrder($this->organization->id, $this->product->id, MrpPlannedOrder::TYPE_PURCHASE);

        $response = $this->apiPost("/manufacturing/mrp/planned-orders/{$order->id}/convert")->assertOk();

        $purchaseOrder = PurchaseOrder::with('lines')->findOrFail($response->json('data.converted_to.id'));
        $this->assertSame($vendor->id, $purchaseOrder->supplier_id);
        $this->assertNotEmpty($purchaseOrder->order_number);
        $this->assertCount(1, $purchaseOrder->lines);
        $this->assertSame($this->product->id, $purchaseOrder->lines->first()->product_id);
        $this->assertEqualsWithDelta(5.0, (float) $purchaseOrder->lines->first()->quantity, 0.0001);
        $this->assertSame(MrpPlannedOrder::STATUS_CONVERTED, $order->fresh()->status);
        $this->assertSame(PurchaseOrder::class, $order->fresh()->converted_to_type);
    }

    public function test_a_purchase_planned_order_without_a_supplier_is_refused(): void
    {
        $order = $this->plannedOrder($this->organization->id, $this->product->id, MrpPlannedOrder::TYPE_PURCHASE);

        $this->apiPost("/manufacturing/mrp/planned-orders/{$order->id}/convert")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, PurchaseOrder::withoutGlobalScopes()->count());
        $this->assertSame(MrpPlannedOrder::STATUS_PLANNED, $order->fresh()->status);
    }

    public function test_a_stale_copy_cannot_convert_a_purchase_planned_order_twice(): void
    {
        $this->sourceListVendor();
        $this->actingAs($this->user);
        $order = $this->plannedOrder($this->organization->id, $this->product->id, MrpPlannedOrder::TYPE_PURCHASE);
        $stale = MrpPlannedOrder::findOrFail($order->id);
        $service = app(MrpService::class);

        $service->convertToOrder(MrpPlannedOrder::findOrFail($order->id), $this->user->id);

        try {
            $service->convertToOrder($stale, $this->user->id);
            $this->fail('A converted purchase planned order was converted again.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked row, as expected.
        }

        $this->assertSame(1, PurchaseOrder::withoutGlobalScopes()->count());
    }

    public function test_a_purchase_planned_order_above_the_approval_threshold_converts_to_an_order_pending_approval(): void
    {
        config(['erp.po_approval_threshold' => 1000]);
        $this->sourceListVendor();
        $this->product->update(['purchase_price' => 5000]);
        $order = $this->plannedOrder($this->organization->id, $this->product->id, MrpPlannedOrder::TYPE_PURCHASE);

        $response = $this->apiPost("/manufacturing/mrp/planned-orders/{$order->id}/convert")->assertOk();

        $purchaseOrder = PurchaseOrder::findOrFail($response->json('data.converted_to.id'));
        $this->assertGreaterThan(1000, (float) $purchaseOrder->total);
        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $purchaseOrder->status);
        $this->assertSame(MrpPlannedOrder::STATUS_CONVERTED, $order->fresh()->status);
    }

    /**
     * A vendor on the product's source list. The product gets a low purchase
     * price so the planned quantity stays under the purchase order approval
     * threshold, whose routing is not what these tests cover.
     */
    private function sourceListVendor(): Contact
    {
        $this->product->update(['purchase_price' => 10]);

        $vendor = Contact::factory()->create(['organization_id' => $this->organization->id]);

        VendorSourceList::create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'vendor_id' => $vendor->id,
            'is_blocked' => false,
            'priority' => 1,
        ]);

        return $vendor;
    }

    private function activeBom(): BomTemplate
    {
        return BomTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
        ]);
    }

    private function plannedOrder(int $organizationId, int $productId, string $type = MrpPlannedOrder::TYPE_PRODUCTION): MrpPlannedOrder
    {
        $run = MrpRun::factory()->create([
            'organization_id' => $organizationId,
            'run_by' => $this->user->id,
        ]);

        return MrpPlannedOrder::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'mrp_run_id' => $run->id,
            'product_id' => $productId,
            'order_type' => $type,
            'planned_quantity' => 5,
            'planned_start_date' => now()->toDateString(),
            'planned_end_date' => now()->addWeek()->toDateString(),
            'status' => MrpPlannedOrder::STATUS_PLANNED,
        ]);
    }
}
