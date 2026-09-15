<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the role endpoints: listing, creating, showing, changing and deleting
 * the organization's roles with their permissions. A caller who is not a super
 * admin may put on a role only permissions they hold; system roles are not
 * changed or deleted, nor is a role still assigned to a user.
 */
class RoleEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'core.roles.view',
            'core.roles.create',
            'core.roles.edit',
            'core.roles.delete',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_roles_are_listed_by_name_for_the_organization_and_filtered(): void
    {
        $zeta = $this->role(['name' => 'Zeta Clerks', 'slug' => 'zeta-clerks']);
        $alpha = $this->role(['name' => 'Alpha Clerks', 'slug' => 'alpha-clerks', 'is_system' => true]);
        $zeta->permissions()->attach($this->permission('core.roles.view')->id);
        $this->role(['organization_id' => $this->otherOrg->id, 'name' => 'Beta Clerks']);

        $ids = fn (string $query) => array_column($this->apiGet('/roles'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$alpha->id, $zeta->id], $ids('?search=Clerks'));
        $this->assertSame([$alpha->id], $ids('?search=Clerks&is_system=1'));
        $this->assertSame([$zeta->id], $ids('?search=zeta-clerks'));

        $this->apiGet('/roles?search=Clerks&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $alpha->id);
        $this->apiGet('/roles?search=Zeta')->assertJsonPath('data.0.permissions.0.slug', 'core.roles.view');
    }

    public function test_a_role_is_created_shown_updated_and_deleted(): void
    {
        $view = $this->permission('core.roles.view');
        $edit = $this->permission('core.roles.edit');

        $created = $this->apiPost('/roles', [
            'name' => 'Store Managers',
            'description' => 'Runs the store',
            'permission_ids' => [$view->id],
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('message', 'Role created successfully.')
            ->assertJsonPath('data.slug', 'store-managers')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions.0.id', $view->id)
            ->assertJsonPath('data.permissions.0.module', 'core');
        $id = $created->json('data.id');
        $this->assertSame($this->organization->id, Role::findOrFail($id)->organization_id);

        User::factory()->create(['organization_id' => $this->organization->id])->roles()->attach($id);

        $this->apiGet("/roles/{$id}")
            ->assertOk()
            ->assertJsonPath('data.users_count', 1)
            ->assertJsonPath('data.permissions.0.slug', 'core.roles.view');

        $this->apiPut("/roles/{$id}", ['name' => 'Floor Managers', 'description' => null, 'permission_ids' => [$edit->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Role updated successfully.')
            ->assertJsonPath('data.name', 'Floor Managers')
            ->assertJsonPath('data.description', 'Runs the store')
            ->assertJsonPath('data.permissions.0.id', $edit->id)
            ->assertJsonMissingPath('data.users_count');

        $this->apiDelete("/roles/{$id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Cannot delete role with assigned users. Reassign users first.');

        $bare = $this->role();
        $bare->permissions()->attach($view->id);
        $this->apiDelete("/roles/{$bare->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Role deleted successfully.');
        $this->assertNull(Role::find($bare->id));
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $bare->id]);
    }

    public function test_a_permission_the_caller_lacks_cannot_be_put_on_a_role(): void
    {
        $settings = $this->permission('core.settings.edit', 'Edit Settings');
        $role = $this->role();

        $this->apiPost('/roles', ['name' => 'Escalated', 'permission_ids' => [$settings->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors.permission_ids.0', "Cannot assign permission 'Edit Settings' that you do not have.");
        $this->assertFalse(Role::where('name', 'Escalated')->exists());

        $this->apiPut("/roles/{$this->role->id}", ['permission_ids' => [$settings->id]])->assertStatus(422);
        $this->assertFalse($this->role->permissions()->where('permissions.id', $settings->id)->exists());
        $this->assertCount(0, $role->permissions()->get());
    }

    public function test_system_roles_are_not_changed_or_deleted(): void
    {
        $system = $this->role(['is_system' => true]);

        $this->apiPut("/roles/{$system->id}", ['name' => str_repeat('x', 300)])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'System roles cannot be modified.');
        $this->apiDelete("/roles/{$system->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'System roles cannot be deleted.');
        $this->assertNotNull(Role::find($system->id));
    }

    public function test_another_organizations_role_is_not_found(): void
    {
        $foreign = $this->role(['organization_id' => $this->otherOrg->id]);

        $this->apiGet("/roles/{$foreign->id}")->assertNotFound();
        $this->apiPut("/roles/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->apiDelete("/roles/{$foreign->id}")->assertNotFound();
        $this->assertNotNull(Role::withoutGlobalScopes()->find($foreign->id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function role(array $attributes = []): Role
    {
        return Role::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    private function permission(string $slug, ?string $name = null): Permission
    {
        return Permission::firstOrCreate(['slug' => $slug], ['name' => $name ?? $slug, 'module' => 'core']);
    }
}
