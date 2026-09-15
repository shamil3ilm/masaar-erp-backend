<?php

declare(strict_types=1);

namespace Tests\Feature\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\UserSegment;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the campaign endpoints and keeps a campaign on the caller's own
 * segment, so it never targets another organization's audience definition.
 */
class CampaignTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'crm.campaigns.view',
            'crm.campaigns.create',
            'crm.campaigns.edit',
            'crm.campaigns.delete',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_lists_the_organizations_live_campaigns_newest_first_with_send_counts(): void
    {
        $older = $this->campaign(['name' => 'Older', 'created_at' => now()->subDay()]);
        $newer = $this->campaign(['name' => 'Newer']);
        $this->campaign(['name' => 'Deleted'])->delete();
        $this->campaign(['name' => 'Theirs', 'organization_id' => $this->other->id, 'created_by' => User::factory()->create(['organization_id' => $this->other->id])->id]);

        $response = $this->apiGet('/crm/campaigns');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertSame(0, $response->json('data.0.sends_count'));
    }

    public function test_store_creates_a_draft_with_defaults(): void
    {
        $segment = $this->segment($this->organization);

        $this->apiPost('/crm/campaigns', [
            'name' => 'Welcome',
            'target_segment_id' => $segment->id,
            'actions' => [['type' => 'database']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', Campaign::STATUS_DRAFT)
            ->assertJsonPath('data.schedule_type', 'immediate')
            ->assertJsonPath('data.max_sends_per_user', 1)
            ->assertJsonPath('data.created_by', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_a_campaign_cannot_target_another_organizations_segment(): void
    {
        $theirs = $this->segment($this->other);

        $this->apiPost('/crm/campaigns', [
            'name' => 'Borrowed audience',
            'target_segment_id' => $theirs->id,
            'actions' => [['type' => 'database']],
        ])->assertStatus(422)->assertJsonValidationErrors(['target_segment_id']);

        $campaign = $this->campaign();
        $this->apiPut("/crm/campaigns/{$campaign->id}", ['target_segment_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_segment_id']);

        $this->assertNull($campaign->fresh()->target_segment_id);
        $this->assertSame(1, Campaign::withoutGlobalScopes()->count());
    }

    public function test_update_activate_pause_and_delete_and_another_organizations_campaign_is_not_found(): void
    {
        $campaign = $this->campaign();
        $theirs = $this->campaign([
            'organization_id' => $this->other->id,
            'created_by' => User::factory()->create(['organization_id' => $this->other->id])->id,
        ]);

        $this->apiPut("/crm/campaigns/{$campaign->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->apiPost("/crm/campaigns/{$campaign->id}/activate")->assertOk()->assertJsonPath('data.status', Campaign::STATUS_ACTIVE);
        $this->apiPost("/crm/campaigns/{$campaign->id}/pause")->assertOk()->assertJsonPath('data.status', Campaign::STATUS_PAUSED);
        $this->apiGet("/crm/campaigns/{$campaign->id}")->assertOk()->assertJsonPath('data.sends_count', 0);

        foreach (['GET' => '', 'PUT' => '', 'DELETE' => '', 'POST' => '/activate'] as $method => $suffix) {
            $this->json($method, "/api/v1/crm/campaigns/{$theirs->id}{$suffix}", [], $this->authHeaders())->assertNotFound();
        }
        $this->assertSame(Campaign::STATUS_DRAFT, Campaign::withoutGlobalScopes()->findOrFail($theirs->id)->status);

        $this->apiDelete("/crm/campaigns/{$campaign->id}")->assertOk();
        $this->assertSoftDeleted($campaign);
        $this->apiGet("/crm/campaigns/{$campaign->id}")->assertNotFound();
    }

    private function campaign(array $attributes = []): Campaign
    {
        return Campaign::unguarded(fn () => Campaign::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Campaign',
            'actions' => [['type' => 'database']],
            'status' => Campaign::STATUS_DRAFT,
            'created_by' => $this->user->id,
            ...$attributes,
        ]));
    }

    private function segment(Organization $organization): UserSegment
    {
        return UserSegment::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => 'Segment',
            'conditions' => [],
            'created_by' => User::factory()->create(['organization_id' => $organization->id])->id,
        ]);
    }
}
