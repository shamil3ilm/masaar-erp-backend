<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\FuelLog;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MileageLog;
use App\Models\Maintenance\Vehicle;
use App\Models\Maintenance\VehicleAssignment;
use App\Models\Maintenance\VehicleMaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the vehicle list, logs and cost summary, keeps vehicle references inside
 * the caller's organization, and shows drivers by reference only.
 */
class FleetEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['maintenance.fleet.view', 'maintenance.fleet.manage']);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_vehicles_list_by_fleet_number_filtered_by_search_and_activity(): void
    {
        $second = $this->vehicle('FL-2', ['license_plate' => 'ABC-2']);
        $first = $this->vehicle('FL-1', ['license_plate' => 'ABC-1']);
        $this->vehicle('FL-3', ['license_plate' => 'ABC-3', 'is_active' => false]);
        $this->vehicle('FL-0', ['license_plate' => 'ABC-0', 'organization_id' => $this->other->id]);

        $response = $this->apiGet('/maintenance/fleet?active_only=1&search=ABC');

        $response->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_vehicle_is_not_found(): void
    {
        $theirs = $this->vehicle('FL-9', ['organization_id' => $this->other->id]);

        $this->apiGet("/maintenance/fleet/{$theirs->id}")
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Vehicle not found.');
        $this->apiGet("/maintenance/fleet/{$theirs->id}/mileage-logs")->assertNotFound();
        $this->apiPost("/maintenance/fleet/{$theirs->id}/assign", [
            'driver_id' => $this->employee($this->organization)->id,
        ])->assertNotFound();

        $this->assertSame(0, VehicleAssignment::withoutGlobalScopes()->count());
    }

    public function test_a_vehicle_for_another_organizations_department_is_refused(): void
    {
        $theirDepartment = Department::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/maintenance/fleet', [
            'fleet_number' => 'FL-1',
            'license_plate' => 'ABC-1',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'vehicle_type' => 'truck',
            'fuel_type' => 'diesel',
            'department_id' => $theirDepartment->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['department_id']);
    }

    public function test_drivers_logs_and_records_refuse_another_organizations_employee_or_order(): void
    {
        $vehicle = $this->vehicle('FL-1');
        $theirEmployee = $this->employee($this->other);
        $theirOrder = MaintenanceOrder::factory()->create([
            'organization_id' => $this->other->id,
            'equipment_id' => Equipment::factory()->create(['organization_id' => $this->other->id])->id,
        ]);

        $this->apiPost("/maintenance/fleet/{$vehicle->id}/assign", ['driver_id' => $theirEmployee->id])
            ->assertStatus(422)->assertJsonValidationErrors(['driver_id']);
        $this->apiPost("/maintenance/fleet/{$vehicle->id}/mileage-logs", [
            'odometer_start' => 100,
            'odometer_end' => 150,
            'driver_id' => $theirEmployee->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['driver_id']);
        $this->apiPost("/maintenance/fleet/{$vehicle->id}/fuel-logs", [
            'odometer_reading' => 150,
            'fuel_quantity_liters' => 40,
            'fuel_cost' => 90,
            'currency_code' => 'SAR',
            'fuel_type' => 'diesel',
            'filled_by' => $theirEmployee->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['filled_by']);
        $this->apiPost("/maintenance/fleet/{$vehicle->id}/maintenance", [
            'maintenance_type' => 'repair',
            'service_date' => now()->toDateString(),
            'description' => 'Brake pads',
            'maintenance_order_id' => $theirOrder->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['maintenance_order_id']);
    }

    public function test_drivers_are_shown_by_reference_and_a_new_assignment_ends_the_current_one(): void
    {
        $vehicle = $this->vehicle('FL-1');
        $firstDriver = $this->employee($this->organization);
        $secondDriver = $this->employee($this->organization);

        $this->apiPost("/maintenance/fleet/{$vehicle->id}/assign", ['driver_id' => $firstDriver->id])->assertCreated();
        $response = $this->apiPost("/maintenance/fleet/{$vehicle->id}/assign", ['driver_id' => $secondDriver->id]);

        $response->assertCreated()->assertJsonPath('data.driver.employee_number', $secondDriver->employee_number);
        $this->assertArrayNotHasKey('date_of_birth', $response->json('data.driver'));
        $this->assertSame(1, VehicleAssignment::where('vehicle_id', $vehicle->id)->where('is_current', true)->count());

        $shown = $this->apiGet("/maintenance/fleet/{$vehicle->id}")->assertOk();
        foreach ($shown->json('data.assignments') as $assignment) {
            $this->assertArrayNotHasKey('date_of_birth', $assignment['driver']);
        }
    }

    public function test_logs_list_newest_first_with_their_employees_by_reference(): void
    {
        $vehicle = $this->vehicle('FL-1');
        $driver = $this->employee($this->organization);
        MileageLog::create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'log_date' => now()->subDay()->toDateString(),
            'odometer_start' => 100,
            'odometer_end' => 150,
            'distance_km' => 50,
            'driver_id' => $driver->id,
        ]);
        FuelLog::create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'log_date' => now()->toDateString(),
            'odometer_reading' => 150,
            'fuel_quantity_liters' => 40,
            'fuel_cost' => 90,
            'currency_code' => 'SAR',
            'fuel_type' => 'diesel',
            'filled_by' => $driver->id,
        ]);

        $mileage = $this->apiGet("/maintenance/fleet/{$vehicle->id}/mileage-logs")->assertOk();
        $this->assertSame($driver->id, $mileage->json('data.0.driver.id'));
        $this->assertArrayNotHasKey('date_of_birth', $mileage->json('data.0.driver'));

        $fuel = $this->apiGet("/maintenance/fleet/{$vehicle->id}/fuel-logs")->assertOk();
        $this->assertSame($driver->id, $fuel->json('data.0.filled_by.id'));
        $this->assertArrayNotHasKey('date_of_birth', $fuel->json('data.0.filled_by'));
    }

    public function test_cost_summary_totals_fuel_and_maintenance_in_the_period(): void
    {
        $vehicle = $this->vehicle('FL-1');
        FuelLog::create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'log_date' => now()->subDay()->toDateString(),
            'odometer_reading' => 150,
            'fuel_quantity_liters' => 40,
            'fuel_cost' => 90,
            'currency_code' => 'SAR',
            'fuel_type' => 'diesel',
        ]);
        VehicleMaintenanceRecord::create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'repair',
            'service_date' => now()->subDay()->toDateString(),
            'description' => 'Brake pads',
            'cost' => 210,
        ]);

        $from = now()->subWeek()->toDateString();
        $to = now()->toDateString();

        $this->apiGet("/maintenance/fleet/cost-summary?date_from={$from}&date_to={$to}")
            ->assertOk()
            ->assertJsonPath('data.fuel_cost', 90)
            ->assertJsonPath('data.maintenance_cost', 210)
            ->assertJsonPath('data.total_cost', 300)
            ->assertJsonPath('data.vehicle_count', 1);
    }

    private function vehicle(string $fleetNumber, array $overrides = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'organization_id' => $this->organization->id,
            'fleet_number' => $fleetNumber,
            'license_plate' => 'PL-'.$fleetNumber,
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'vehicle_type' => 'truck',
            'fuel_type' => 'diesel',
            'is_active' => true,
        ], $overrides));
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'date_of_birth' => '1990-01-01',
        ]);
    }
}
