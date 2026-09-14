<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.cash-flow.view',
            'accounting.cash-flow.generate',
            'accounting.cash-flow.manage',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeScenario(array $overrides = []): CashFlowScenario
    {
        return CashFlowScenario::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Base Scenario',
            'is_base_case'    => false,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Scenarios — index
    // -------------------------------------------------------------------------

    public function test_index_scenarios_returns_list(): void
    {
        $this->makeScenario(['name' => 'Optimistic']);
        $this->makeScenario(['name' => 'Pessimistic']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/scenarios');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_scenarios_excludes_other_org_scenarios(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        CashFlowScenario::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Org Scenario',
            'is_base_case'    => false,
            'created_by'      => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/scenarios');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Scenarios — store
    // -------------------------------------------------------------------------

    public function test_store_scenario_creates_scenario(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/scenarios', [
                'name'         => 'Conservative Growth',
                'description'  => 'Low growth assumption',
                'is_base_case' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Conservative Growth');
    }

    public function test_store_scenario_validates_required_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/scenarios', []);

        $response->assertStatus(422);
    }

    public function test_store_scenario_demotes_previous_base_case(): void
    {
        $existing = $this->makeScenario(['is_base_case' => true]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/scenarios', [
                'name'         => 'New Base',
                'is_base_case' => true,
            ]);

        $this->assertFalse($existing->fresh()->is_base_case);
    }

    // -------------------------------------------------------------------------
    // Scenarios — update
    // -------------------------------------------------------------------------

    public function test_update_scenario_modifies_scenario(): void
    {
        $scenario = $this->makeScenario(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/cash-flow/scenarios/' . $scenario->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Scenarios — destroy
    // -------------------------------------------------------------------------

    public function test_destroy_scenario_soft_deletes(): void
    {
        $scenario = $this->makeScenario();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/cash-flow/scenarios/' . $scenario->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('cash_flow_scenarios', ['id' => $scenario->id]);
    }

    // -------------------------------------------------------------------------
    // Forecasts — index
    // -------------------------------------------------------------------------

    public function test_index_forecasts_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Forecasts — generate
    // -------------------------------------------------------------------------

    public function test_generate_forecast_creates_forecast(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days'  => 30,
                'currency_code' => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.forecast'));
        $this->assertNotNull($response->json('data.period_summary'));
    }

    public function test_generate_forecast_rejects_invalid_horizon(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days' => 45, // not in [30,60,90]
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Forecasts — show and lines
    // -------------------------------------------------------------------------

    public function test_show_forecast_returns_details(): void
    {
        // Generate then show
        $generateRes = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days' => 30,
            ]);
        $generateRes->assertStatus(201);

        $forecastUuid = $generateRes->json('data.forecast.uuid');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts/' . $forecastUuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.forecast.uuid', $forecastUuid);
    }

    public function test_forecast_lines_returns_paginated_lines(): void
    {
        $generateRes = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days' => 30,
            ]);
        $generateRes->assertStatus(201);

        $forecastUuid = $generateRes->json('data.forecast.uuid');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts/' . $forecastUuid . '/lines');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_update_scenario_to_base_case_demotes_the_other_base_case(): void
    {
        $existing = $this->makeScenario(['name' => 'Old Base', 'is_base_case' => true]);
        $scenario = $this->makeScenario(['name' => 'Candidate']);

        $this->withToken($this->token)
            ->putJson('/api/v1/cash-flow/scenarios/' . $scenario->uuid, ['is_base_case' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.is_base_case', true)
            ->assertJsonPath('data.creator.id', $this->user->id);

        $this->assertFalse($existing->fresh()->is_base_case);
    }

    public function test_index_scenarios_lists_base_case_first_then_by_name(): void
    {
        $this->makeScenario(['name' => 'Beta']);
        $this->makeScenario(['name' => 'Alpha']);
        $this->makeScenario(['name' => 'Zulu', 'is_base_case' => true]);

        $response = $this->withToken($this->token)->getJson('/api/v1/cash-flow/scenarios');

        $this->assertSame(['Zulu', 'Alpha', 'Beta'], array_column($response->json('data'), 'name'));
    }

    public function test_generate_forecast_links_the_requested_scenario_and_list_filters_by_it(): void
    {
        $scenario = $this->makeScenario();

        $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', ['horizon_days' => 30, 'scenario_id' => $scenario->id])
            ->assertStatus(201)
            ->assertJsonPath('data.forecast.scenario.id', $scenario->id);
        $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', ['horizon_days' => 30])
            ->assertStatus(201);

        $this->assertCount(2, $this->withToken($this->token)->getJson('/api/v1/cash-flow/forecasts')->json('data'));

        $filtered = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts?scenario_id=' . $scenario->id);
        $filtered->assertStatus(200)->assertJsonPath('data.0.scenario.id', $scenario->id);
        $this->assertCount(1, $filtered->json('data'));
    }

    public function test_generate_forecast_returns_404_for_another_organizations_scenario(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $scenario = $this->makeScenario(['organization_id' => $otherOrg->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', ['horizon_days' => 30, 'scenario_id' => $scenario->id])
            ->assertStatus(404);

        $this->assertSame(0, CashFlowForecast::withoutGlobalScopes()->count());
    }

    public function test_forecast_lines_filter_by_flow_type_confidence_and_source(): void
    {
        $forecast = CashFlowForecast::create([
            'organization_id'       => $this->organization->id,
            'forecast_date'         => now()->toDateString(),
            'horizon_days'          => 30,
            'currency_code'         => 'SAR',
            'total_opening_balance' => 0,
            'total_inflows'         => 0,
            'total_outflows'        => 0,
            'closing_balance'       => 0,
            'generated_at'          => now(),
        ]);
        $line = fn (string $description, string $flow, string $confidence, string $source) => \App\Models\Accounting\CashFlowLine::create([
            'forecast_id'   => $forecast->id,
            'expected_date' => now()->toDateString(),
            'flow_type'     => $flow,
            'source_type'   => $source,
            'description'   => $description,
            'amount'        => 10,
            'confidence'    => $confidence,
            'is_actual'     => false,
        ]);
        $line('match', 'inflow', 'certain', 'manual');
        $line('outflow', 'outflow', 'certain', 'manual');
        $line('possible', 'inflow', 'possible', 'manual');
        $line('invoice', 'inflow', 'certain', 'invoice');

        $response = $this->withToken($this->token)->getJson(
            '/api/v1/cash-flow/forecasts/' . $forecast->uuid . '/lines?flow_type=inflow&confidence=certain&source_type=manual'
        );

        $response->assertStatus(200);
        $this->assertSame(['match'], array_column($response->json('data'), 'description'));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/cash-flow/scenarios')->assertStatus(401);
        $this->getJson('/api/v1/cash-flow/forecasts')->assertStatus(401);
    }
}
