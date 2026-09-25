<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Core\Organization;
use App\Models\Messaging\MessagingConfiguration;
use App\Services\Messaging\MessagingConfigurationService;
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
            $this->assertArrayNotHasKey('default_for_type', $data);
            $this->assertStringNotContainsString('secret-token', json_encode($data));
        }
    }

    public function test_the_first_channel_of_a_type_is_the_default_only_when_the_request_asks(): void
    {
        $this->apiPost('/messaging/configurations', [
            'channel_type' => 'sms',
            'name' => 'Twilio',
            'provider' => 'twilio',
            'credentials' => ['key' => 'k'],
        ])->assertCreated()->assertJsonPath('data.is_default', false);

        $this->assertSame(0, $this->defaults('sms'));

        $this->apiPost('/messaging/configurations', [
            'channel_type' => 'sms',
            'name' => 'Vonage',
            'provider' => 'vonage',
            'credentials' => ['key' => 'k'],
            'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true);

        $this->assertSame(1, $this->defaults('sms'));
    }

    public function test_a_new_default_replaces_the_previous_default_of_that_type_in_this_organization_only(): void
    {
        $previous = $this->configuration(['channel_type' => 'sms'], default: true);
        $email = $this->configuration(['channel_type' => 'email'], default: true);
        $theirs = $this->theirConfiguration();

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
        $this->assertSame(1, $this->defaults('sms'));
    }

    public function test_the_default_moves_between_two_channels_of_the_same_type(): void
    {
        $first = $this->configuration(['channel_type' => 'sms', 'name' => 'Twilio'], default: true);
        $second = $this->configuration(['channel_type' => 'sms', 'name' => 'Vonage']);
        $theirs = $this->theirConfiguration();

        $this->apiPut("/messaging/configurations/{$second->id}", ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, $this->defaults('sms'));
        $this->assertTrue(MessagingConfiguration::withoutGlobalScopes()->findOrFail($theirs->id)->is_default);

        $this->apiPut("/messaging/configurations/{$second->id}", ['is_default' => false])
            ->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->assertSame(0, $this->defaults('sms'));
    }

    public function test_two_switches_started_from_the_same_state_leave_one_default(): void
    {
        $this->configuration(['channel_type' => 'sms', 'name' => 'Twilio'], default: true);
        $second = $this->configuration(['channel_type' => 'sms', 'name' => 'Vonage']);
        $third = $this->configuration(['channel_type' => 'sms', 'name' => 'Unifonic']);

        // Each request reads its own channel before either switch is written.
        $configurations = app(MessagingConfigurationService::class);
        $staleSecond = MessagingConfiguration::findOrFail($second->id);
        $staleThird = MessagingConfiguration::findOrFail($third->id);

        $configurations->update($staleSecond, ['is_default' => true]);
        $configurations->update($staleThird, ['is_default' => true]);

        $this->assertSame(1, $this->defaults('sms'));
        $this->assertTrue($third->fresh()->is_default);
    }

    public function test_index_orders_by_channel_type_and_the_default_channel_cannot_be_deleted(): void
    {
        $whatsapp = $this->configuration(['channel_type' => 'whatsapp', 'name' => 'A']);
        $email = $this->configuration(['channel_type' => 'email', 'name' => 'Z'], default: true);
        $sms = $this->configuration(['channel_type' => 'sms', 'name' => 'B']);
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

    /**
     * How many of the organization's channels are the default for a type.
     */
    private function defaults(string $channelType): int
    {
        return MessagingConfiguration::where('default_for_type', $channelType)->count();
    }

    private function configuration(array $attributes = [], bool $default = false): MessagingConfiguration
    {
        $attributes = [
            'organization_id' => $this->organization->id,
            'channel_type' => 'email',
            'provider' => 'twilio',
            'credentials' => ['key' => 'k'],
            ...$attributes,
        ];

        return MessagingConfiguration::factory()->create([
            ...$attributes,
            'default_for_type' => $default ? $attributes['channel_type'] : null,
        ]);
    }

    /**
     * Another organization's default SMS channel.
     */
    private function theirConfiguration(): MessagingConfiguration
    {
        return MessagingConfiguration::factory()->create([
            'organization_id' => $this->other->id,
            'channel_type' => 'sms',
            'credentials' => ['key' => 'k'],
            'default_for_type' => 'sms',
        ]);
    }
}
