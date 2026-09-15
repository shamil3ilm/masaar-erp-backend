<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\FunctionalLocation;
use App\Services\Maintenance\EquipmentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the functional location tree, where-used path and installation
 * endpoints, keeps them on the caller's own equipment and locations, and
 * builds the tree with the same number of queries however large it is.
 */
class EquipmentHierarchyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.equipment-hierarchy.view',
            'maintenance.equipment-hierarchy.manage',
        ]);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);
    }

    public function test_the_tree_nests_active_children_with_their_equipment(): void
    {
        $plant = $this->location('PLANT', null);
        $area = $this->location('AREA', $plant);
        $this->location('OLD-LINE', $area, ['is_active' => false]);
        $this->location('OLD-PLANT', null, ['is_active' => false]);
        $pump = $this->equipment($area);

        $response = $this->apiGet('/maintenance/equipment-hierarchy/tree');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.floc.code', 'PLANT')
            ->assertJsonPath('data.0.children.0.floc.code', 'AREA')
            ->assertJsonPath('data.0.children.0.equipment.0.id', $pump->id)
            ->assertJsonCount(0, 'data.0.children.0.children');

        $this->apiGet("/maintenance/equipment-hierarchy/tree?root_id={$area->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.floc.code', 'AREA');
    }

    public function test_the_tree_is_built_with_the_same_number_of_queries_however_large_it_is(): void
    {
        $plant = $this->location('PLANT', null);
        $this->equipment($this->location('AREA-1', $plant));
        $service = app(EquipmentHierarchyService::class);

        $small = $this->queriesFor(fn () => $service->buildTree($this->organization->id));

        foreach (range(2, 6) as $number) {
            $area = $this->location("AREA-{$number}", $plant);
            $this->equipment($this->location("LINE-{$number}", $area));
        }

        $large = $this->queriesFor(fn () => $service->buildTree($this->organization->id));

        $this->assertCount(6, $service->buildTree($this->organization->id)[0]['children']);
        $this->assertSame($small, $large);
    }

    public function test_installing_relocating_and_deinstalling_move_the_equipment(): void
    {
        $first = $this->location('LINE-1', null);
        $second = $this->location('LINE-2', null);
        $pump = $this->equipment(null);

        $this->apiPost('/maintenance/equipment-hierarchy/install', [
            'equipment_id' => $pump->id,
            'functional_location_id' => $first->id,
        ])->assertOk()->assertJsonPath('data.functional_location.id', $first->id);

        $this->apiPost('/maintenance/equipment-hierarchy/relocate', [
            'equipment_id' => $pump->id,
            'functional_location_id' => $second->id,
        ])->assertOk()->assertJsonPath('data.functional_location_id', $second->id);

        $this->apiPost('/maintenance/equipment-hierarchy/deinstall', ['equipment_id' => $pump->id])
            ->assertOk()
            ->assertJsonPath('data.functional_location_id', null);
    }

    public function test_another_organizations_equipment_or_location_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirLocation = FunctionalLocation::create([
            'organization_id' => $other->id,
            'code' => 'THEIRS',
            'name' => 'Their line',
            'location_type' => FunctionalLocation::TYPE_LINE,
        ]);
        $theirPump = Equipment::factory()->create(['organization_id' => $other->id]);
        $ourPump = $this->equipment(null);

        $this->apiPost('/maintenance/equipment-hierarchy/install', [
            'equipment_id' => $ourPump->id,
            'functional_location_id' => $theirLocation->id,
        ])->assertNotFound();
        $this->apiPost('/maintenance/equipment-hierarchy/deinstall', ['equipment_id' => $theirPump->id])
            ->assertNotFound();
        $this->apiGet("/maintenance/equipment-hierarchy/where-used/{$theirPump->id}")->assertNotFound();

        $this->assertNull($ourPump->fresh()->functional_location_id);
    }

    public function test_where_used_traces_the_path_from_the_plant_down(): void
    {
        $plant = $this->location('PLANT', null);
        $line = $this->location('LINE', $this->location('AREA', $plant));
        $pump = $this->equipment($line);

        $response = $this->apiGet("/maintenance/equipment-hierarchy/where-used/{$pump->id}");

        $response->assertOk()->assertJsonPath('data.equipment.id', $pump->id);
        $this->assertSame(['PLANT', 'AREA', 'LINE'], array_column($response->json('data.location_path'), 'code'));
    }

    public function test_equipment_under_a_location_includes_its_descendants_and_utilisation_counts_by_location(): void
    {
        $plant = $this->location('PLANT', null);
        $area = $this->location('AREA', $plant);
        $atPlant = $this->equipment($plant);
        $inArea = $this->equipment($area, ['status' => Equipment::STATUS_SCRAPPED]);
        $this->equipment(null);

        $response = $this->apiGet("/maintenance/equipment-hierarchy/floc/{$plant->id}/equipment");
        $response->assertOk();
        $this->assertEqualsCanonicalizing([$atPlant->id, $inArea->id], array_column($response->json('data'), 'id'));

        $summary = collect($this->apiGet('/maintenance/equipment-hierarchy/utilisation-summary')->assertOk()->json('data'))
            ->keyBy('floc_name');
        $this->assertSame(1, $summary['AREA name']['inactive']);
        $this->assertSame(1, $summary['Unassigned']['total']);
    }

    private function location(string $code, ?FunctionalLocation $parent, array $overrides = []): FunctionalLocation
    {
        return FunctionalLocation::create(array_merge([
            'organization_id' => $this->organization->id,
            'parent_id' => $parent?->id,
            'code' => $code,
            'name' => "{$code} name",
            'location_type' => $parent === null ? FunctionalLocation::TYPE_PLANT : FunctionalLocation::TYPE_AREA,
            'is_active' => true,
        ], $overrides));
    }

    private function equipment(?FunctionalLocation $location, array $overrides = []): Equipment
    {
        return Equipment::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'functional_location_id' => $location?->id,
            'equipment_category_id' => null,
            'status' => Equipment::STATUS_ACTIVE,
        ], $overrides));
    }

    private function queriesFor(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
