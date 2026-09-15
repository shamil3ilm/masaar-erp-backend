<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\CounterBasedOrder;
use App\Models\Maintenance\CounterBasedPlan;
use App\Models\Maintenance\CounterReading;
use App\Models\Maintenance\EquipmentCounter;
use App\Models\Maintenance\FunctionalLocation;
use App\Models\Maintenance\LocationEquipment;
use App\Models\Maintenance\MaintenanceTaskList;
use App\Services\Maintenance\CounterBasedMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Keeps counters, counter plans and counter orders inside the caller's
 * organization, measures a reading from the counter's latest value, and
 * completes an order once.
 */
class CounterBasedMaintenanceEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.counters.manage',
            'maintenance.counter-orders.view',
            'maintenance.counter-orders.manage',
            'maintenance.counter-plans.manage',
        ]);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_a_counter_for_another_organizations_location_or_equipment_is_refused(): void
    {
        $theirLocation = $this->location($this->other);
        $theirEquipment = LocationEquipment::create([
            'organization_id' => $this->other->id,
            'floc_id' => $theirLocation->id,
            'equipment_number' => 'LE-1',
            'description' => 'Their compressor',
        ]);

        $this->apiPost('/maintenance/counters', [
            'counter_name' => 'Running hours',
            'uom' => 'h',
            'floc_id' => $theirLocation->id,
            'equipment_id' => $theirEquipment->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['floc_id', 'equipment_id']);

        $this->assertSame(0, EquipmentCounter::withoutGlobalScopes()->count());
    }

    public function test_a_plan_for_another_organizations_location_counter_or_task_list_is_refused(): void
    {
        $theirTaskList = MaintenanceTaskList::create([
            'organization_id' => $this->other->id,
            'task_list_number' => 'TL-THEIRS',
            'description' => 'Their task list',
        ]);

        $this->apiPost('/maintenance/counter-plans', [
            'plan_number' => 'PLAN-1',
            'plan_type' => 'counter_based',
            'floc_id' => $this->location($this->other)->id,
            'counter_id' => $this->counter($this->other)->id,
            'task_list_id' => $theirTaskList->id,
            'counter_interval' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors(['floc_id', 'counter_id', 'task_list_id']);

        $this->assertSame(0, CounterBasedPlan::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_counter_plan_and_order_are_not_found(): void
    {
        $counter = $this->counter($this->other);
        $plan = $this->plan($this->other, $counter);
        $order = $this->order($this->other, $plan);

        $this->apiPost("/maintenance/counters/{$counter->id}/readings", [
            'reading_value' => 10,
            'reading_date' => now()->toDateString(),
        ])->assertNotFound();
        $this->apiPost("/maintenance/counter-plans/{$plan->id}/generate-order")->assertNotFound();
        $this->apiPost("/maintenance/counter-orders/{$order->id}/complete")->assertNotFound();

        $this->assertSame(0, CounterReading::withoutGlobalScopes()->count());
        $this->assertSame('created', $order->fresh()->status);
    }

    public function test_lists_show_only_the_organizations_rows(): void
    {
        $counter = $this->counter($this->organization);
        $this->order($this->organization, $this->plan($this->organization, $counter));
        $theirCounter = $this->counter($this->other);
        $this->order($this->other, $this->plan($this->other, $theirCounter));

        $this->apiGet('/maintenance/counters')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 20);
        $this->apiGet('/maintenance/counter-plans')->assertOk()->assertJsonPath('meta.total', 1);
        $this->apiGet('/maintenance/counter-orders')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_a_reading_from_a_stale_counter_is_measured_from_the_latest_reading(): void
    {
        $counter = $this->counter($this->organization, 100);
        $stale = EquipmentCounter::findOrFail($counter->id);
        $service = app(CounterBasedMaintenanceService::class);

        $service->recordReading($counter, 150, now(), $this->user->id);
        $reading = $service->recordReading($stale, 170, now(), $this->user->id);

        $this->assertEquals(20, (float) $reading->delta_value);
        $this->assertEquals(170, (float) $counter->fresh()->current_reading);
    }

    public function test_generating_an_order_moves_the_plan_to_the_next_due_reading(): void
    {
        $plan = $this->plan($this->organization, $this->counter($this->organization, 500));

        $this->apiPost("/maintenance/counter-plans/{$plan->id}/generate-order")
            ->assertCreated()
            ->assertJsonPath('data.maintenance_plan_id', $plan->id);

        $this->assertEquals(600, (float) $plan->fresh()->next_due_reading);
        $this->assertEquals(500, (float) $plan->fresh()->last_maintenance_reading);
    }

    public function test_an_order_is_completed_once(): void
    {
        $order = $this->order($this->organization, $this->plan($this->organization, $this->counter($this->organization)));

        $this->apiPost("/maintenance/counter-orders/{$order->id}/complete", ['actual_end' => '2026-09-10'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->apiPost("/maintenance/counter-orders/{$order->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertSame('2026-09-10', $order->fresh()->actual_end->toDateString());
    }

    private function location(Organization $organization): FunctionalLocation
    {
        return FunctionalLocation::create([
            'organization_id' => $organization->id,
            'code' => 'FL-'.fake()->unique()->numerify('####'),
            'name' => 'Line',
            'location_type' => FunctionalLocation::TYPE_LINE,
        ]);
    }

    private function counter(Organization $organization, float $reading = 0): EquipmentCounter
    {
        return EquipmentCounter::create([
            'organization_id' => $organization->id,
            'counter_name' => 'Running hours',
            'uom' => 'h',
            'current_reading' => $reading,
        ]);
    }

    private function plan(Organization $organization, EquipmentCounter $counter): CounterBasedPlan
    {
        return CounterBasedPlan::create([
            'organization_id' => $organization->id,
            'plan_number' => 'PLAN-'.fake()->unique()->numerify('####'),
            'plan_type' => 'counter_based',
            'counter_id' => $counter->id,
            'counter_interval' => 100,
            'active' => true,
        ]);
    }

    private function order(Organization $organization, CounterBasedPlan $plan): CounterBasedOrder
    {
        return CounterBasedOrder::create([
            'organization_id' => $organization->id,
            'order_number' => 'PMO-'.fake()->unique()->numerify('####'),
            'maintenance_plan_id' => $plan->id,
            'order_type' => 'preventive',
            'description' => 'Service',
            'status' => 'created',
            'priority' => 'normal',
        ]);
    }
}
