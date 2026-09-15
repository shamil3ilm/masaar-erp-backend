<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\StorageType;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins storage types with their rules, keeps them to the caller's warehouses,
 * and resolves a rule only under its own storage type.
 */
class StorageTypeEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.storage-types.view',
            'inventory.storage-types.manage',
        ]);

        $this->store = $this->warehouse();
    }

    public function test_a_storage_type_is_shown_with_its_rules(): void
    {
        $typeId = $this->createType('RACK')->json('data.id');

        $this->apiPost("/inventory/storage-types/{$typeId}/rules", ['movement_type' => 'goods_receipt'])->assertCreated();

        $this->apiGet("/inventory/storage-types/{$typeId}")
            ->assertOk()
            ->assertJsonPath('data.warehouse.id', $this->store->id)
            ->assertJsonPath('data.determination_rules.0.movement_type', 'goods_receipt');
    }

    public function test_a_storage_type_for_another_organizations_warehouse_is_refused(): void
    {
        $response = $this->apiPost('/inventory/storage-types', [
            'warehouse_id' => $this->foreignWarehouse()->id,
            'storage_type_code' => 'RACK',
            'storage_type_name' => 'Rack',
            'storage_class' => 'rack',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);
        $this->assertSame(0, StorageType::withoutGlobalScopes()->count());
    }

    public function test_a_rule_named_under_another_storage_type_is_not_found(): void
    {
        $first = $this->createType('RACK')->json('data.id');
        $second = $this->createType('BULK')->json('data.id');
        $ruleId = $this->apiPost("/inventory/storage-types/{$second}/rules", ['movement_type' => 'goods_issue'])->json('data.id');

        $this->apiDelete("/inventory/storage-types/{$first}/rules/{$ruleId}")->assertNotFound();
    }

    public function test_another_organizations_storage_type_is_not_found(): void
    {
        $theirs = StorageType::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'warehouse_id' => $this->foreignWarehouse()->id,
            'storage_type_code' => 'RACK',
            'storage_type_name' => 'Rack',
            'storage_class' => 'rack',
        ]);

        $this->apiGet("/inventory/storage-types/{$theirs->id}")->assertNotFound();
        $this->apiDelete("/inventory/storage-types/{$theirs->id}")->assertNotFound();
    }

    private function createType(string $code): \Illuminate\Testing\TestResponse
    {
        return $this->apiPost('/inventory/storage-types', [
            'warehouse_id' => $this->store->id,
            'storage_type_code' => $code,
            'storage_type_name' => $code,
            'storage_class' => 'rack',
        ])->assertCreated();
    }
}
