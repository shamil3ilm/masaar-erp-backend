<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\EquipmentCategory;
use App\Models\Maintenance\FunctionalLocation;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenancePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the functional location, equipment category, equipment and plan lists
 * and their delete guards, and keeps their references inside the caller's
 * organization.
 */
class MaintenanceMasterDataTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $permissions = [];
        foreach (['functional-locations', 'equipment-categories', 'equipment', 'plans'] as $area) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = "maintenance.{$area}.{$action}";
            }
        }
        $permissions[] = 'maintenance.orders.create';
        $this->setUpAuthenticatedUser($permissions);
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_functional_locations_search_name_or_code_within_the_type_filter(): void
    {
        $area = $this->location('PA', 'Pump area', FunctionalLocation::TYPE_AREA);
        $coded = $this->location('PUMP-2', 'Second area', FunctionalLocation::TYPE_AREA);
        $this->location('PL', 'Pump plant', FunctionalLocation::TYPE_PLANT);
        $this->location('LA', 'Loading area', FunctionalLocation::TYPE_AREA);

        $response = $this->apiGet('/maintenance/functional-locations?search=Pump&location_type=area');

        $response->assertOk();
        $this->assertSame([$area->id, $coded->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_location_under_another_organizations_parent_or_branch_is_refused(): void
    {
        $theirParent = FunctionalLocation::create([
            'organization_id' => $this->other->id,
            'code' => 'THEIRS',
            'name' => 'Their plant',
            'location_type' => FunctionalLocation::TYPE_PLANT,
        ]);
        $theirBranch = Branch::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/maintenance/functional-locations', [
            'parent_id' => $theirParent->id,
            'branch_id' => $theirBranch->id,
            'code' => 'A1',
            'name' => 'Area one',
            'location_type' => FunctionalLocation::TYPE_AREA,
        ])->assertStatus(422)->assertJsonValidationErrors(['parent_id', 'branch_id']);
    }

    public function test_a_location_with_children_or_equipment_cannot_be_deleted(): void
    {
        $plant = $this->location('P1', 'Plant', FunctionalLocation::TYPE_PLANT);
        $this->location('A1', 'Area', FunctionalLocation::TYPE_AREA, ['parent_id' => $plant->id]);
        $line = $this->location('L1', 'Line', FunctionalLocation::TYPE_LINE);
        $this->equipment(['functional_location_id' => $line->id]);

        $this->apiDelete("/maintenance/functional-locations/{$plant->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_CHILDREN');
        $this->apiDelete("/maintenance/functional-locations/{$line->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_EQUIPMENT');
    }

    public function test_categories_list_by_name_with_their_equipment_count(): void
    {
        $pumps = EquipmentCategory::create(['organization_id' => $this->organization->id, 'name' => 'Pumps']);
        $fans = EquipmentCategory::create(['organization_id' => $this->organization->id, 'name' => 'Fans']);
        $this->equipment(['equipment_category_id' => $pumps->id]);
        $this->equipment(['equipment_category_id' => $pumps->id]);

        $response = $this->apiGet('/maintenance/equipment-categories');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $fans->id)
            ->assertJsonPath('data.1.equipment_count', 2);
        $this->apiDelete("/maintenance/equipment-categories/{$pumps->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_EQUIPMENT');
    }

    public function test_equipment_lists_by_name_filtered_by_status_and_search(): void
    {
        $second = $this->equipment(['name' => 'Pump B', 'serial_number' => 'SN-2']);
        $first = $this->equipment(['name' => 'Pump A']);
        $this->equipment(['name' => 'Pump C', 'status' => Equipment::STATUS_SCRAPPED]);
        $this->equipment(['name' => 'Fan']);

        $response = $this->apiGet('/maintenance/equipment?search=Pump&status=active');

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    public function test_equipment_at_another_organizations_location_or_category_is_refused(): void
    {
        $theirLocation = FunctionalLocation::create([
            'organization_id' => $this->other->id,
            'code' => 'THEIRS',
            'name' => 'Their line',
            'location_type' => FunctionalLocation::TYPE_LINE,
        ]);
        $theirCategory = EquipmentCategory::create(['organization_id' => $this->other->id, 'name' => 'Theirs']);

        $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-1',
            'name' => 'Pump',
            'functional_location_id' => $theirLocation->id,
            'equipment_category_id' => $theirCategory->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['functional_location_id', 'equipment_category_id']);
    }

    public function test_equipment_with_an_open_order_cannot_be_deleted(): void
    {
        $equipment = $this->equipment();
        MaintenanceOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'equipment_id' => $equipment->id,
            'status' => MaintenanceOrder::STATUS_OPEN,
        ]);

        $this->apiDelete("/maintenance/equipment/{$equipment->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_OPEN_ORDERS');
    }

    public function test_due_soon_lists_equipment_due_within_the_days_asked(): void
    {
        $later = $this->equipment(['next_maintenance_date' => now()->addDays(5)->toDateString()]);
        $sooner = $this->equipment(['next_maintenance_date' => now()->addDay()->toDateString()]);
        $this->equipment(['next_maintenance_date' => now()->addDays(30)->toDateString()]);

        $response = $this->apiGet('/maintenance/equipment/due-soon?days=7');

        $response->assertOk();
        $this->assertSame([$sooner->id, $later->id], array_column($response->json('data'), 'id'));
    }

    public function test_plans_filter_by_activity_and_refuse_another_organizations_equipment(): void
    {
        $equipment = $this->equipment();
        $active = $this->plan($equipment, 'Monthly check', true);
        $this->plan($equipment, 'Old check', false);
        $theirEquipment = Equipment::factory()->create(['organization_id' => $this->other->id]);

        $response = $this->apiGet('/maintenance/plans?is_active=1');
        $response->assertOk();
        $this->assertSame([$active->id], array_column($response->json('data'), 'id'));

        $this->apiPost('/maintenance/plans', [
            'equipment_id' => $theirEquipment->id,
            'name' => 'Their plan',
            'maintenance_type' => MaintenancePlan::TYPE_PREVENTIVE,
            'frequency_type' => MaintenancePlan::FREQ_MONTHLY,
            'frequency_value' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['equipment_id']);
    }

    public function test_toggling_a_plan_reports_its_new_state_and_an_inactive_plan_generates_no_order(): void
    {
        $plan = $this->plan($this->equipment(), 'Monthly check', true);

        $this->apiPost("/maintenance/plans/{$plan->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance plan deactivated successfully.')
            ->assertJsonPath('data.is_active', false);

        $this->apiPost("/maintenance/plans/{$plan->id}/generate-order")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertSame(0, MaintenanceOrder::count());
    }

    private function location(string $code, string $name, string $type, array $overrides = []): FunctionalLocation
    {
        return FunctionalLocation::create(array_merge([
            'organization_id' => $this->organization->id,
            'code' => $code,
            'name' => $name,
            'location_type' => $type,
        ], $overrides));
    }

    private function equipment(array $overrides = []): Equipment
    {
        return Equipment::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'status' => Equipment::STATUS_ACTIVE,
            'functional_location_id' => null,
            'equipment_category_id' => null,
        ], $overrides));
    }

    private function plan(Equipment $equipment, string $name, bool $active): MaintenancePlan
    {
        return MaintenancePlan::create([
            'organization_id' => $this->organization->id,
            'equipment_id' => $equipment->id,
            'name' => $name,
            'maintenance_type' => MaintenancePlan::TYPE_PREVENTIVE,
            'frequency_type' => MaintenancePlan::FREQ_MONTHLY,
            'frequency_value' => 1,
            'is_active' => $active,
        ]);
    }
}
