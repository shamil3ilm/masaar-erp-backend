<?php

declare(strict_types=1);

namespace Tests\Feature\RealEstate;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\RealEstate\Building;
use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\Property;
use App\Models\RealEstate\RentalUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vacancy management: opening and closing a unit's vacancy, its history, and
 * a building's occupancy snapshot, within the organization only.
 */
class VacancyEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private RentalUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'real_estate',
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $this->setUpAuthenticatedUser([
            'real_estate.units.manage', 'real_estate.buildings.view', 'real_estate.buildings.manage',
        ]);

        $this->unit = $this->unitIn($this->organization->id, 'U-101');
    }

    public function test_a_vacancy_is_opened_closed_and_kept_in_the_units_history(): void
    {
        $this->apiPost("/real-estate/units/{$this->unit->id}/vacate", [
            'vacant_from' => '2026-03-01',
            'reason' => 'lease_expired',
            'market_rent' => 3000,
        ])->assertCreated()
            ->assertJsonPath('message', 'Vacancy period opened')
            ->assertJsonPath('data.rental_unit_id', $this->unit->id);

        $this->assertSame('vacant', $this->unit->fresh()->status);

        $closed = $this->apiPost("/real-estate/units/{$this->unit->id}/occupy", ['occupied_from' => '2026-04-01'])
            ->assertOk()
            ->assertJsonPath('message', 'Vacancy period closed');

        // 31 days at a market rent of 3000 a month (100 a day), counted to the day it closed.
        $this->assertEquals(3100, $closed->json('data.vacancy_loss'));
        $this->assertSame('occupied', $this->unit->fresh()->status);

        $this->apiGet("/real-estate/units/{$this->unit->id}/vacancy-history")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_another_organizations_unit_and_building_are_not_found(): void
    {
        $foreignUnit = $this->unitIn(Organization::factory()->create()->id, 'U-999');

        $this->apiPost("/real-estate/units/{$foreignUnit->id}/vacate", ['vacant_from' => '2026-03-01', 'reason' => 'renovation'])
            ->assertNotFound();
        $this->apiGet("/real-estate/units/{$foreignUnit->id}/vacancy-history")->assertNotFound();
        $this->apiPost("/real-estate/buildings/{$foreignUnit->building_id}/snapshot")->assertNotFound();

        $this->assertSame('occupied', $foreignUnit->fresh()->status);
    }

    public function test_a_buildings_occupancy_snapshot_counts_its_units(): void
    {
        $this->apiPost("/real-estate/buildings/{$this->unit->building_id}/snapshot", ['date' => '2026-03-15'])
            ->assertOk()
            ->assertJsonPath('message', 'Occupancy snapshot taken')
            ->assertJsonPath('data.total_units', 1)
            ->assertJsonPath('data.occupied_units', 1);
    }

    private function unitIn(int $organizationId, string $code): RentalUnit
    {
        $portfolio = Portfolio::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'code' => 'PF-'.$code,
            'name' => 'Portfolio '.$code,
        ]);
        $property = Property::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'portfolio_id' => $portfolio->id,
            'code' => 'PR-'.$code,
            'name' => 'Property '.$code,
        ]);
        $building = Building::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'code' => 'B-'.$code,
            'name' => 'Building '.$code,
        ]);

        return RentalUnit::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'building_id' => $building->id,
            'code' => $code,
            'status' => 'occupied',
            'is_active' => true,
            'area_sqm' => 120,
        ]);
    }
}
