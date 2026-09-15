<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the CRM activity endpoints and keeps an activity assigned within the
 * caller's organization.
 */
class ActivityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'crm.activities.view',
            'crm.activities.create',
            'crm.activities.edit',
            'crm.activities.complete',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_filters_and_orders_by_start_newest_first(): void
    {
        $lead = Lead::factory()->create(['organization_id' => $this->organization->id]);
        $older = $this->activity(['subject' => 'Call Acme', 'start_datetime' => now()->subDays(2), 'related_type' => Lead::class, 'related_id' => $lead->id]);
        $newer = $this->activity(['subject' => 'Call Acme again', 'start_datetime' => now()->subDay(), 'related_type' => Lead::class, 'related_id' => $lead->id]);
        $this->activity(['subject' => 'Call Acme', 'status' => Activity::STATUS_COMPLETED, 'related_type' => Lead::class, 'related_id' => $lead->id]);
        $this->activity(['subject' => 'Email Acme', 'related_type' => Lead::class, 'related_id' => $lead->id + 1]);
        Activity::factory()->create(['organization_id' => $this->other->id, 'subject' => 'Call Acme']);

        $response = $this->apiGet('/crm/activities?status=planned&search=Acme&related_type='.urlencode(Lead::class)."&related_id={$lead->id}");

        $response->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_an_activity_cannot_be_assigned_to_another_organizations_user(): void
    {
        $outsider = User::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/crm/activities', [
            'activity_type' => 'call',
            'subject' => 'Intro call',
            'assigned_to' => $outsider->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $activity = $this->activity();
        $this->apiPut("/crm/activities/{$activity->id}", ['assigned_to' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);

        $this->assertSame(1, Activity::withoutGlobalScopes()->count());
    }

    public function test_store_creates_a_planned_activity_for_the_caller(): void
    {
        $this->apiPost('/crm/activities', [
            'activity_type' => 'meeting',
            'subject' => 'Kick-off',
            'assigned_to' => $this->user->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', Activity::STATUS_PLANNED)
            ->assertJsonPath('data.creator.id', $this->user->id)
            ->assertJsonPath('data.assignee.id', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_completed_and_cancelled_activities_are_final_and_another_organizations_is_not_found(): void
    {
        $completed = $this->activity(['status' => Activity::STATUS_COMPLETED]);
        $cancelled = $this->activity(['status' => Activity::STATUS_CANCELLED]);
        $theirs = Activity::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPut("/crm/activities/{$completed->id}", ['subject' => 'x'])->assertStatus(422)->assertJsonPath('error.code', 'ACTIVITY_COMPLETED');
        $this->apiPut("/crm/activities/{$cancelled->id}", ['subject' => 'x'])->assertStatus(422)->assertJsonPath('error.code', 'ACTIVITY_CANCELLED');
        $this->apiPost("/crm/activities/{$completed->id}/complete")->assertStatus(422)->assertJsonPath('error.code', 'ALREADY_COMPLETED');
        $this->apiPost("/crm/activities/{$cancelled->id}/complete")->assertStatus(422)->assertJsonPath('error.code', 'ACTIVITY_CANCELLED');

        $this->apiGet("/crm/activities/{$theirs->id}")->assertNotFound();
        $this->apiPut("/crm/activities/{$theirs->id}", ['subject' => 'x'])->assertNotFound();
        $this->apiPost("/crm/activities/{$theirs->id}/complete")->assertNotFound();
    }

    public function test_update_and_complete_an_open_activity(): void
    {
        $activity = $this->activity();

        $this->apiPut("/crm/activities/{$activity->id}", ['subject' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Renamed');

        $this->apiPost("/crm/activities/{$activity->id}/complete", ['outcome' => 'Signed'])
            ->assertOk()
            ->assertJsonPath('data.status', Activity::STATUS_COMPLETED)
            ->assertJsonPath('data.outcome', 'Signed');
        $this->assertNotNull($activity->fresh()->completed_at);
    }

    private function activity(array $attributes = []): Activity
    {
        return Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => Activity::STATUS_PLANNED,
            'created_by' => $this->user->id,
            ...$attributes,
        ]);
    }
}
