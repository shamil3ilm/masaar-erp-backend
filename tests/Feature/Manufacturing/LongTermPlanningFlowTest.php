<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\PlanningSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Long-term planning simulations are found only within the organization and
 * base themselves only on its own MRP runs.
 */
class LongTermPlanningFlowTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);
    }

    public function test_another_organizations_mrp_run_is_refused(): void
    {
        $theirRun = MrpRun::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'run_by' => $this->foreignUser()->id,
        ]);

        $this->apiPost('/manufacturing/long-term-planning', [
            'name' => 'Next year',
            'planning_horizon_from' => now()->toDateString(),
            'planning_horizon_to' => now()->addYear()->toDateString(),
            'mrp_run_id' => $theirRun->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['mrp_run_id']);
    }

    public function test_another_organizations_simulation_is_not_found(): void
    {
        $theirs = PlanningSimulation::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/long-term-planning/{$theirs->id}")->assertNotFound();
        $this->apiPut("/manufacturing/long-term-planning/{$theirs->id}", ['name' => 'x'])->assertNotFound();
        $this->apiPost("/manufacturing/long-term-planning/{$theirs->id}/run")->assertNotFound();
        $this->apiDelete("/manufacturing/long-term-planning/{$theirs->id}")->assertNotFound();

        $this->assertSame(PlanningSimulation::STATUS_DRAFT, $theirs->fresh()->status);
    }

    public function test_a_completed_simulation_is_not_updated(): void
    {
        $simulation = PlanningSimulation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => PlanningSimulation::STATUS_COMPLETED,
        ]);

        $this->apiPut("/manufacturing/long-term-planning/{$simulation->id}", ['name' => 'Renamed'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertNotSame('Renamed', $simulation->fresh()->name);
    }
}
