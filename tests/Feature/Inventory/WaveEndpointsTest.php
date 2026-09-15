<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\PickingList;
use App\Models\Inventory\PickingListLine;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\WavePlan;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\WaveManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the putaway rule and wave lists, keeps putaway references and picking
 * lines inside the caller's organization, and releases a wave once.
 */
class WaveEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.warehouse-mgmt.view',
            'inventory.warehouse-mgmt.manage',
            'inventory.warehouse-mgmt.pick',
        ]);
        $this->actingAs($this->user, 'api');

        $this->store = $this->warehouse();
    }

    public function test_putaway_rules_filter_by_warehouse(): void
    {
        $match = PutawayRule::create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->store->id, 'priority' => 1]);
        PutawayRule::create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse('WH-2')->id, 'priority' => 1]);

        $response = $this->apiGet("/inventory/warehouse-mgmt/putaway-rules?warehouse_id={$this->store->id}");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_putaway_rule_refuses_another_organizations_product_or_location(): void
    {
        $response = $this->apiPost('/inventory/warehouse-mgmt/putaway-rules', [
            'warehouse_id' => $this->store->id,
            'product_id' => $this->foreignProduct()->id,
            'preferred_location_id' => $this->locationIn($this->foreignWarehouse())->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['product_id', 'preferred_location_id']);
    }

    public function test_waves_filter_by_status(): void
    {
        $draft = $this->createWave();
        WavePlan::whereKey($this->createWave())->update(['status' => WavePlan::STATUS_COMPLETED]);

        $response = $this->apiGet('/inventory/warehouse-mgmt/waves?status=draft');

        $response->assertOk();
        $this->assertSame([$draft], array_column($response->json('data'), 'id'));
    }

    public function test_a_stale_release_does_not_generate_picking_lists_twice(): void
    {
        $waveId = $this->createWave();
        $service = app(WaveManagementService::class);
        $wave = WavePlan::findOrFail($waveId);
        $stale = WavePlan::findOrFail($waveId);

        $service->releaseWave($wave, $this->user->id);
        $listsAfterRelease = PickingList::count();

        $this->assertRejected(fn () => $service->releaseWave($stale, $this->user->id));

        $this->assertSame($listsAfterRelease, PickingList::count());
    }

    public function test_another_organizations_picking_line_cannot_be_picked(): void
    {
        $theirWarehouse = $this->foreignWarehouse();
        $theirs = $this->otherOrganization()->id;

        $wave = WavePlan::withoutGlobalScopes()->forceCreate([
            'organization_id' => $theirs,
            'warehouse_id' => $theirWarehouse->id,
            'wave_number' => 'WAVE-THEIRS',
            'wave_type' => WavePlan::TYPE_OUTBOUND,
            'planned_date' => now()->toDateString(),
            'status' => WavePlan::STATUS_PICKING,
        ]);
        $list = PickingList::withoutGlobalScopes()->forceCreate([
            'organization_id' => $theirs,
            'wave_plan_id' => $wave->id,
            'warehouse_id' => $theirWarehouse->id,
            'list_number' => 'PKL-THEIRS',
            'status' => PickingList::STATUS_IN_PROGRESS,
            'picking_type' => PickingList::TYPE_MULTI_ORDER,
            'total_lines' => 1,
            'picked_lines' => 0,
        ]);
        $line = PickingListLine::forceCreate([
            'picking_list_id' => $list->id,
            'source_type' => 'sales_order',
            'source_id' => 1,
            'product_id' => $this->foreignProduct()->id,
            'required_quantity' => 5,
            'picked_quantity' => 0,
            'status' => PickingListLine::STATUS_PENDING,
            'sort_order' => 0,
        ]);

        $this->apiPost("/inventory/warehouse-mgmt/picking-list-lines/{$line->id}/pick", ['quantity' => 1])->assertNotFound();

        $this->assertEquals(0, (float) $line->fresh()->picked_quantity);
    }

    private function createWave(): int
    {
        return $this->apiPost('/inventory/warehouse-mgmt/waves', [
            'warehouse_id' => $this->store->id,
            'wave_type' => WavePlan::TYPE_OUTBOUND,
            'planned_date' => now()->toDateString(),
            'orders' => [['order_type' => 'sales_order', 'order_id' => 1]],
        ])->assertCreated()->json('data.id');
    }
}
