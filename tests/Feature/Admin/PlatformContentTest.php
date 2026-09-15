<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin\FeatureFlag;
use App\Models\Admin\PlatformSetting;
use App\Models\Admin\SystemAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the platform announcement, setting and feature flag endpoints.
 */
class PlatformContentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->user = User::factory()->superAdmin()->create(['organization_id' => $this->organization->id]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_announcements_are_listed_created_updated_published_and_deleted(): void
    {
        $older = SystemAnnouncement::factory()->create(['created_at' => now()->subDay()]);
        $newer = SystemAnnouncement::factory()->create();

        $response = $this->admin('GET', '/announcements')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));

        $payload = ['title' => 'Maintenance', 'content' => 'Tonight', 'type' => 'maintenance'];
        $this->admin('POST', '/announcements', $payload)->assertStatus(422);
        $this->admin('POST', '/announcements', [
            ...$payload,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'target_audience' => 'admins',
        ])->assertCreated()->assertJsonPath('data.target_audience', 'admins');

        $announcement = SystemAnnouncement::where('title', 'Maintenance')->sole();
        $key = $announcement->getRouteKey();

        $this->admin('PUT', "/announcements/{$key}", ['title' => 'Maintenance window'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Maintenance window');
        $this->admin('POST', "/announcements/{$key}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull($announcement->fresh()->published_at);

        $this->admin('DELETE', "/announcements/{$key}")->assertOk()->assertJsonPath('data.message', 'Announcement deleted');
        $this->assertNull(SystemAnnouncement::find($announcement->id));
    }

    public function test_settings_are_read_written_and_bulk_updated(): void
    {
        PlatformSetting::factory()->create(['key' => 'site_name', 'value' => 'Masaar', 'group' => 'general']);

        $this->admin('GET', '/settings')->assertOk()->assertJsonCount(1, 'data');
        $this->admin('GET', '/settings/site_name')->assertOk()->assertJsonPath('data.value', 'Masaar');
        $this->admin('GET', '/settings/missing')->assertNotFound();

        $this->admin('PUT', '/settings/support_email', ['value' => 'help@example.com', 'group' => 'email'])
            ->assertOk()
            ->assertJsonPath('data.group', 'email');

        $this->admin('PUT', '/settings', ['settings' => ['site_name' => 'Masaar ERP', 'currency' => 'SAR']])
            ->assertOk()
            ->assertJsonPath('data.message', 'Settings updated');
        $this->assertSame('Masaar ERP', PlatformSetting::where('key', 'site_name')->value('value'));
        $this->assertSame('SAR', PlatformSetting::where('key', 'currency')->value('value'));
    }

    public function test_feature_flags_are_listed_by_code_created_updated_toggled_and_checked(): void
    {
        $beta = FeatureFlag::create(['name' => 'B', 'code' => 'b-flag', 'is_enabled' => false]);
        FeatureFlag::create(['name' => 'A', 'code' => 'a-flag', 'is_enabled' => true]);

        $response = $this->admin('GET', '/feature-flags')->assertOk();
        $this->assertSame(['a-flag', 'b-flag'], array_column($response->json('data'), 'code'));

        $this->admin('POST', '/feature-flags', ['name' => 'C', 'code' => 'a-flag'])->assertStatus(422);
        $this->admin('POST', '/feature-flags', ['name' => 'C', 'code' => 'c-flag'])->assertCreated()->assertJsonPath('data.code', 'c-flag');

        $this->admin('POST', "/feature-flags/{$beta->id}/toggle")->assertOk()->assertJsonPath('data.is_enabled', true);
        $this->admin('POST', "/feature-flags/{$beta->id}/toggle")->assertOk()->assertJsonPath('data.is_enabled', false);
        $this->admin('PUT', "/feature-flags/{$beta->id}", ['name' => 'Beta'])->assertOk()->assertJsonPath('data.name', 'Beta');

        $this->admin('GET', '/feature-flags/check/a-flag')->assertOk()->assertJsonPath('data.code', 'a-flag');
        $this->admin('GET', '/feature-flags/check/none')->assertNotFound();
    }

    private function admin(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->json($method, "/api/v1/admin{$uri}", $data, $this->adminHeaders());
    }
}
