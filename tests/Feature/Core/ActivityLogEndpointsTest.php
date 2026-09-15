<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the activity log list filter and a user's activity. The user filter
 * names a user of the caller's organization; a caller who is not a super
 * admin reads only their own activity.
 */
class ActivityLogEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.entity-views.view']);
    }

    public function test_the_user_filter_refuses_another_organizations_user(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiGet("/activity-logs?user_id={$member->id}")->assertOk();
        $this->apiGet("/activity-logs?user_id={$foreign->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_a_caller_reads_only_their_own_activity(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiGet("/activity-logs/user/{$this->user->id}")->assertOk();
        $this->apiGet("/activity-logs/user/{$member->id}")->assertForbidden();
        $this->apiGet('/activity-logs/user/999999')->assertNotFound();
    }
}
