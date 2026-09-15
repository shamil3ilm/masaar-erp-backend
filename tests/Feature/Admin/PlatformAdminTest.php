<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin\PlatformAdmin;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the platform admin, organization and user endpoints, keeps them for
 * super admins, and writes an admin's password only as a hash.
 */
class PlatformAdminTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->user = User::factory()->superAdmin()->create(['organization_id' => $this->organization->id]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_admins_list_newest_first_and_a_user_who_is_not_a_super_admin_is_refused(): void
    {
        $older = PlatformAdmin::factory()->create(['created_at' => now()->subDay()]);
        $newer = PlatformAdmin::factory()->create();

        $response = $this->admin('GET', '/admins')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));

        $this->setUpAuthenticatedUser();
        $this->admin('GET', '/admins')->assertForbidden();
        $this->admin('PUT', "/admins/{$older->getRouteKey()}", ['role' => 'super_admin'])->assertForbidden();
    }

    public function test_store_hashes_the_password_and_sets_no_security_columns(): void
    {
        $response = $this->admin('POST', '/admins', [
            'name' => 'Ops',
            'email' => 'ops@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'support',
            'two_factor_secret' => 'planted',
            'is_2fa_enabled' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'support');
        $this->assertArrayNotHasKey('password', $response->json('data'));

        $admin = PlatformAdmin::where('email', 'ops@example.com')->sole();
        $this->assertTrue(Hash::check('secret-pass', $admin->password));
        $this->assertNull($admin->two_factor_secret);
        $this->assertFalse((bool) $admin->is_2fa_enabled);
    }

    public function test_update_hashes_a_new_password_and_sets_no_security_columns(): void
    {
        $admin = PlatformAdmin::factory()->create(['role' => 'admin']);

        $this->admin('PUT', "/admins/{$admin->getRouteKey()}", [
            'role' => 'support',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
            'two_factor_secret' => 'planted',
        ])->assertOk()->assertJsonPath('data.role', 'support');

        $fresh = $admin->fresh();
        $this->assertTrue(Hash::check('new-secret-1', $fresh->password));
        $this->assertNull($fresh->two_factor_secret);

        $this->admin('DELETE', "/admins/{$admin->getRouteKey()}")->assertOk()->assertJsonPath('data.message', 'Admin deleted');
        $this->assertSoftDeleted($admin);
    }

    public function test_organizations_filter_with_user_counts_and_can_be_suspended_and_activated(): void
    {
        $acme = Organization::factory()->create(['name' => 'Acme Trading', 'status' => 'active']);
        Organization::factory()->create(['name' => 'Acme Paused', 'status' => 'suspended']);
        User::factory()->count(2)->create(['organization_id' => $acme->id]);

        $response = $this->admin('GET', '/organizations?status=active&search=Acme')->assertOk();
        $this->assertSame([$acme->id], array_column($response->json('data'), 'id'));
        $this->assertSame(2, $response->json('data.0.users_count'));

        $this->admin('GET', "/organizations/{$acme->getRouteKey()}")->assertOk()->assertJsonPath('data.users_count', 2);
        $this->admin('POST', "/organizations/{$acme->getRouteKey()}/suspend")->assertStatus(422);
        $this->admin('POST', "/organizations/{$acme->getRouteKey()}/suspend", ['reason' => 'Unpaid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
        $this->admin('POST', "/organizations/{$acme->getRouteKey()}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_users_are_searched_by_name_with_their_organization(): void
    {
        $match = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zed Findme']);

        $response = $this->admin('GET', '/users?search=Findme')->assertOk();

        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->organization->id, $response->json('data.0.organization.id'));
    }

    private function admin(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->json($method, "/api/v1/admin{$uri}", $data, $this->adminHeaders());
    }
}
