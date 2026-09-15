<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Core\Organization;
use App\Models\Messaging\ChannelTemplateApproval;
use App\Models\Messaging\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the message template endpoints and keeps a translation under the
 * caller's own parent template.
 */
class MessageTemplateTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['messaging.templates.view', 'messaging.templates.manage']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_filters_by_channel_and_activity_sorted_by_name(): void
    {
        $b = $this->template(['name' => 'B reminder', 'channel_type' => 'sms']);
        $a = $this->template(['name' => 'A reminder', 'channel_type' => 'sms']);
        $this->template(['name' => 'C reminder', 'channel_type' => 'email']);
        $this->template(['name' => 'D reminder', 'channel_type' => 'sms', 'is_active' => false]);
        MessageTemplate::factory()->create(['organization_id' => $this->other->id, 'channel_type' => 'sms']);

        $response = $this->apiGet('/messaging/templates?channel_type=sms&is_active=true');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$a->id, $b->id], array_column($response->json('data'), 'id'));
    }

    public function test_store_applies_defaults_and_refuses_another_organizations_parent_template(): void
    {
        $theirs = MessageTemplate::factory()->create(['organization_id' => $this->other->id]);
        $payload = [
            'name' => 'Invoice issued',
            'code' => 'invoice-issued',
            'channel_type' => 'email',
            'category' => 'transactional',
            'body' => 'Hello {{name}}',
        ];

        $this->apiPost('/messaging/templates', [...$payload, 'parent_template_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_template_id']);

        $this->apiPost('/messaging/templates', $payload)
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_system_templates_cannot_be_changed_and_a_custom_one_is_deleted_with_its_approvals(): void
    {
        $system = $this->template(['is_system' => true]);
        $this->apiPut("/messaging/templates/{$system->id}", ['name' => 'Renamed'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SYSTEM_TEMPLATE');
        $this->apiDelete("/messaging/templates/{$system->id}")->assertStatus(422);

        $custom = $this->template();
        $this->apiPut("/messaging/templates/{$custom->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        ChannelTemplateApproval::factory()->create(['template_id' => $custom->id]);

        $this->apiDelete("/messaging/templates/{$custom->id}")->assertOk();
        $this->assertNull(MessageTemplate::find($custom->id));
        $this->assertSame(0, ChannelTemplateApproval::where('template_id', $custom->id)->count());
    }

    private function template(array $attributes = []): MessageTemplate
    {
        return MessageTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'is_system' => false,
            ...$attributes,
        ]);
    }
}
