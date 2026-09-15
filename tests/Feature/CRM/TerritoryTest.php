<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\PipelineStage;
use App\Models\CRM\Territory;
use App\Models\CRM\TerritoryAssignment;
use App\Models\CRM\TerritoryRoutingRule;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the territory endpoints, keeps territories, assignments and routing
 * rules on the caller's own rows, and shows employees by reference only.
 */
class TerritoryTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['crm.territories.view', 'crm.territories.manage']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_filters_by_status_newest_first_within_the_organization(): void
    {
        $older = $this->territory(['code' => 'T1', 'created_at' => now()->subDays(2)]);
        $newer = $this->territory(['code' => 'T2', 'created_at' => now()->subDay()]);
        $this->territory(['code' => 'T3', 'status' => 'inactive']);
        $this->territory(['code' => 'T4', 'organization_id' => $this->other->id]);

        $response = $this->apiGet('/crm/territories?status=active');

        $response->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_references_to_another_organizations_territory_or_employee_are_refused(): void
    {
        $theirs = $this->territory(['code' => 'X1', 'organization_id' => $this->other->id]);
        $mine = $this->territory(['code' => 'M1']);
        $theirEmployee = Employee::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/crm/territories', ['name' => 'North', 'code' => 'N1', 'parent_id' => $theirs->id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
        $this->apiPut("/crm/territories/{$mine->id}", ['parent_id' => $theirs->id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
        $this->apiPost('/crm/territories/routing-rules', ['territory_id' => $theirs->id, 'match_field' => 'country', 'match_value' => 'SA'])
            ->assertStatus(422)->assertJsonValidationErrors(['territory_id']);
        $this->apiPost("/crm/territories/{$mine->id}/assignments", ['employee_id' => $theirEmployee->id, 'effective_from' => now()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['employee_id']);

        $this->assertSame(0, TerritoryRoutingRule::withoutGlobalScopes()->count());
        $this->assertSame(0, TerritoryAssignment::withoutGlobalScopes()->count());
        $this->assertNull($mine->fresh()->parent_id);
    }

    public function test_assignments_show_the_employee_by_reference_and_keep_the_end_date(): void
    {
        $territory = $this->territory(['code' => 'T1']);
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $created = $this->apiPost("/crm/territories/{$territory->id}/assignments", [
            'employee_id' => $employee->id,
            'role' => 'backup',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.role', 'backup');

        $this->assertEqualsCanonicalizing(Employee::REFERENCE_COLUMNS, array_keys($created->json('data.employee')));
        $this->assertSame(now()->addMonth()->toDateString(), TerritoryAssignment::sole()->effective_to->toDateString());

        $shown = $this->apiGet("/crm/territories/{$territory->id}")->assertOk();
        $this->assertEqualsCanonicalizing(Employee::REFERENCE_COLUMNS, array_keys($shown->json('data.assignments.0.employee')));

        $listed = $this->apiGet("/crm/territories/{$territory->id}/assignments")->assertOk();
        $this->assertEqualsCanonicalizing(Employee::REFERENCE_COLUMNS, array_keys($listed->json('data.0.employee')));

        $assignment = TerritoryAssignment::sole();
        $this->apiDelete("/crm/territories/assignments/{$assignment->id}")->assertOk();
        $this->assertSame(now()->subDay()->toDateString(), $assignment->fresh()->effective_to->toDateString());
    }

    public function test_routing_rules_list_by_priority_and_a_lead_is_auto_assigned_to_the_owner(): void
    {
        $territory = $this->territory(['code' => 'SA']);
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id]);
        TerritoryAssignment::create([
            'organization_id' => $this->organization->id,
            'territory_id' => $territory->id,
            'employee_id' => $employee->id,
            'role' => 'owner',
            'effective_from' => now()->subMonth()->toDateString(),
        ]);
        $second = $this->rule($territory, ['priority' => 20, 'match_value' => 'AE']);
        $first = $this->rule($territory, ['priority' => 5, 'match_value' => 'SA']);
        TerritoryRoutingRule::withoutGlobalScopes()->create([
            'organization_id' => $this->other->id,
            'territory_id' => $this->territory(['code' => 'X', 'organization_id' => $this->other->id])->id,
            'match_field' => 'country',
            'match_value' => 'SA',
        ]);

        $response = $this->apiGet('/crm/territories/routing-rules');
        $response->assertOk()->assertJsonPath('meta.per_page', 25);
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));

        $lead = Lead::factory()->create(['organization_id' => $this->organization->id, 'country_code' => 'SA']);
        $assigned = $this->apiPost("/crm/territories/leads/{$lead->id}/auto-assign")->assertOk();

        $this->assertSame($this->user->id, $lead->fresh()->assigned_to);
        $this->assertEqualsCanonicalizing(Employee::REFERENCE_COLUMNS, array_keys($assigned->json('data.employee')));
    }

    public function test_team_workload_sums_each_salespersons_pipeline_in_a_fixed_number_of_queries(): void
    {
        $territory = $this->territory(['code' => 'W1']);
        $stage = PipelineStage::factory()->create(['organization_id' => $this->organization->id]);
        $first = $this->salesperson($territory);
        Lead::factory()->count(2)->create(['organization_id' => $this->organization->id, 'assigned_to' => $first->user_id, 'status' => Lead::STATUS_NEW]);
        $this->opportunityFor($first, $stage, Opportunity::STATUS_OPEN, '100');
        $this->opportunityFor($first, $stage, Opportunity::STATUS_WON, '300');

        // The first request also warms the module-access cache; count from the second.
        $this->apiGet('/crm/territories/team-workload')->assertOk();
        [$single, $singleQueries] = $this->countingQueries(fn () => $this->apiGet('/crm/territories/team-workload'));

        $single->assertOk()
            ->assertJsonPath('data.0.employee_id', $first->id)
            ->assertJsonPath('data.0.open_leads', 2)
            ->assertJsonPath('data.0.open_opportunities', 1)
            ->assertJsonPath('data.0.quota_attainment', '75.0000')
            ->assertJsonPath('data.0.territories.0.code', 'W1');
        $this->assertEquals(100, $single->json('data.0.pipeline_value'));

        $busier = $this->salesperson($territory);
        Lead::factory()->count(3)->create(['organization_id' => $this->organization->id, 'assigned_to' => $busier->user_id, 'status' => Lead::STATUS_NEW]);
        $this->salesperson($territory);

        [$many, $manyQueries] = $this->countingQueries(fn () => $this->apiGet('/crm/territories/team-workload'));

        $many->assertOk();
        $this->assertSame([$busier->id, $first->id], array_slice(array_column($many->json('data'), 'employee_id'), 0, 2));
        $this->assertSame(0, $many->json('data.2.open_leads'));
        $this->assertSame('0.0000', $many->json('data.2.quota_attainment'));
        $this->assertSame($singleQueries, $manyQueries);
    }

    public function test_another_organizations_territory_is_not_found(): void
    {
        $theirs = $this->territory(['code' => 'X1', 'organization_id' => $this->other->id]);

        $this->apiGet("/crm/territories/{$theirs->id}")->assertNotFound();
        $this->apiPut("/crm/territories/{$theirs->id}", ['name' => 'Mine now'])->assertNotFound();
        $this->apiDelete("/crm/territories/{$theirs->id}")->assertNotFound();

        $mine = $this->territory(['code' => 'M1']);
        $this->apiDelete("/crm/territories/{$mine->id}")->assertOk();
        $this->assertSoftDeleted($mine);
    }

    private function territory(array $attributes = []): Territory
    {
        return Territory::unguarded(fn () => Territory::create([
            'organization_id' => $this->organization->id,
            'name' => 'Territory',
            'code' => 'T',
            'status' => 'active',
            ...$attributes,
        ]));
    }

    private function salesperson(Territory $territory): Employee
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        TerritoryAssignment::create([
            'organization_id' => $this->organization->id,
            'territory_id' => $territory->id,
            'employee_id' => $employee->id,
            'role' => 'owner',
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        return $employee;
    }

    private function opportunityFor(Employee $employee, PipelineStage $stage, string $status, string $amount): Opportunity
    {
        return Opportunity::factory()->create([
            'organization_id' => $this->organization->id,
            'pipeline_stage_id' => $stage->id,
            'assigned_to' => $employee->user_id,
            'status' => $status,
            'amount' => $amount,
        ]);
    }

    /**
     * @return array{0: \Illuminate\Testing\TestResponse, 1: int}
     */
    private function countingQueries(callable $request): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$response, $count];
    }

    private function rule(Territory $territory, array $attributes = []): TerritoryRoutingRule
    {
        return TerritoryRoutingRule::create([
            'organization_id' => $this->organization->id,
            'territory_id' => $territory->id,
            'entity_type' => 'lead',
            'match_field' => 'country',
            'match_value' => 'SA',
            ...$attributes,
        ]);
    }
}
