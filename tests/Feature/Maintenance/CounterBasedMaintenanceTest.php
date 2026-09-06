<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\EquipmentCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers counter-based maintenance: equipment counters, their readings, and the
 * endpoints that expose them.
 *
 * Also pins the model-to-table mapping, which the SAP "pm_" prefix rename moved.
 */
class CounterBasedMaintenanceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.counters.manage',
        ]);

        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code'     => 'maintenance',
            'is_enabled'      => true,
        ]);
    }

    private function makeCounter(string $name, string $uom): EquipmentCounter
    {
        return EquipmentCounter::create([
            'organization_id' => $this->organization->id,
            'uuid'            => (string) Str::uuid(),
            'counter_name'    => $name,
            'uom'             => $uom,
        ]);
    }

    public function test_counter_is_stored_in_the_equipment_counters_table(): void
    {
        $this->postJson('/api/v1/maintenance/counters', [
            'counter_name' => 'Engine hours',
            'uom'          => 'h',
        ], $this->authHeaders())->assertCreated();

        $this->assertDatabaseHas('equipment_counters', [
            'counter_name'    => 'Engine hours',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_counter_requires_a_name_and_unit(): void
    {
        $this->postJson('/api/v1/maintenance/counters', [], $this->authHeaders())
            ->assertUnprocessable();
    }

    public function test_counters_endpoint_lists_them(): void
    {
        $this->makeCounter('Odometer', 'km');

        $this->getJson('/api/v1/maintenance/counters', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_reading_is_recorded_against_the_counter(): void
    {
        $counter = $this->makeCounter('Engine hours', 'h');

        $this->postJson("/api/v1/maintenance/counters/{$counter->id}/readings", [
            'reading_value' => 125.5,
            'reading_date'  => now()->toDateString(),
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseHas('counter_readings', ['counter_id' => $counter->id]);
    }

    public function test_counter_plans_endpoint_responds(): void
    {
        $this->getJson('/api/v1/maintenance/counter-plans', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_counter_orders_endpoint_responds(): void
    {
        $this->getJson('/api/v1/maintenance/counter-orders', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_task_lists_endpoint_responds(): void
    {
        $this->getJson('/api/v1/maintenance/task-lists', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/maintenance/counters')->assertUnauthorized();
    }
}
