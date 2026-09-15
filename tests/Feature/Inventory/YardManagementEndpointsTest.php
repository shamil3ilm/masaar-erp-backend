<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\DockDoor;
use App\Models\Inventory\TruckAppointment;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\YardMovement;
use App\Models\Inventory\YardZone;
use App\Models\Sales\Contact;
use App\Services\Inventory\YardManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the zone and appointment lists, keeps vendor tax numbers out of them,
 * keeps yard references inside the caller's organization, and departs a truck
 * once.
 */
class YardManagementEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.yard.view',
            'inventory.yard.manage',
        ]);
        $this->actingAs($this->user, 'api');

        $this->store = $this->warehouse();
    }

    public function test_zones_filter_by_warehouse_in_code_order(): void
    {
        $second = $this->zone('Z-B', $this->store);
        $first = $this->zone('Z-A', $this->store);
        $this->zone('Z-C', $this->warehouse('WH-2'));

        $response = $this->apiGet("/inventory/yard/zones?warehouse_id={$this->store->id}");

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_zone_for_another_organizations_warehouse_is_refused(): void
    {
        $response = $this->apiPost('/inventory/yard/zones', [
            'warehouse_id' => $this->foreignWarehouse()->id,
            'zone_code' => 'Z-1',
            'name' => 'Staging',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);
        $this->assertSame(0, YardZone::withoutGlobalScopes()->count());
    }

    public function test_appointments_show_the_vendor_without_its_tax_number(): void
    {
        $vendor = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
            'tax_number' => '300000000000003',
        ]);
        $appointment = $this->appointment(TruckAppointment::STATUS_SCHEDULED, ['vendor_id' => $vendor->id]);

        $list = $this->apiGet('/inventory/yard/appointments')->assertOk();
        $this->assertSame($vendor->contact_name, $list->json('data.0.vendor.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $list->json('data.0.vendor'));

        $show = $this->apiGet("/inventory/yard/appointments/{$appointment->id}")->assertOk();
        $this->assertArrayNotHasKey('tax_number', $show->json('data.vendor'));
    }

    public function test_another_organizations_dock_door_is_refused(): void
    {
        $appointment = $this->appointment(TruckAppointment::STATUS_CHECKED_IN);
        $theirDoor = DockDoor::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'warehouse_id' => $this->foreignWarehouse()->id,
            'door_code' => 'D-1',
            'status' => DockDoor::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $response = $this->apiPost("/inventory/yard/appointments/{$appointment->id}/assign-dock", [
            'dock_door_id' => $theirDoor->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['dock_door_id']);
        $this->assertSame(DockDoor::STATUS_AVAILABLE, DockDoor::withoutGlobalScopes()->findOrFail($theirDoor->id)->status);
    }

    public function test_a_departure_from_a_stale_copy_is_not_recorded_twice(): void
    {
        $appointment = $this->appointment(TruckAppointment::STATUS_CHECKED_IN);
        $stale = TruckAppointment::findOrFail($appointment->id);
        $service = app(YardManagementService::class);

        $service->depart($appointment);

        try {
            $service->depart($stale);
            $this->fail('A departure from a stale copy was expected to be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cannot be departed', $e->getMessage());
        }

        $this->assertSame(1, YardMovement::where('movement_type', YardMovement::TYPE_DEPARTURE)->count());
    }

    private function zone(string $code, Warehouse $warehouse): YardZone
    {
        return YardZone::create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $warehouse->id,
            'zone_code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function appointment(string $status, array $overrides = []): TruckAppointment
    {
        return TruckAppointment::create(array_merge([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->store->id,
            'appointment_number' => 'APT-'.fake()->unique()->numerify('#####'),
            'scheduled_arrival' => now()->addHour(),
            'status' => $status,
        ], $overrides));
    }
}
