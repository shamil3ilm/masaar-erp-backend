<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\Core\Webhook;
use App\Models\Core\WebhookDlqEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the webhook dead-letter queue endpoints: listing, summarizing,
 * replaying one or many entries and deleting one. Only the caller's
 * organization's entries are listed, replayed or deleted.
 */
class WebhookDlqEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.webhooks.view', 'core.webhooks.manage']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_entries_are_listed_latest_failure_first_filtered_and_summarized(): void
    {
        $older = $this->entry($this->organization->id, ['status' => 'pending', 'last_failed_at' => now()->subHour()]);
        $newer = $this->entry($this->organization->id, ['status' => 'dead', 'last_failed_at' => now()]);
        $this->entry($this->otherOrg->id, ['status' => 'pending']);

        $this->apiGet('/webhooks/dlq')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $this->apiGet('/webhooks/dlq?status=dead')->assertOk()->assertJsonPath('meta.total', 1);

        $this->apiGet('/webhooks/dlq/summary')
            ->assertOk()
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.dead', 1);
    }

    public function test_entries_are_replayed_one_or_many_and_deleted(): void
    {
        $one = $this->entry($this->organization->id);
        $two = $this->entry($this->organization->id);
        $foreign = $this->entry($this->otherOrg->id);

        $this->apiPost("/webhooks/dlq/{$one->id}/replay")
            ->assertOk()
            ->assertJsonPath('message', 'Webhook event replayed')
            ->assertJsonPath('data.status', 'replayed')
            ->assertJsonPath('data.replayed_by', $this->user->id);

        $this->apiPost('/webhooks/dlq/bulk-replay', ['ids' => [$two->id, $foreign->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Bulk replay initiated')
            ->assertJsonPath('data.replayed', 1);
        $this->assertSame('replayed', $two->fresh()->status);
        $this->assertSame('pending', WebhookDlqEntry::withoutGlobalScopes()->findOrFail($foreign->id)->status);

        $this->apiDelete("/webhooks/dlq/{$one->id}")->assertOk()->assertJsonPath('message', 'DLQ entry deleted');
        $this->assertNull(WebhookDlqEntry::find($one->id));
    }

    public function test_another_organizations_entry_is_not_found(): void
    {
        $foreign = $this->entry($this->otherOrg->id);

        $this->apiPost("/webhooks/dlq/{$foreign->id}/replay")->assertNotFound();
        $this->apiDelete("/webhooks/dlq/{$foreign->id}")->assertNotFound();
        $this->assertNotNull(WebhookDlqEntry::withoutGlobalScopes()->find($foreign->id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(int $organizationId, array $attributes = []): WebhookDlqEntry
    {
        $webhook = Webhook::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'created_by' => $this->user->id,
            'name' => 'Hook',
            'url' => 'https://hooks.example.test/hook',
            'events' => ['*'],
        ]);

        return WebhookDlqEntry::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'webhook_id' => $webhook->id,
            'event_type' => 'invoice.created',
            'payload' => ['id' => 1],
            'failure_count' => 1,
            'first_failed_at' => now(),
            'last_failed_at' => now(),
            'last_error' => 'timeout',
            'status' => 'pending',
        ], $attributes));
    }
}
