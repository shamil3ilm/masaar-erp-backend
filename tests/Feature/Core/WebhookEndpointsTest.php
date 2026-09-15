<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\Core\Webhook;
use App\Models\Core\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the webhook endpoints: creating, reading, changing, toggling and
 * deleting a webhook, rotating its signing secret, and its deliveries. The
 * secret is returned only when created or rotated. A webhook URL may not
 * target a private or local address, whether it is set on create or update.
 */
class WebhookEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view', 'core.settings.edit']);
        Queue::fake();
    }

    public function test_a_webhook_is_created_read_changed_toggled_rotated_and_deleted(): void
    {
        $created = $this->apiPost('/webhooks', [
            'name' => 'Orders',
            'url' => 'https://hooks.example.test/orders',
            'events' => ['*'],
        ]);

        $created->assertStatus(201)->assertJsonPath('message', 'Webhook created successfully. Please save the secret securely.');
        $id = $created->json('data.id');
        $secret = $created->json('data.secret');
        $this->assertIsString($secret);
        $this->assertNotSame('', $secret);

        $this->apiGet('/webhooks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonMissingPath('data.0.secret');

        $this->apiGet("/webhooks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Orders')
            ->assertJsonMissingPath('data.secret')
            ->assertJsonStructure(['data' => ['secret_masked']]);

        $this->apiPut("/webhooks/{$id}", ['name' => 'All orders', 'url' => 'https://hooks.example.test/all'])
            ->assertOk()
            ->assertJsonPath('message', 'Webhook updated successfully.')
            ->assertJsonPath('data.url', 'https://hooks.example.test/all')
            ->assertJsonMissingPath('data.secret');

        $this->apiPost("/webhooks/{$id}/toggle")
            ->assertOk()
            ->assertJsonPath('message', 'Webhook disabled.')
            ->assertJsonPath('data.is_active', false);

        $rotated = $this->apiPost("/webhooks/{$id}/regenerate-secret")
            ->assertOk()
            ->assertJsonPath('message', 'Webhook secret regenerated. Please update your integration.')
            ->json('data.secret');
        $this->assertNotSame($secret, $rotated);

        $this->apiDelete("/webhooks/{$id}")->assertOk()->assertJsonPath('message', 'Webhook deleted successfully.');
    }

    public function test_a_webhook_url_cannot_target_a_private_address_on_create_or_update(): void
    {
        $this->apiPost('/webhooks', ['name' => 'Local', 'url' => 'http://127.0.0.1/hook', 'events' => ['*']])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Webhook URL cannot target private/local addresses.');

        $id = $this->apiPost('/webhooks', ['name' => 'Public', 'url' => 'https://hooks.example.test/x', 'events' => ['*']])
            ->assertStatus(201)
            ->json('data.id');

        $this->apiPut("/webhooks/{$id}", ['url' => 'http://192.168.1.5/hook'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Webhook URL cannot target private/local addresses.');
        $this->apiPut("/webhooks/{$id}", ['url' => 'ftp://hooks.example.test/x'])
            ->assertStatus(422);

        $this->assertSame('https://hooks.example.test/x', Webhook::findOrFail($id)->url);
    }

    public function test_deliveries_are_listed_shown_and_a_failed_one_retried(): void
    {
        $webhook = $this->webhook($this->organization->id, ['retry_count' => 3]);
        $failed = $this->delivery($webhook, WebhookDelivery::STATUS_FAILED);
        $delivered = $this->delivery($webhook, WebhookDelivery::STATUS_SUCCESS);

        $this->apiGet("/webhooks/{$webhook->id}/deliveries")->assertOk()->assertJsonCount(2, 'data');
        $this->apiGet("/webhooks/{$webhook->id}/deliveries/{$failed->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $failed->id);

        $this->apiPost("/webhooks/{$webhook->id}/deliveries/{$failed->id}/retry")
            ->assertOk()
            ->assertJsonPath('message', 'Delivery queued for retry.');
        $this->assertSame(WebhookDelivery::STATUS_PENDING, $failed->fresh()->status);
        $this->assertSame(2, (int) $failed->fresh()->attempt);

        $this->apiPost("/webhooks/{$webhook->id}/deliveries/{$delivered->id}/retry")
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Cannot retry successful delivery');
    }

    public function test_another_organizations_webhook_and_deliveries_are_not_found(): void
    {
        $foreign = $this->webhook(Organization::factory()->create()->id);
        $foreignDelivery = $this->delivery($foreign, WebhookDelivery::STATUS_FAILED);
        $own = $this->webhook($this->organization->id);

        $this->apiGet("/webhooks/{$foreign->id}")->assertNotFound();
        $this->apiPut("/webhooks/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->apiPost("/webhooks/{$foreign->id}/toggle")->assertNotFound();
        $this->apiPost("/webhooks/{$foreign->id}/regenerate-secret")->assertNotFound();
        $this->apiDelete("/webhooks/{$foreign->id}")->assertNotFound();
        $this->apiGet("/webhooks/{$own->id}/deliveries/{$foreignDelivery->id}")->assertNotFound();
        $this->apiPost("/webhooks/{$own->id}/deliveries/{$foreignDelivery->id}/retry")->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function webhook(int $organizationId, array $attributes = []): Webhook
    {
        return Webhook::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'created_by' => $this->user->id,
            'name' => 'Hook',
            'url' => 'https://hooks.example.test/hook',
            'events' => ['*'],
            'is_active' => true,
            'retry_count' => 3,
            'timeout_seconds' => 30,
            'content_type' => 'application/json',
        ], $attributes));
    }

    private function delivery(Webhook $webhook, string $status): WebhookDelivery
    {
        return WebhookDelivery::create([
            'uuid' => (string) Str::uuid(),
            'webhook_id' => $webhook->id,
            'event_type' => 'invoice.created',
            'payload' => ['id' => 1],
            'status' => $status,
            'attempt' => 1,
        ]);
    }
}
