<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the user endpoints: listing, creating, showing, changing and
 * deactivating the organization's users with their roles and branches.
 *
 * A role, branch or default branch must belong to the caller's organization.
 * A caller who is not a super admin may assign only roles holding permissions
 * they hold, and may change or remove only users holding nothing beyond them.
 */
class UserEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'core.users.view',
            'core.users.create',
            'core.users.edit',
            'core.users.delete',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_users_are_listed_for_the_organization_with_filters_and_sorting(): void
    {
        $role = $this->roleWith(['core.users.view'], ['slug' => 'clerk']);
        $alice = $this->member(['name' => 'Zed Alice', 'email' => 'alice@example.test']);
        $bob = $this->member(['name' => 'Zed Bob', 'email' => 'bob@example.test', 'is_active' => false]);
        $alice->roles()->attach($role->id);
        $bob->branches()->attach($this->branch->id, ['is_default' => true]);
        User::factory()->create(['organization_id' => $this->otherOrg->id, 'name' => 'Zed Foreign']);

        $ids = fn (string $query) => array_column($this->apiGet('/users'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$alice->id, $bob->id], $ids('?search=Zed'));
        $this->assertSame([$bob->id, $alice->id], $ids('?search=Zed&sort_by=email&sort_order=desc'));
        $this->assertSame([$alice->id], $ids('?search=Zed&role=clerk'));
        $this->assertSame([$bob->id], $ids('?search=Zed&is_active=0'));
        $this->assertSame([$bob->id], $ids("?search=Zed&branch_id={$this->branch->id}"));

        $this->apiGet('/users?search=Zed&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.roles.0.slug', 'clerk')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_a_user_is_created_with_roles_and_branches(): void
    {
        $role = $this->roleWith(['core.users.view']);
        $second = Branch::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->apiPost('/users', $this->payload([
            'role_ids' => [$role->id],
            'branch_ids' => [$this->branch->id, $second->id],
            'default_branch_id' => $second->id,
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('data.email', 'new.user@example.test')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.roles.0.id', $role->id)
            ->assertJsonPath('data.default_branch.id', $second->id)
            ->assertJsonMissingPath('data.password');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertSame($this->organization->id, $user->organization_id);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
    }

    public function test_language_and_timezone_take_their_defaults_and_are_not_cleared(): void
    {
        $id = $this->apiPost('/users', $this->payload())->assertStatus(201)->json('data.id');

        $this->apiGet("/users/{$id}")
            ->assertOk()
            ->assertJsonPath('data.preferred_language', 'en')
            ->assertJsonPath('data.timezone', 'Asia/Riyadh');

        $this->apiPut("/users/{$id}", ['preferred_language' => 'ar', 'timezone' => 'Asia/Dubai'])->assertOk();

        $this->apiPut("/users/{$id}", ['preferred_language' => null, 'timezone' => null])
            ->assertOk()
            ->assertJsonPath('data.preferred_language', 'ar')
            ->assertJsonPath('data.timezone', 'Asia/Dubai');
    }

    public function test_another_organizations_role_and_branches_are_refused(): void
    {
        $foreignRole = Role::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignBranch = Branch::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/users', $this->payload([
            'role_ids' => [$foreignRole->id],
            'branch_ids' => [$foreignBranch->id],
            'default_branch_id' => $foreignBranch->id,
        ]));

        $response->assertStatus(422);
        $errors = $response->json('errors') ?? [];
        $this->assertArrayHasKey('role_ids.0', $errors);
        $this->assertArrayHasKey('branch_ids.0', $errors);
        $this->assertArrayHasKey('default_branch_id', $errors);
        $this->assertFalse(User::where('email', 'new.user@example.test')->exists());

        $member = $this->member();
        $this->apiPut("/users/{$member->id}", ['role_ids' => [$foreignRole->id]])->assertStatus(422);
        $this->assertCount(0, $member->roles()->get());
    }

    public function test_a_role_holding_permissions_the_caller_lacks_cannot_be_assigned(): void
    {
        $admin = $this->roleWith(['core.users.view', 'core.roles.delete'], ['name' => 'Owner']);

        $this->apiPost('/users', $this->payload(['role_ids' => [$admin->id]]))
            ->assertStatus(422)
            ->assertJsonPath('errors.role_ids.0', "Cannot assign role 'Owner': it holds permissions you do not have.");
        $this->assertFalse(User::where('email', 'new.user@example.test')->exists());

        $this->apiPut("/users/{$this->user->id}", ['role_ids' => [$this->role->id, $admin->id]])
            ->assertStatus(422);
        $this->assertSame([$this->role->id], $this->user->roles()->pluck('roles.id')->all());
    }

    public function test_a_user_holding_more_than_the_caller_cannot_be_changed_or_removed(): void
    {
        $owner = $this->member(['password' => 'owner-pass-1']);
        $owner->roles()->attach($this->roleWith(['core.settings.edit'])->id);

        $this->apiPut("/users/{$owner->id}", [
            'password' => 'taken-over-1',
            'password_confirmation' => 'taken-over-1',
        ])->assertForbidden();
        $this->assertTrue(Hash::check('owner-pass-1', $owner->fresh()->password));

        $this->apiDelete("/users/{$owner->id}")->assertForbidden();
        $this->assertNotSoftDeleted($owner);

        $superAdmin = $this->member();
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $this->apiPut("/users/{$superAdmin->id}", ['name' => 'Renamed'])->assertForbidden();
    }

    public function test_a_user_is_shown_updated_and_deactivated(): void
    {
        $role = $this->roleWith(['core.users.view']);
        $member = $this->member(['name' => 'Before', 'phone' => '0500000000']);
        $member->roles()->attach($role->id);

        $this->apiGet("/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.roles.0.id', $role->id)
            ->assertJsonPath('data.organization.id', $this->organization->id);

        $this->apiPut("/users/{$member->id}", [
            'name' => 'After',
            'phone' => null,
            'password' => 'changed-pass-1',
            'password_confirmation' => 'changed-pass-1',
            'role_ids' => [],
            'branch_ids' => [$this->branch->id],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'User updated successfully.')
            ->assertJsonPath('data.name', 'After')
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.roles', [])
            ->assertJsonPath('data.default_branch.id', $this->branch->id);
        $this->assertTrue(Hash::check('changed-pass-1', $member->fresh()->password));

        $this->apiDelete("/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User deactivated successfully.');
        $this->assertSoftDeleted($member);
        $this->assertFalse((bool) User::withTrashed()->findOrFail($member->id)->is_active);
    }

    public function test_the_caller_cannot_delete_their_own_account(): void
    {
        $this->apiDelete("/users/{$this->user->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'You cannot delete your own account.');
        $this->assertNotSoftDeleted($this->user);
    }

    public function test_another_organizations_user_is_forbidden(): void
    {
        $foreign = User::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiGet("/users/{$foreign->id}")->assertForbidden();
        $this->apiPut("/users/{$foreign->id}", ['name' => 'X'])->assertForbidden();
        $this->apiDelete("/users/{$foreign->id}")->assertForbidden();
        $this->assertNotSoftDeleted($foreign);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    /**
     * A role of the organization holding the given permissions.
     *
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    private function roleWith(array $permissions, array $attributes = []): Role
    {
        $role = Role::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));

        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'core']);
            $role->permissions()->attach($permission->id);
        }

        return $role;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'new.user@example.test',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ], $overrides);
    }
}
