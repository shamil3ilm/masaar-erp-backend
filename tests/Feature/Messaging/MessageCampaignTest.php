<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Core\Organization;
use App\Models\Messaging\MessageCampaign;
use App\Models\Messaging\MessageTemplate;
use App\Models\Messaging\MessagingConfiguration;
use App\Models\Messaging\OutboundMessage;
use App\Models\Sales\Contact;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the message campaign endpoints, keeps a campaign on the caller's own
 * template, channel and contacts, and sends each queued message once.
 */
class MessageCampaignTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['messaging.campaigns.view', 'messaging.campaigns.manage']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
        $this->template = MessageTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'category' => 'promotional',
        ]);
    }

    public function test_index_filters_by_activity_channel_and_search(): void
    {
        $match = $this->campaign(['name' => 'Spring sale', 'channel_type' => 'sms']);
        $this->campaign(['name' => 'Spring sale email', 'channel_type' => 'email']);
        $this->campaign(['name' => 'Spring sale paused', 'channel_type' => 'sms', 'is_active' => false]);
        $this->campaign(['name' => 'Winter', 'channel_type' => 'sms']);

        $response = $this->apiGet('/messaging/campaigns?is_active=true&channel_type=sms&search=Spring');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->template->id, $response->json('data.0.template.id'));
    }

    public function test_a_campaign_on_another_organizations_template_or_channel_is_refused(): void
    {
        $theirTemplate = MessageTemplate::factory()->create(['organization_id' => $this->other->id]);
        $theirChannel = MessagingConfiguration::factory()->create(['organization_id' => $this->other->id, 'credentials' => ['key' => 'k']]);

        $payload = [
            'name' => 'Borrowed',
            'trigger_event' => 'invoice.created',
            'channel_type' => 'sms',
            'template_id' => $theirTemplate->id,
            'channel_id' => $theirChannel->id,
        ];

        $this->apiPost('/messaging/campaigns', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_id', 'channel_id']);

        $campaign = $this->campaign();
        $this->apiPut("/messaging/campaigns/{$campaign->id}", ['template_id' => $theirTemplate->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);

        $this->assertSame(1, MessageCampaign::withoutGlobalScopes()->count());
    }

    public function test_recipients_cannot_name_another_organizations_contact(): void
    {
        $campaign = $this->campaign();
        $theirs = Contact::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost("/messaging/campaigns/{$campaign->id}/recipients", [
            'recipients' => [['email' => 'a@example.com', 'contact_id' => $theirs->id]],
        ])->assertStatus(422)->assertJsonValidationErrors(['recipients.0.contact_id']);

        $this->assertSame(0, OutboundMessage::withoutGlobalScopes()->count());
    }

    public function test_recipients_show_the_contact_by_reference_only(): void
    {
        $campaign = $this->campaign();
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => '300000000000003',
        ]);
        $queued = $this->message($campaign, ['status' => OutboundMessage::STATUS_QUEUED, 'contact_id' => $contact->id]);
        $this->message($campaign, ['status' => OutboundMessage::STATUS_SENT]);

        $response = $this->apiGet("/messaging/campaigns/{$campaign->id}/recipients?status=queued");

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$queued->id], array_column($response->json('data'), 'id'));
        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($response->json('data.0.contact')));
    }

    public function test_destroy_refuses_an_active_campaign_and_removes_an_inactive_one_with_its_messages(): void
    {
        $active = $this->campaign();
        $this->apiDelete("/messaging/campaigns/{$active->id}")->assertStatus(422)->assertJsonPath('error.code', 'CAMPAIGN_ACTIVE');

        $inactive = $this->campaign(['is_active' => false]);
        $this->message($inactive, ['status' => OutboundMessage::STATUS_QUEUED]);

        $this->apiDelete("/messaging/campaigns/{$inactive->id}")->assertOk();

        $this->assertNull(MessageCampaign::find($inactive->id));
        $this->assertSame(0, OutboundMessage::where('automation_id', $inactive->id)->count());
    }

    public function test_launch_sends_queued_messages_and_pause_refuses_an_inactive_campaign(): void
    {
        $channel = MessagingConfiguration::factory()->create(['organization_id' => $this->organization->id, 'credentials' => ['key' => 'k']]);
        $campaign = $this->campaign(['is_active' => false, 'timing' => 'immediate', 'channel_id' => $channel->id]);
        $message = $this->message($campaign, ['status' => OutboundMessage::STATUS_QUEUED, 'channel_id' => $channel->id]);

        $this->apiPatch("/messaging/campaigns/{$campaign->id}/state", ['action' => 'pause'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CAMPAIGN_ERROR');

        $this->apiPost("/messaging/campaigns/{$campaign->id}/launch")
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.execution_count', 1);

        $this->assertSame(OutboundMessage::STATUS_SENT, $message->fresh()->status);
    }

    public function test_a_message_another_sender_has_already_claimed_is_not_sent_again(): void
    {
        $channel = MessagingConfiguration::factory()->create(['organization_id' => $this->organization->id, 'credentials' => ['key' => 'k']]);
        $campaign = $this->campaign(['channel_id' => $channel->id]);
        $stale = $this->message($campaign, ['status' => OutboundMessage::STATUS_QUEUED, 'channel_id' => $channel->id]);

        OutboundMessage::whereKey($stale->id)->update(['status' => OutboundMessage::STATUS_SENDING]);

        $this->assertFalse(app(MessageService::class)->sendMessage($stale));
        $this->assertSame(OutboundMessage::STATUS_SENDING, $stale->fresh()->status);
        $this->assertNull($stale->fresh()->sent_at);
    }

    private function campaign(array $attributes = []): MessageCampaign
    {
        return MessageCampaign::factory()->create([
            'organization_id' => $this->organization->id,
            'template_id' => $this->template->id,
            'created_by' => $this->user->id,
            'delay_minutes' => 0,
            ...$attributes,
        ]);
    }

    private function message(MessageCampaign $campaign, array $attributes = []): OutboundMessage
    {
        return OutboundMessage::factory()->create([
            'organization_id' => $this->organization->id,
            'automation_id' => $campaign->id,
            ...$attributes,
        ]);
    }
}
