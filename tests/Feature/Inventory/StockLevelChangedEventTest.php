<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Events\Inventory\StockLevelChanged;
use App\Models\Core\Notification;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Notifications\Inventory\LowStockNotification;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StockLevelChangedEventTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Warehouse $warehouse;

    private Product $product;

    private StockLevel $stockLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->warehouse = $this->makeWarehouse('WH-A');

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => Product::TYPE_GOODS,
            'track_inventory' => true,
            'track_batches' => false,
            'has_expiry' => false,
        ]);

        // Twenty on hand against a reorder level of ten.
        $this->stockLevel = StockLevel::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'variant_id' => null,
            'location_id' => null,
            'quantity' => 20,
            'reserved_quantity' => 0,
            'average_cost' => 5,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
        ]);
    }

    public function test_a_movement_dispatches_the_quantities_either_side_of_it(): void
    {
        Event::fake([StockLevelChanged::class]);

        $this->issue(15, referenceId: 77);

        Event::assertDispatched(StockLevelChanged::class, fn (StockLevelChanged $event) => $event->stockLevel->id === $this->stockLevel->id
            && $event->previousQuantity === 20.0
            && $event->newQuantity === 5.0
            && $event->movementType === StockMovement::TYPE_ADJUSTMENT
            && $event->referenceType === 'stock_adjustment'
            && $event->referenceId === 77);
    }

    public function test_a_transfer_dispatches_for_both_warehouses(): void
    {
        Event::fake([StockLevelChanged::class]);

        $destination = $this->makeWarehouse('WH-B');

        app(StockService::class)->transfer($this->product->id, $this->warehouse->id, $destination->id, 5);

        Event::assertDispatched(StockLevelChanged::class, fn (StockLevelChanged $event) => $event->stockLevel->warehouse_id === $this->warehouse->id
            && $event->newQuantity === 15.0);
        Event::assertDispatched(StockLevelChanged::class, fn (StockLevelChanged $event) => $event->stockLevel->warehouse_id === $destination->id
            && $event->previousQuantity === 0.0
            && $event->newQuantity === 5.0);
    }

    public function test_crossing_the_reorder_level_notifies_inventory_managers(): void
    {
        $this->makeInventoryManager();

        $this->issue(15);

        $this->assertSame(1, $this->lowStockNotifications());
    }

    public function test_staying_above_the_reorder_level_notifies_nobody(): void
    {
        $this->makeInventoryManager();

        $this->issue(5);

        $this->assertSame(0, $this->lowStockNotifications());
    }

    private function issue(float $quantity, ?int $referenceId = null): void
    {
        app(StockService::class)->recordMovement(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            movementType: StockMovement::TYPE_ADJUSTMENT,
            direction: StockMovement::DIRECTION_OUT,
            quantity: $quantity,
            unitCost: 5.0,
            referenceType: 'stock_adjustment',
            referenceId: $referenceId,
        );
    }

    private function makeWarehouse(string $code): Warehouse
    {
        return Warehouse::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'code' => $code,
            'allow_negative_stock' => false,
        ]);
    }

    private function makeInventoryManager(): void
    {
        $role = Role::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'inventory-manager',
        ]);

        $this->user->roles()->attach($role->id);
    }

    private function lowStockNotifications(): int
    {
        return Notification::where('user_id', $this->user->id)
            ->where('type', LowStockNotification::class)
            ->count();
    }
}
