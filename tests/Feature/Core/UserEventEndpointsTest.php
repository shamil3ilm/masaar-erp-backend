<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\Core\UserEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the user event endpoints: the event list and the 30-day summary.
 *
 * Events carry the IP address and browser of whoever caused them. The routes
 * are open to every signed-in user as the caller's own record, so a caller
 * sees other users' events only when they hold core.users.view.
 */
class UserEventEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
    }

    public function test_a_caller_without_user_view_sees_only_their_own_events(): void
    {
        $this->setUpAuthenticatedUser();
        $this->colleague = User::factory()->create(['organization_id' => $this->organization->id]);

        $own = $this->event($this->user, UserEvent::USER_LOGIN);
        $this->event($this->colleague, UserEvent::USER_LOGIN);

        $this->assertSame([$own->id], array_column($this->apiGet('/events')->assertOk()->json('data'), 'id'));
        $this->assertSame([], $this->apiGet("/events?user_id={$this->colleague->id}")->assertOk()->json('data'));

        $this->apiGet('/events/summary')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.count', 1);
    }

    public function test_a_user_administrator_sees_the_organizations_events_latest_first_and_filtered(): void
    {
        $this->setUpAuthenticatedUser(['core.users.view']);
        $this->colleague = User::factory()->create(['organization_id' => $this->organization->id]);

        $old = $this->event($this->user, UserEvent::USER_LOGIN, now()->subDays(3));
        $mine = $this->event($this->user, UserEvent::USER_LOGOUT, now()->subDay());
        $theirs = $this->event($this->colleague, UserEvent::USER_LOGIN, now());
        $foreignUser = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->event($foreignUser, UserEvent::USER_LOGIN);

        $ids = fn (string $query) => array_column($this->apiGet('/events'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$theirs->id, $mine->id, $old->id], $ids(''));
        $this->assertSame([$theirs->id, $old->id], $ids('?event_type=user.login'));
        $this->assertSame([$theirs->id], $ids("?user_id={$this->colleague->id}"));
        $this->assertSame([$mine->id], $ids('?from='.now()->subDays(2)->toDateString().'&to='.now()->subDay()->toDateString()));

        $this->apiGet('/events')
            ->assertOk()
            ->assertJsonPath('message', 'Events retrieved successfully')
            ->assertJsonPath('data.0.ip_address', '10.0.0.1');
    }

    public function test_the_summary_counts_the_last_30_days_by_type_and_day(): void
    {
        $this->setUpAuthenticatedUser(['core.users.view']);
        $this->colleague = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->event($this->user, UserEvent::USER_LOGIN, now()->subDay());
        $this->event($this->colleague, UserEvent::USER_LOGIN, now()->subDay());
        $this->event($this->user, UserEvent::USER_LOGOUT, now()->subDays(2));
        $this->event($this->user, UserEvent::USER_LOGIN, now()->subDays(40));

        $rows = $this->apiGet('/events/summary')
            ->assertOk()
            ->assertJsonPath('message', 'Event summary retrieved successfully')
            ->json('data');

        $this->assertSame([
            ['event_type' => 'user.login', 'date' => now()->subDay()->toDateString(), 'count' => 2],
            ['event_type' => 'user.logout', 'date' => now()->subDays(2)->toDateString(), 'count' => 1],
        ], $rows);
    }

    private function event(User $user, string $type, ?\DateTimeInterface $at = null): UserEvent
    {
        return UserEvent::forceCreate([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'event_type' => $type,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => $at ?? now(),
        ]);
    }
}
