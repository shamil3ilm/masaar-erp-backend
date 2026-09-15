<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\User;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the order list and its guards, keeps order references inside the
 * caller's organization, and starts, completes and cancels an order once, on
 * the locked row, with the parts it used issued from stock in the same
 * transaction.
 */
class MaintenanceOrderLifecycleTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Equipment $equipment;

    private Warehouse $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.orders.view',
            'maintenance.orders.create',
            'maintenance.orders.edit',
            'maintenance.orders.delete',
            'maintenance.stats.view',
        ]);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->equipment = Equipment::factory()->create(['organization_id' => $this->organization->id]);
        $this->store = $this->warehouse();
    }

    public function test_orders_list_by_priority_then_newest_first(): void
    {
        $low = $this->order(['priority' => 'low', 'created_at' => now()->subDays(3)]);
        $olderHigh = $this->order(['priority' => 'high', 'created_at' => now()->subDays(2)]);
        $newerHigh = $this->order(['priority' => 'high', 'created_at' => now()->subDay()]);
        $critical = $this->order(['priority' => 'critical', 'created_at' => now()->subDays(4)]);
        $this->order(['priority' => 'critical', 'status' => MaintenanceOrder::STATUS_CANCELLED]);

        $response = $this->apiGet('/maintenance/orders?status=open&per_page=10');

        $response->assertOk()->assertJsonPath('meta.per_page', 10);
        $this->assertSame(
            [$critical->id, $newerHigh->id, $olderHigh->id, $low->id],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_another_organizations_order_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirs = MaintenanceOrder::factory()->create([
            'organization_id' => $other->id,
            'equipment_id' => Equipment::factory()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiGet("/maintenance/orders/{$theirs->id}")->assertNotFound();
        $this->apiPost("/maintenance/orders/{$theirs->id}/start")->assertNotFound();

        $this->assertSame(MaintenanceOrder::STATUS_OPEN, $theirs->fresh()->status);
    }

    public function test_an_order_for_another_organizations_equipment_assignee_or_part_is_refused(): void
    {
        $other = $this->otherOrganization();
        $theirEquipment = Equipment::factory()->create(['organization_id' => $other->id]);
        $theirUser = User::factory()->create(['organization_id' => $other->id]);
        $theirProduct = $this->foreignProduct();

        $this->apiPost('/maintenance/orders', [
            'equipment_id' => $theirEquipment->id,
            'order_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'description' => 'Replace bearing',
            'assigned_to' => $theirUser->id,
            'parts' => [['product_id' => $theirProduct->id, 'description' => 'Bearing']],
        ])->assertStatus(422)->assertJsonValidationErrors(['equipment_id', 'assigned_to', 'parts.0.product_id']);

        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->count());
    }

    public function test_a_closed_order_cannot_be_updated_and_an_open_one_can_be_put_on_hold(): void
    {
        $closed = $this->order(['status' => MaintenanceOrder::STATUS_COMPLETED]);
        $open = $this->order();

        $this->apiPut("/maintenance/orders/{$closed->id}", ['description' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ORDER_CLOSED');

        $this->apiPut("/maintenance/orders/{$open->id}", ['status' => MaintenanceOrder::STATUS_ON_HOLD])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrder::STATUS_ON_HOLD);
    }

    public function test_only_open_or_cancelled_orders_can_be_deleted(): void
    {
        $started = $this->order(['status' => MaintenanceOrder::STATUS_IN_PROGRESS]);
        $open = $this->order();

        $this->apiDelete("/maintenance/orders/{$started->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ORDER_NOT_DELETABLE');
        $this->apiDelete("/maintenance/orders/{$open->id}")->assertOk();

        $this->assertNotNull($started->fresh());
        $this->assertSoftDeleted($open);
    }

    public function test_completion_issues_the_parts_used_from_stock(): void
    {
        [$order, $product] = $this->startedOrderUsing(quantityUsed: 3, inStock: 10);

        $this->apiPost("/maintenance/orders/{$order->id}/complete", ['resolution_notes' => 'Replaced'])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrder::STATUS_COMPLETED);

        $this->assertEquals(7, $this->quantityOf($product, $this->store));
        $movement = StockMovement::where('reference_type', 'maintenance_order')->where('reference_id', $order->id)->sole();
        $this->assertSame(StockMovement::DIRECTION_OUT, $movement->direction);
        $this->assertSame(Equipment::STATUS_ACTIVE, $this->equipment->fresh()->status);
    }

    public function test_completion_is_refused_and_nothing_changes_when_a_used_part_is_short(): void
    {
        [$order, $product] = $this->startedOrderUsing(quantityUsed: 3, inStock: 2);

        $this->apiPost("/maintenance/orders/{$order->id}/complete")->assertStatus(422);

        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertEquals(2, $this->quantityOf($product, $this->store));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_completion_is_refused_when_a_used_part_has_no_stock(): void
    {
        [$order] = $this->startedOrderUsing(quantityUsed: 1, inStock: null);

        $this->apiPost("/maintenance/orders/{$order->id}/complete")->assertStatus(422);

        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_a_second_completion_from_a_stale_order_is_rejected(): void
    {
        [$order, $product] = $this->startedOrderUsing(quantityUsed: 3, inStock: 10);
        $stale = MaintenanceOrder::findOrFail($order->id);
        $service = app(MaintenanceService::class);

        $service->completeOrder($order, [], $this->user->id);

        $this->assertRejected(fn () => $service->completeOrder($stale, [], $this->user->id));
        $this->assertEquals(7, $this->quantityOf($product, $this->store));
        $this->assertSame(1, StockMovement::count());
    }

    public function test_a_stale_start_after_cancellation_is_rejected(): void
    {
        $order = $this->order();
        $stale = MaintenanceOrder::findOrFail($order->id);
        $service = app(MaintenanceService::class);

        $service->cancelOrder($order, $this->user->id);

        $this->assertRejected(fn () => $service->startOrder($stale, $this->user->id));
        $this->assertSame(MaintenanceOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_a_task_is_completed_once_and_only_under_its_own_order(): void
    {
        $order = $this->order();
        $task = $order->tasks()->create(['task_description' => 'Isolate supply', 'sort_order' => 1]);
        $otherOrder = $this->order();

        $this->apiPost("/maintenance/orders/{$otherOrder->id}/tasks/{$task->id}/complete")->assertNotFound();
        $this->apiPost("/maintenance/orders/{$order->id}/tasks/{$task->id}/complete", ['notes' => 'Done'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Done');
        $this->apiPost("/maintenance/orders/{$order->id}/tasks/{$task->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_stats_count_the_orders_in_the_period_by_type_and_status(): void
    {
        $this->order(['order_type' => MaintenanceOrder::TYPE_CORRECTIVE]);
        $this->order(['order_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => MaintenanceOrder::STATUS_CANCELLED]);
        $this->order(['order_type' => MaintenanceOrder::TYPE_INSPECTION, 'created_at' => now()->subYear()]);

        $from = now()->subWeek()->toDateString();
        $to = now()->toDateString();

        $this->apiGet("/maintenance/stats?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.by_type.corrective', 2)
            ->assertJsonPath('data.by_status.cancelled', 1);
    }

    private function order(array $overrides = []): MaintenanceOrder
    {
        return MaintenanceOrder::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'equipment_id' => $this->equipment->id,
            'status' => MaintenanceOrder::STATUS_OPEN,
        ], $overrides));
    }

    /**
     * An in-progress order with one part line that used $quantityUsed of a
     * product holding $inStock in the store, or no stock record when null.
     *
     * @return array{MaintenanceOrder, Product}
     */
    private function startedOrderUsing(float $quantityUsed, ?float $inStock): array
    {
        $product = $this->stockedProduct();

        if ($inStock !== null) {
            $this->stockLevel($product, $this->store, $inStock);
        }

        $order = $this->order([
            'status' => MaintenanceOrder::STATUS_IN_PROGRESS,
            'actual_start' => now()->subHour(),
        ]);
        $order->parts()->create([
            'product_id' => $product->id,
            'description' => 'Bearing',
            'quantity_required' => $quantityUsed,
            'quantity_used' => $quantityUsed,
            'unit_cost' => 5,
        ]);

        return [$order, $product];
    }
}
