<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenanceServiceOrder;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the service order list and the dates its status changes stamp, and
 * keeps service order references inside the caller's organization.
 */
class MaintenanceServiceOrderTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['maintenance.service-orders.view', 'maintenance.service-orders.manage']);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_a_service_order_is_created_as_a_draft_with_its_sla_deadlines(): void
    {
        $this->apiPost('/maintenance/service-orders', [
            'service_type' => 'repair',
            'description' => 'Rewind motor',
            'requested_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'sla_response_hours' => '4',
        ])->assertCreated()
            ->assertJsonPath('data.status', MaintenanceServiceOrder::STATUS_DRAFT)
            ->assertJsonPath('data.created_by', $this->user->id);

        $this->assertNotNull(MaintenanceServiceOrder::sole()->sla_response_due_at);
    }

    public function test_a_service_order_for_another_organizations_equipment_order_or_vendor_is_refused(): void
    {
        $theirEquipment = Equipment::factory()->create(['organization_id' => $this->other->id]);
        $theirOrder = MaintenanceOrder::factory()->create([
            'organization_id' => $this->other->id,
            'equipment_id' => $theirEquipment->id,
        ]);
        $theirVendor = Contact::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/maintenance/service-orders', [
            'equipment_id' => $theirEquipment->id,
            'maintenance_order_id' => $theirOrder->id,
            'vendor_id' => $theirVendor->id,
            'service_type' => 'repair',
            'description' => 'Rewind motor',
            'requested_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['equipment_id', 'maintenance_order_id', 'vendor_id']);

        $this->assertSame(0, MaintenanceServiceOrder::withoutGlobalScopes()->count());
    }

    public function test_service_orders_filter_by_status_newest_first(): void
    {
        $older = $this->serviceOrder(['created_at' => now()->subDays(2)]);
        $newer = $this->serviceOrder(['created_at' => now()->subDay()]);
        $this->serviceOrder(['status' => MaintenanceServiceOrder::STATUS_ISSUED]);

        $response = $this->apiGet('/maintenance/service-orders?status=draft');

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_confirming_and_completing_stamp_their_dates(): void
    {
        $serviceOrder = $this->serviceOrder();

        $this->apiPut("/maintenance/service-orders/{$serviceOrder->id}", ['status' => MaintenanceServiceOrder::STATUS_CONFIRMED])
            ->assertOk();
        $this->assertNotNull($serviceOrder->fresh()->vendor_responded_at);

        $this->apiPut("/maintenance/service-orders/{$serviceOrder->id}", ['status' => MaintenanceServiceOrder::STATUS_COMPLETED])
            ->assertOk();
        $this->assertSame(now()->toDateString(), $serviceOrder->fresh()->completed_date->toDateString());
    }

    public function test_another_organizations_service_order_is_not_found(): void
    {
        $theirs = $this->serviceOrder(['organization_id' => $this->other->id]);

        $this->apiGet("/maintenance/service-orders/{$theirs->id}")->assertNotFound();
        $this->apiDelete("/maintenance/service-orders/{$theirs->id}")->assertNotFound();

        $this->assertNotNull($theirs->fresh());
    }

    private function serviceOrder(array $overrides = []): MaintenanceServiceOrder
    {
        return tap((new MaintenanceServiceOrder)->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'service_order_number' => 'SO-'.fake()->unique()->numerify('#####'),
            'status' => MaintenanceServiceOrder::STATUS_DRAFT,
            'service_type' => 'repair',
            'description' => 'Rewind motor',
            'requested_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
        ], $overrides)))->save();
    }
}
