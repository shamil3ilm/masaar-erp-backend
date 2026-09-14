<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ProfitabilitySegment;
use App\Models\Accounting\ProfitabilitySegmentValue;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProfitabilitySegmentTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.profitability-segments.manage',
            'accounting.profitability-segments.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSegment(array $overrides = []): ProfitabilitySegment
    {
        return ProfitabilitySegment::create(array_merge([
            'organization_id' => $this->organization->id,
            'segment_name'    => 'Segment ' . fake()->unique()->numerify('###'),
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeSegment();
        $this->makeSegment();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_segment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/profitability-segments', [
                'segment_name' => 'Enterprise Segment',
                'region'       => 'GCC',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/profitability-segments', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_segment_details(): void
    {
        $segment = $this->makeSegment();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/' . $segment->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $segment->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_segment(): void
    {
        $segment = $this->makeSegment(['segment_name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/profitability-segments/' . $segment->id, [
                'segment_name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $segment->fresh()->segment_name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_segment(): void
    {
        $segment = $this->makeSegment();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/profitability-segments/' . $segment->id);

        $response->assertStatus(204);
        $this->assertNull(ProfitabilitySegment::find($segment->id));
    }

    // -------------------------------------------------------------------------
    // Post Values
    // -------------------------------------------------------------------------

    public function test_post_values_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/profitability-segments/post-values', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Drill Down
    // -------------------------------------------------------------------------

    public function test_drill_down_validates_period_and_fiscal_year(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/drill-down');

        $response->assertStatus(422);
    }

    public function test_drill_down_returns_result(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/drill-down?period=3&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Report
    // -------------------------------------------------------------------------

    public function test_report_validates_period_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/report');

        $response->assertStatus(422);
    }

    public function test_report_returns_segment_report(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/report?period=3&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/profitability-segments')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Tenant isolation and response shape
    // -------------------------------------------------------------------------

    private function makeValue(ProfitabilitySegment $segment, int $orgId, float $revenue): ProfitabilitySegmentValue
    {
        return ProfitabilitySegmentValue::create([
            'organization_id'          => $orgId,
            'profitability_segment_id' => $segment->id,
            'period'                   => 3,
            'fiscal_year'              => 2025,
            'revenue'                  => $revenue,
            'cost_of_sales'            => 0,
            'gross_margin'             => $revenue,
            'overhead_costs'           => 0,
            'net_margin'               => $revenue,
            'quantity_sold'            => 1,
        ]);
    }

    private function makeOtherOrganizationSegmentWithValue(): ProfitabilitySegment
    {
        $otherOrg = Organization::factory()->create();
        $segment  = $this->makeSegment([
            'organization_id' => $otherOrg->id,
            'segment_name'    => 'Other Org Segment',
            'region'          => 'North',
        ]);
        $this->makeValue($segment, $otherOrg->id, 9000);

        return $segment;
    }

    public function test_show_includes_relations(): void
    {
        $segment = $this->makeSegment();

        $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/' . $segment->id)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['values', 'customer_group', 'product']]);
    }

    public function test_show_update_and_destroy_return_404_for_other_organization_segment(): void
    {
        $other = $this->makeOtherOrganizationSegmentWithValue();

        $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/' . $other->id)
            ->assertStatus(404);
        $this->withToken($this->token)
            ->putJson('/api/v1/profitability-segments/' . $other->id, ['segment_name' => 'Hijack'])
            ->assertStatus(404);
        $this->withToken($this->token)
            ->deleteJson('/api/v1/profitability-segments/' . $other->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('profitability_segments', [
            'id'           => $other->id,
            'segment_name' => 'Other Org Segment',
            'deleted_at'   => null,
        ]);
    }

    public function test_index_excludes_other_organization_segments(): void
    {
        $this->makeSegment();
        $this->makeOtherOrganizationSegmentWithValue();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments');

        $response->assertStatus(200)->assertJsonPath('meta.per_page', 20);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_report_excludes_other_organization_values(): void
    {
        $own = $this->makeSegment(['segment_name' => 'Own Segment', 'region' => 'South']);
        $this->makeValue($own, $this->organization->id, 100);
        $this->makeOtherOrganizationSegmentWithValue();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/report?period=3&fiscal_year=2025');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.segment_name', 'Own Segment');
        $this->assertEquals(100, $response->json('data.0.revenue'));
    }

    public function test_drill_down_excludes_other_organization_values(): void
    {
        $own = $this->makeSegment(['segment_name' => 'Own Segment', 'region' => 'South']);
        $this->makeValue($own, $this->organization->id, 100);
        $this->makeOtherOrganizationSegmentWithValue();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/profitability-segments/drill-down?period=3&fiscal_year=2025&dimensions[]=region');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.region', 'South');
        $this->assertEquals(100, $response->json('data.0.revenue'));
    }
}
