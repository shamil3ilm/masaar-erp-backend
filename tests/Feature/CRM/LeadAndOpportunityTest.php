<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the lead and opportunity lists, deletion together with their
 * activities, and moving an opportunity only to the caller's own stage.
 */
class LeadAndOpportunityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private PipelineStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'crm.leads.view',
            'crm.leads.delete',
            'crm.opportunities.view',
            'crm.opportunities.edit',
            'crm.opportunities.delete',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
        $this->stage = PipelineStage::factory()->create(['organization_id' => $this->organization->id, 'probability' => 20]);
    }

    public function test_lead_index_filters_by_status_and_search_newest_first(): void
    {
        $older = $this->lead(['company_name' => 'Acme One', 'created_at' => now()->subDays(2)]);
        $newer = $this->lead(['company_name' => 'Acme Two', 'created_at' => now()->subDay()]);
        $this->lead(['company_name' => 'Acme Lost', 'status' => Lead::STATUS_LOST]);
        $this->lead(['company_name' => 'Globex']);
        Lead::factory()->create(['organization_id' => $this->other->id, 'company_name' => 'Acme Other']);

        $response = $this->apiGet('/crm/leads?status=new&search=Acme');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_converted_lead_is_kept_and_another_is_deleted_with_its_activities(): void
    {
        $converted = $this->lead(['status' => Lead::STATUS_CONVERTED]);
        $this->apiDelete("/crm/leads/{$converted->id}")->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $lead = $this->lead();
        $this->activityFor(Lead::class, $lead->id);

        $this->apiDelete("/crm/leads/{$lead->id}")->assertOk();

        $this->assertSoftDeleted($lead);
        $this->assertSame(0, Activity::where('related_type', Lead::class)->where('related_id', $lead->id)->count());
    }

    public function test_opportunity_index_filters_by_stage_and_search_ordered_by_close_date(): void
    {
        $later = $this->opportunity(['name' => 'Acme renewal', 'expected_close_date' => now()->addMonths(2)]);
        $sooner = $this->opportunity(['name' => 'Acme expansion', 'expected_close_date' => now()->addMonth()]);
        $this->opportunity(['name' => 'Globex']);
        $this->opportunity([
            'name' => 'Acme other stage',
            'pipeline_stage_id' => PipelineStage::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);

        $response = $this->apiGet("/crm/opportunities?pipeline_stage_id={$this->stage->id}&search=Acme");

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$sooner->id, $later->id], array_column($response->json('data'), 'id'));
    }

    public function test_an_opportunity_moves_only_to_the_organizations_stage(): void
    {
        $opportunity = $this->opportunity();
        $theirStage = PipelineStage::factory()->create(['organization_id' => $this->other->id]);
        $next = PipelineStage::factory()->create(['organization_id' => $this->organization->id, 'probability' => 60]);

        $this->apiPost("/crm/opportunities/{$opportunity->id}/stage", ['pipeline_stage_id' => $theirStage->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline_stage_id']);

        $this->apiPost("/crm/opportunities/{$opportunity->id}/stage", ['pipeline_stage_id' => $next->id])
            ->assertOk()
            ->assertJsonPath('data.pipeline_stage_id', $next->id);
        $this->assertSame(60, $opportunity->fresh()->probability);
    }

    public function test_an_opportunity_is_deleted_with_its_activities(): void
    {
        $opportunity = $this->opportunity();
        $this->activityFor(Opportunity::class, $opportunity->id);

        $this->apiDelete("/crm/opportunities/{$opportunity->id}")->assertOk();

        $this->assertSoftDeleted($opportunity);
        $this->assertSame(0, Activity::where('related_type', Opportunity::class)->where('related_id', $opportunity->id)->count());
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => Lead::STATUS_NEW,
            ...$attributes,
        ]);
    }

    private function opportunity(array $attributes = []): Opportunity
    {
        return Opportunity::factory()->create([
            'organization_id' => $this->organization->id,
            'pipeline_stage_id' => $this->stage->id,
            ...$attributes,
        ]);
    }

    private function activityFor(string $type, int $id): Activity
    {
        return Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'related_type' => $type,
            'related_id' => $id,
        ]);
    }
}
