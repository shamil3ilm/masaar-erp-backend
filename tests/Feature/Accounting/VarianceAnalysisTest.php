<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\VarianceAnalysisItem;
use App\Models\Accounting\VarianceAnalysisRun;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class VarianceAnalysisTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.variance.manage',
            'accounting.variance.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRun(array $overrides = []): VarianceAnalysisRun
    {
        return VarianceAnalysisRun::create(array_merge([
            'organization_id' => $this->organization->id,
            'period'          => 3,
            'fiscal_year'     => 2025,
            'run_type'        => VarianceAnalysisRun::RUN_TYPE_COST_CENTER,
            'run_by'          => $this->user->id,
            'status'          => 'completed',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/variance-analysis', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_run_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/variance-analysis', [
                'period'      => 3,
                'fiscal_year' => 2025,
                'run_type'    => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_run(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/variance-analysis', [
                'period'      => 3,
                'fiscal_year' => 2025,
                'run_type'    => VarianceAnalysisRun::RUN_TYPE_COST_CENTER,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_run_details(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $run->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Results
    // -------------------------------------------------------------------------

    public function test_results_returns_data(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $run->id . '/results');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    public function test_summary_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/summary');

        $response->assertStatus(422);
    }

    public function test_summary_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/summary?period=3&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/variance-analysis')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Filters, tenant isolation and response shape
    // -------------------------------------------------------------------------

    private function makeItem(VarianceAnalysisRun $run, float $variance): VarianceAnalysisItem
    {
        return VarianceAnalysisItem::create([
            'organization_id'          => $run->organization_id,
            'variance_analysis_run_id' => $run->id,
            'reference_type'           => 'work_order',
            'reference_id'             => 1,
            'variance_category'        => VarianceAnalysisItem::CATEGORY_PRICE_VARIANCE,
            'standard_cost'            => 100,
            'actual_cost'              => 100 + $variance,
            'variance_amount'          => $variance,
            'variance_percent'         => $variance,
        ]);
    }

    private function makeOtherOrganizationRun(): VarianceAnalysisRun
    {
        $otherOrg  = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);

        return $this->makeRun(['organization_id' => $otherOrg->id, 'run_by' => $otherUser->id]);
    }

    public function test_index_applies_filled_filters_only(): void
    {
        $this->makeRun(['period' => 3, 'status' => 'completed']);
        $this->makeRun(['period' => 4, 'status' => 'failed']);
        $this->makeOtherOrganizationRun();

        $all = $this->withToken($this->token)->getJson('/api/v1/variance-analysis?status=&period=');
        $all->assertStatus(200)->assertJsonPath('meta.per_page', 20);
        $this->assertCount(2, $all->json('data'));

        $failed = $this->withToken($this->token)->getJson('/api/v1/variance-analysis?status=failed');
        $this->assertCount(1, $failed->json('data'));
        $failed->assertJsonPath('data.0.period', 4);

        $period = $this->withToken($this->token)->getJson('/api/v1/variance-analysis?period=3&fiscal_year=2025&per_page=5');
        $period->assertJsonPath('meta.per_page', 5);
        $this->assertCount(1, $period->json('data'));
    }

    public function test_show_includes_the_runner(): void
    {
        $run = $this->makeRun();

        $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $run->id)
            ->assertStatus(200)
            ->assertJsonPath('data.run_by.id', $this->user->id)
            ->assertJsonPath('data.run_by.name', $this->user->name);
    }

    public function test_show_and_results_return_404_for_other_organization_run(): void
    {
        $other = $this->makeOtherOrganizationRun();
        $this->makeItem($other, 5);

        $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $other->id)
            ->assertStatus(404);
        $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $other->id . '/results')
            ->assertStatus(404);
    }

    public function test_results_lists_the_run_items(): void
    {
        $run = $this->makeRun();
        $this->makeItem($run, 7);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $run->id . '/results');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.variance_analysis_run_id', $run->id);
    }

    public function test_summary_excludes_other_organization_runs(): void
    {
        $this->makeItem($this->makeRun(), 10);
        $this->makeItem($this->makeOtherOrganizationRun(), 50);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/summary?period=3&fiscal_year=2025');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('data.price_variance.total_variance'));
    }
}
