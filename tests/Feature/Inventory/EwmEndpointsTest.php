<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\EwmBin;
use App\Models\Inventory\EwmStorageType;
use App\Models\Inventory\EwmTransferOrder;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the EWM transfer order list, keeps bin references inside the caller's
 * organization, and moves a transfer order's quantity through its bins once.
 */
class EwmEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;
    private EwmStorageType $storageType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.ewm.view',
            'inventory.ewm.manage',
            'inventory.ewm.transfer-orders.create',
            'inventory.ewm.transfer-orders.confirm',
            'inventory.ewm.transfer-orders.cancel',
        ]);

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
        $this->storageType = EwmStorageType::create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->store->id,
            'code' => 'BLK',
            'name' => 'Bulk',
            'type' => EwmStorageType::TYPE_BULK,
        ]);
    }

    public function test_a_bin_for_another_organizations_warehouse_or_storage_type_is_refused(): void
    {
        $theirWarehouse = $this->foreignWarehouse();
        $theirType = EwmStorageType::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'warehouse_id' => $theirWarehouse->id,
            'code' => 'BLK',
            'name' => 'Bulk',
            'type' => EwmStorageType::TYPE_BULK,
        ]);

        $response = $this->apiPost('/inventory/ewm/bins', [
            'warehouse_id' => $theirWarehouse->id,
            'storage_type_id' => $theirType->id,
            'bin_code' => 'A-01',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['warehouse_id', 'storage_type_id']);
        $this->assertSame(0, EwmBin::withoutGlobalScopes()->count());
    }

    public function test_transfer_orders_filter_by_status(): void
    {
        $created = $this->transferOrder($this->bin('A-01'));
        $cancelled = $this->transferOrder($this->bin('A-02'));
        $this->apiPost("/inventory/ewm/transfer-orders/{$cancelled['uuid']}/cancel")->assertOk();

        $response = $this->apiGet('/inventory/ewm/transfer-orders?status=created');

        $response->assertOk();
        $this->assertSame([$created['id']], array_column($response->json('data'), 'id'));
    }

    public function test_a_transfer_order_is_confirmed_once(): void
    {
        $bin = $this->bin('A-01');
        $order = $this->transferOrder($bin);

        $this->apiPost("/inventory/ewm/transfer-orders/{$order['uuid']}/confirm", ['confirmed_qty' => 10])->assertOk();
        $this->apiPost("/inventory/ewm/transfer-orders/{$order['uuid']}/confirm", ['confirmed_qty' => 10])->assertStatus(400);

        $this->assertEquals(10, (float) $bin->fresh()->current_weight_kg);
        $this->assertSame(EwmTransferOrder::STATUS_CONFIRMED, EwmTransferOrder::where('uuid', $order['uuid'])->value('status'));
    }

    public function test_another_organizations_bin_is_not_found(): void
    {
        $theirWarehouse = $this->foreignWarehouse();
        $theirType = EwmStorageType::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'warehouse_id' => $theirWarehouse->id,
            'code' => 'BLK',
            'name' => 'Bulk',
            'type' => EwmStorageType::TYPE_BULK,
        ]);
        $theirs = EwmBin::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'warehouse_id' => $theirWarehouse->id,
            'storage_type_id' => $theirType->id,
            'bin_code' => 'Z-01',
            'status' => EwmBin::STATUS_ACTIVE,
        ]);

        $this->apiGet("/inventory/ewm/bins/{$theirs->uuid}")->assertNotFound();
    }

    private function bin(string $code): EwmBin
    {
        return EwmBin::create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->store->id,
            'storage_type_id' => $this->storageType->id,
            'bin_code' => $code,
            'status' => EwmBin::STATUS_ACTIVE,
            'max_weight_kg' => 100,
            'current_weight_kg' => 0,
            'fill_pct' => 0,
        ]);
    }

    /**
     * @return array{id: int, uuid: string}
     */
    private function transferOrder(EwmBin $destination): array
    {
        $data = $this->apiPost('/inventory/ewm/transfer-orders', [
            'warehouse_id' => $this->store->id,
            'movement_type' => EwmTransferOrder::MOVEMENT_GOODS_RECEIPT,
            'dest_bin_id' => $destination->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10,
        ])->assertCreated()->json('data');

        return ['id' => $data['id'], 'uuid' => $data['uuid']];
    }
}
