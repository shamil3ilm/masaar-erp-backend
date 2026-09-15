<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Core\Organization;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the conversation endpoints and keeps a conversation among the caller's
 * own organization's users.
 */
class ConversationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'messaging.conversations.view',
            'messaging.conversations.create',
            'messaging.messages.create',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_lists_only_conversations_the_caller_still_takes_part_in_newest_message_first(): void
    {
        $older = $this->conversation(['last_message_at' => now()->subDays(2)]);
        $newer = $this->conversation(['last_message_at' => now()->subDay()]);
        $left = $this->conversation();
        $left->participants()->updateExistingPivot($this->user->id, ['left_at' => now()]);
        $this->conversation(['organization_id' => $this->other->id]);
        Conversation::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        ConversationMessage::factory()->create(['conversation_id' => $newer->id, 'sender_id' => $this->user->id]);

        $response = $this->apiGet('/messaging/conversations');

        $response->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertSame([$this->user->id], array_column($response->json('data.0.participants'), 'id'));
        $this->assertCount(1, $response->json('data.0.latest_message'));
    }

    public function test_store_creates_a_group_with_the_creator_as_participant(): void
    {
        $colleagues = User::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $response = $this->apiPost('/messaging/conversations', [
            'participant_ids' => $colleagues->pluck('id')->all(),
            'subject' => 'Month end',
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'group');
        $conversation = Conversation::findOrFail($response->json('data.id'));
        $this->assertEqualsCanonicalizing(
            [$this->user->id, ...$colleagues->pluck('id')->all()],
            $conversation->participants()->pluck('users.id')->all()
        );
    }

    public function test_another_organizations_user_cannot_be_added_to_a_conversation(): void
    {
        $outsider = User::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/messaging/conversations', ['participant_ids' => [$outsider->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['participant_ids.0']);

        $this->assertSame(0, Conversation::withoutGlobalScopes()->count());
    }

    public function test_messages_and_sending_are_limited_to_participants(): void
    {
        $mine = $this->conversation(['last_message_at' => null]);
        $notMine = Conversation::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->apiGet("/messaging/conversations/{$notMine->id}/messages")->assertNotFound();
        $this->apiPost("/messaging/conversations/{$notMine->id}/messages", ['content' => 'Hi'])->assertNotFound();
        $this->apiGet("/messaging/conversations/{$notMine->id}")->assertNotFound();

        $this->apiPost("/messaging/conversations/{$mine->id}/messages", ['content' => 'Hi'])
            ->assertCreated()
            ->assertJsonPath('data.sender.id', $this->user->id)
            ->assertJsonPath('data.type', 'text');
        $this->assertNotNull($mine->fresh()->last_message_at);

        $this->apiGet("/messaging/conversations/{$mine->id}/messages")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('data.0.content', 'Hi');

        $this->apiPost("/messaging/conversations/{$mine->id}/read")->assertOk();
        $this->assertNotNull($mine->participants()->first()->pivot->last_read_at);
    }

    private function conversation(array $attributes = []): Conversation
    {
        $conversation = Conversation::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            ...$attributes,
        ]);
        $conversation->participants()->attach($this->user->id, ['joined_at' => now()]);

        return $conversation;
    }
}
