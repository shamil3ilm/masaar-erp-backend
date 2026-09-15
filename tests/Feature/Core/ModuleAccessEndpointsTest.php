<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ModuleAccessLog;
use App\Models\Core\ModuleDefinition;
use App\Models\Core\Role;
use App\Models\Core\RoleModulePermission;
use App\Models\Core\UserModuleOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the super admin module access endpoints: switching a module on or off
 * for the organization, a role's permissions on a module, a user's overrides
 * and the access log. An override or role permission lands on the user, role
 * and module the route names, whatever the body says.
 */
class ModuleAccessEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ModuleDefinition $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->token = JWTAuth::fromUser($this->user);

        $this->module = ModuleDefinition::factory()->create();
    }

    public function test_a_module_is_switched_on_and_off_for_the_organization(): void
    {
        $this->apiPatch('/module-access/organization-modules/999999/active', ['active' => true])
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Module not found.');

        $this->apiPatch("/module-access/organization-modules/{$this->module->id}/active", ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.module.id', $this->module->id);

        $this->apiPatch("/module-access/organization-modules/{$this->module->id}/active", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);
    }

    public function test_a_users_override_is_set_listed_and_removed(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $uri = "/module-access/users/{$member->id}/overrides/{$this->module->id}";

        $this->apiPut($uri, ['override_type' => 'revoke', 'can_view' => false, 'reason' => 'Left the team'])
            ->assertOk()
            ->assertJsonPath('data.user_id', $member->id)
            ->assertJsonPath('data.module_id', $this->module->id)
            ->assertJsonPath('data.granted_by', $this->user->id);

        $this->apiGet("/module-access/users/{$member->id}/overrides")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.module.id', $this->module->id);

        $this->apiDelete($uri)->assertOk()->assertJsonPath('data.message', 'Override removed');
        $this->apiDelete($uri)->assertNotFound()->assertJsonPath('error.message', 'Override not found.');
    }

    public function test_an_override_lands_on_the_user_and_module_the_route_names(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $bystander = User::factory()->create(['organization_id' => $this->organization->id]);
        $otherModule = ModuleDefinition::factory()->create();

        $this->apiPut("/module-access/users/{$member->id}/overrides/{$this->module->id}", [
            'override_type' => 'grant',
            'can_approve' => true,
            'user_id' => $bystander->id,
            'module_id' => $otherModule->id,
            'granted_by' => $bystander->id,
        ])->assertOk();

        $override = UserModuleOverride::sole();
        $this->assertSame([$member->id, $this->module->id, $this->user->id], [
            (int) $override->user_id, (int) $override->module_id, (int) $override->granted_by,
        ]);
    }

    public function test_a_role_permission_lands_on_the_role_and_module_the_route_names(): void
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $otherRole = Role::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPut("/module-access/roles/{$role->id}/permissions/{$this->module->id}", [
            'can_view' => true,
            'can_delete' => true,
            'role_id' => $otherRole->id,
        ])->assertOk();

        $permission = RoleModulePermission::sole();
        $this->assertSame([$role->id, $this->module->id], [(int) $permission->role_id, (int) $permission->module_id]);

        $this->apiGet("/module-access/roles/{$role->id}/permissions")
            ->assertOk()
            ->assertJsonPath('data.0.module.id', $this->module->id)
            ->assertJsonPath('data.0.can_delete', true);
    }

    public function test_the_access_log_is_paged_latest_first(): void
    {
        $older = $this->logEntry(now()->subHour());
        $newer = $this->logEntry(now());

        $this->apiGet('/module-access/access-logs?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.module.id', $this->module->id)
            ->assertJsonPath('data.0.user.id', $this->user->id)
            ->assertJsonMissingPath('data.0.user.password');
        $this->assertNotSame($older->id, $newer->id);
    }

    private function logEntry(\DateTimeInterface $at): ModuleAccessLog
    {
        return ModuleAccessLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'module_id' => $this->module->id,
            'action' => 'view',
            'was_allowed' => true,
            'accessed_at' => $at,
        ]);
    }
}
