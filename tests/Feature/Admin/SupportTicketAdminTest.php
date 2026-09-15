<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin\PlatformAdmin;
use App\Models\Admin\SupportTicket;
use App\Models\Admin\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the platform support ticket endpoints, and records replies and
 * assignments in the columns the tickets actually have.
 */
class SupportTicketAdminTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->user = User::factory()->superAdmin()->create(['organization_id' => $this->organization->id]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_index_filters_by_status_newest_first_and_stats_count_each_status(): void
    {
        $older = $this->ticket(['status' => 'open', 'created_at' => now()->subDay()]);
        $newer = $this->ticket(['status' => 'open']);
        $this->ticket(['status' => 'in_progress']);
        $this->ticket(['status' => 'resolved']);

        $response = $this->admin('GET', '/support-tickets?status=open')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));

        $this->admin('GET', '/support-tickets/stats')
            ->assertOk()
            ->assertJsonPath('data.open', 2)
            ->assertJsonPath('data.in_progress', 1)
            ->assertJsonPath('data.resolved', 1);
    }

    public function test_a_reply_is_recorded_on_the_ticket_from_the_acting_admin(): void
    {
        $ticket = $this->ticket(['status' => 'open']);

        $this->admin('POST', "/support-tickets/{$ticket->getRouteKey()}/reply")->assertStatus(422);

        $this->admin('POST', "/support-tickets/{$ticket->getRouteKey()}/reply", ['message' => 'Looking into it'])
            ->assertCreated()
            ->assertJsonPath('data.ticket_id', $ticket->id)
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertSame('Looking into it', SupportTicketMessage::where('ticket_id', $ticket->id)->sole()->message);
        $this->assertSame(SupportTicket::STATUS_WAITING_RESPONSE, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->first_response_at);

        $this->admin('GET', "/support-tickets/{$ticket->getRouteKey()}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.message', 'Looking into it');
    }

    public function test_a_ticket_is_assigned_to_a_platform_admin(): void
    {
        $platformAdmin = PlatformAdmin::factory()->create();
        $ticket = $this->ticket(['status' => 'open']);

        $this->admin('POST', "/support-tickets/{$ticket->getRouteKey()}/assign", ['admin_id' => 999999])->assertStatus(422);

        $this->admin('POST', "/support-tickets/{$ticket->getRouteKey()}/assign", ['admin_id' => $platformAdmin->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_admin_id', $platformAdmin->id)
            ->assertJsonPath('data.status', SupportTicket::STATUS_IN_PROGRESS);
    }

    public function test_close_resolves_and_reopen_clears_the_resolution(): void
    {
        $ticket = $this->ticket(['status' => 'in_progress']);

        $this->admin('POST', "/support-tickets/{$ticket->getRouteKey()}/close")
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicket::STATUS_RESOLVED);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->admin('POST', "/support-tickets/{$ticket->getRouteKey()}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicket::STATUS_OPEN)
            ->assertJsonPath('data.resolved_at', null);
    }

    private function ticket(array $attributes = []): SupportTicket
    {
        return SupportTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'category' => 'general',
            ...$attributes,
        ]);
    }

    private function admin(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->json($method, "/api/v1/admin{$uri}", $data, $this->adminHeaders());
    }
}
