<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Inventory\Warehouse;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\Contact;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Purchase order endpoints not pinned by the flow tests: listing, the tenant
 * scope of ids an order names, and deleting and receiving on the locked order.
 */
class PurchaseOrderEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/purchase-orders';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'purchase.orders.view', 'purchase.orders.create', 'purchase.orders.delete',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_index_lists_the_organizations_orders_with_filters(): void
    {
        $this->order(['status' => PurchaseOrder::STATUS_DRAFT]);
        $this->order(['status' => PurchaseOrder::STATUS_CONFIRMED]);

        $other = Organization::factory()->create();
        PurchaseOrder::factory()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiGet($this->baseUrl)->assertOk()->assertJsonPath('meta.total', 2);
        $this->apiGet("{$this->baseUrl}?status=".PurchaseOrder::STATUS_CONFIRMED)
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_index_answers_an_ag_grid_request(): void
    {
        $this->order();

        $this->apiGet("{$this->baseUrl}?startRow=0&endRow=10")->assertOk();
    }

    public function test_store_refuses_another_organizations_warehouse(): void
    {
        $other = Organization::factory()->create();
        $foreignWarehouse = Warehouse::create([
            'organization_id' => $other->id,
            'branch_id' => Branch::factory()->create(['organization_id' => $other->id])->id,
            'name' => 'Foreign',
            'code' => 'WH-X',
            'is_active' => true,
        ]);

        $this->apiPost($this->baseUrl, [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $foreignWarehouse->id,
            'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');

        $this->assertSame(0, PurchaseOrder::withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
    }

    public function test_deleting_a_draft_removes_its_lines(): void
    {
        $order = $this->order(['status' => PurchaseOrder::STATUS_DRAFT]);
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id]);

        $this->apiDelete("{$this->baseUrl}/{$order->id}")->assertOk();

        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
        $this->assertSame(0, PurchaseOrderLine::where('purchase_order_id', $order->id)->count());
    }

    public function test_a_confirmed_order_is_not_deleted(): void
    {
        $order = $this->order(['status' => PurchaseOrder::STATUS_CONFIRMED]);

        $this->apiDelete("{$this->baseUrl}/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft/sent orders can be deleted.');
    }

    public function test_a_stale_draft_confirmed_meanwhile_is_not_deleted(): void
    {
        $this->actingAs($this->user, 'api');
        $order = $this->order(['status' => PurchaseOrder::STATUS_DRAFT]);
        $stale = PurchaseOrder::findOrFail($order->id);

        PurchaseOrder::whereKey($order->id)->update(['status' => PurchaseOrder::STATUS_CONFIRMED]);

        $this->assertRejected(fn () => app(PurchaseOrderService::class)->delete($stale));
        $this->assertNotSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_a_stale_order_cancelled_meanwhile_is_not_received(): void
    {
        $this->actingAs($this->user, 'api');
        $order = $this->order(['status' => PurchaseOrder::STATUS_CONFIRMED]);
        $line = PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id, 'quantity' => 5]);
        $stale = PurchaseOrder::findOrFail($order->id);

        PurchaseOrder::whereKey($order->id)->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        $this->assertRejected(fn () => app(PurchaseOrderService::class)->receive($stale, [$line->id => 2]));
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $line->fresh()->quantity_received, 0.0001);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->fail('The change was expected to be rejected.');
    }

    private function order(array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }
}
