<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Webhook;
use App\Models\Core\WebhookDelivery;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers the DispatchesWebhooks trait on business models: a subscribed
 * organization receives deliveries, and everyone else pays no cost.
 */
class ModelWebhookDispatchTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        config(['webhooks.dispatch_in_tests' => true]);
        Queue::fake();
    }

    private function subscribe(array $events): Webhook
    {
        return Webhook::create([
            'organization_id' => $this->organization->id,
            'created_by'      => $this->user->id,
            'name'            => 'Test endpoint',
            'url'             => 'https://example.test/hook',
            'secret'          => 'shhh',
            'events'          => $events,
            'is_active'       => true,
            'retry_count'     => 3,
            'timeout_seconds' => 30,
            'content_type'    => 'application/json',
        ]);
    }

    public function test_creating_a_model_delivers_to_a_subscribed_webhook(): void
    {
        $this->subscribe(['contact.created']);

        Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseHas('webhook_deliveries', ['event_type' => 'contact.created']);
    }

    public function test_updating_a_model_delivers_an_updated_event(): void
    {
        $this->subscribe(['contact.updated']);

        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $contact->update(['contact_name' => 'Renamed']);

        $this->assertDatabaseHas('webhook_deliveries', ['event_type' => 'contact.updated']);
    }

    public function test_wildcard_subscription_receives_every_event(): void
    {
        $this->subscribe(['*']);

        Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseHas('webhook_deliveries', ['event_type' => 'contact.created']);
    }

    public function test_unsubscribed_event_creates_no_delivery(): void
    {
        $this->subscribe(['invoice.created']);

        Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    /**
     * With no subscriptions, model writes must not record webhook events —
     * otherwise every write on every tenant would add a row.
     */
    public function test_organization_without_subscriptions_records_nothing(): void
    {
        Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_inactive_webhook_receives_nothing(): void
    {
        $webhook = $this->subscribe(['contact.created']);
        $webhook->update(['is_active' => false]);

        Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    /**
     * WEBHOOKS_ENABLED is the kill switch: it must stop emission even for an
     * organization with a matching active subscription.
     */
    public function test_kill_switch_stops_emission(): void
    {
        $this->subscribe(['contact.created']);
        config(['webhooks.enabled' => false]);

        Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_emission_resumes_when_the_kill_switch_is_released(): void
    {
        $this->subscribe(['contact.created']);

        config(['webhooks.enabled' => false]);
        Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertDatabaseCount('webhook_deliveries', 0);

        config(['webhooks.enabled' => true]);
        Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertDatabaseCount('webhook_deliveries', 1);
    }
}
