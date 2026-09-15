<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Core\Organization;
use App\Models\Messaging\MessagingConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the messaging channel endpoints, keeps one default channel per channel
 * type within the organization, and keeps provider credentials out of responses.
 */
class MessagingConfigurationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['messaging.configurations.view', 'messaging.configurations.manage']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_provider_credentials_never_leave_in_a_response(): void
    {
        $response = $this->apiPost('/messaging/configurations', [
            'channel_type' => 'sms',
            'name' => 'Twilio',
            'provider' => 'twilio',
            'credentials' => ['auth_token' => 'secret-token'],
        ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('credentials', $response->json('data'));
        $id = $response->json('data.id');
        $this->assertSame(['auth_token' => 'secret-token'], MessagingConfiguration::findOrFail($id)->credentials);

        foreach ([
            $this->apiGet("/messaging/configurations/{$id}")->json('data'),
            $this->apiGet('/messaging/configurations')->json('data.0'),
            $this->apiPut("/messaging/configurations/{$id}", ['name' => 'Twilio main'])->json('data'),
        ] as $data) {
            $this->assertArrayNotHasKey('credentials', $data);
            $this->assertStringNotContainsString('secret-token', json_encode($data));
        }
    }

    public function test_a_new_default_replaces_the_previous_default_of_that_type_in_this_organization_only(): void
    {
        $previous = $this->configuration(['channel_type' => 'sms', 'is_default' => true]);
        $email = $this->configuration(['channel_type' => 'email', 'is_default' => true]);
        $theirs = MessagingConfiguration::factory()->create([
            'organization_id' => $this->other->id,
            'channel_type' => 'sms',
            'credentials' => ['key' => 'k'],
            'is_default' => true,
        ]);

        $this->apiPost('/messaging/configurations', [
            'channel_type' => 'sms',
            'name' => 'Vonage',
            'provider' => 'vonage',
            'credentials' => ['key' => 'k'],
            'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true);

        $this->assertFalse($previous->fresh()->is_default);
        $this->assertTrue($email->fresh()->is_default);
        $this->assertTrue(MessagingConfiguration::withoutGlobalScopes()->findOrFail($theirs->id)->is_default);
        $this->assertSame(1, MessagingConfiguration::where('channel_type', 'sms')->where('is_default', true)->count());
    }

    public function test_index_orders_by_channel_type_and_the_default_channel_cannot_be_deleted(): void
    {
        $whatsapp = $this->configuration(['channel_type' => 'whatsapp', 'name' => 'A', 'is_default' => false]);
        $email = $this->configuration(['channel_type' => 'email', 'name' => 'Z', 'is_default' => true]);
        $sms = $this->configuration(['channel_type' => 'sms', 'name' => 'B', 'is_default' => false]);
        MessagingConfiguration::factory()->create(['organization_id' => $this->other->id, 'credentials' => ['key' => 'k']]);

        $response = $this->apiGet('/messaging/configurations?is_active=true');
        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$email->id, $sms->id, $whatsapp->id], array_column($response->json('data'), 'id'));

        $this->apiDelete("/messaging/configurations/{$email->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEFAULT_CHANNEL');
        $this->apiDelete("/messaging/configurations/{$sms->id}")->assertOk();
        $this->assertNull(MessagingConfiguration::find($sms->id));
    }

    private function configuration(array $attributes = []): MessagingConfiguration
    {
        return MessagingConfiguration::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => 'twilio',
            'credentials' => ['key' => 'k'],
            ...$attributes,
        ]);
    }
}
