<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins warehouse listing, defaults and deletion, and keeps a warehouse's
 * branch, manager and code within the caller's organization.
 */
class WarehouseEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.warehouses.view',
            'inventory.warehouses.create',
            'inventory.warehouses.edit',
            'inventory.warehouses.delete',
        ]);
    }

    public function test_the_list_filters_active_warehouses(): void
    {
        $active = $this->warehouse('WH-A');
        $this->warehouse('WH-B', ['is_active' => false]);

        $response = $this->apiGet('/inventory/warehouses?active_only=1');

        $response->assertOk();
        $this->assertSame([$active->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_branch_or_manager_is_refused(): void
    {
        $theirBranch = $this->foreignWarehouse()->branch_id;

        $response = $this->apiPost('/inventory/warehouses', [
            'branch_id' => $theirBranch,
            'manager_id' => $this->foreignUser()->id,
            'name' => 'North',
            'code' => 'WH-N',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['branch_id', 'manager_id']);
    }

    public function test_a_code_used_by_another_organization_is_accepted(): void
    {
        $theirs = $this->foreignWarehouse();

        $response = $this->apiPost('/inventory/warehouses', [
            'branch_id' => $this->branch->id,
            'name' => 'North',
            'code' => $theirs->code,
        ]);

        $response->assertCreated();
    }

    public function test_a_code_already_used_in_the_organization_is_refused(): void
    {
        $this->warehouse('WH-A');

        $response = $this->apiPost('/inventory/warehouses', [
            'branch_id' => $this->branch->id,
            'name' => 'North',
            'code' => 'WH-A',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_a_new_default_replaces_the_previous_one(): void
    {
        $previous = $this->warehouse('WH-A', ['is_default' => true]);

        $response = $this->apiPost('/inventory/warehouses', [
            'branch_id' => $this->branch->id,
            'name' => 'North',
            'code' => 'WH-N',
            'is_default' => true,
        ]);

        $response->assertCreated();
        $this->assertFalse($previous->fresh()->is_default);
        $this->assertSame(1, Warehouse::where('is_default', true)->count());
    }

    public function test_a_warehouse_holding_stock_is_not_deleted(): void
    {
        $store = $this->warehouse('WH-A');
        $this->stockLevel($this->stockedProduct(), $store, 3);

        $this->apiDelete("/inventory/warehouses/{$store->id}")->assertStatus(422);

        $this->assertNotNull(Warehouse::find($store->id));
    }
}
