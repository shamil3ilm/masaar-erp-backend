<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * An organization's roles and the permissions they grant.
 *
 * An actor who is not a super admin may put on a role only permissions they
 * hold; otherwise anyone who can edit roles could grant themselves anything
 * through a role they already have. System roles are neither changed nor
 * deleted, and a role still assigned to a user is not deleted.
 */
class RoleService
{
    /**
     * Roles of the organization with their permissions, by name; search
     * matches name, slug or description.
     *
     * @param  array{search?: ?string, is_system?: ?bool}  $filters
     */
    public function list(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Role::with('permissions')
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['is_system']), fn ($query) => $query->where('is_system', $filters['is_system']))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * A role with its permissions and the number of users holding it.
     */
    public function details(Role $role): Role
    {
        return $role->load('permissions')->loadCount('users');
    }

    /**
     * @param  array<string, mixed>  $data  validated fields
     *
     * @throws ValidationException when a permission is one the actor does not hold
     */
    public function create(User $actor, array $data): Role
    {
        $this->assertMayGrant($actor, $data['permission_ids'] ?? []);

        return DB::transaction(function () use ($actor, $data) {
            $role = Role::create([
                'organization_id' => $actor->organization_id,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            if (! empty($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Changes name, slug and description, ignoring nulls, and replaces the
     * permissions when they are given.
     *
     * @param  array<string, mixed>  $data  validated fields
     *
     * @throws InvalidArgumentException for a system role
     * @throws ValidationException when a permission is one the actor does not hold
     */
    public function update(User $actor, Role $role, array $data): Role
    {
        $this->assertModifiable($role, 'System roles cannot be modified.');
        $this->assertMayGrant($actor, $data['permission_ids'] ?? []);

        return DB::transaction(function () use ($role, $data) {
            $role->update(
                collect($data)->only(['name', 'slug', 'description'])->reject(fn ($value) => $value === null)->toArray()
            );

            if (array_key_exists('permission_ids', $data)) {
                $role->permissions()->sync($data['permission_ids'] ?? []);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Deletes a role and its permission links. The user count is taken on the
     * locked role so a user assigned meanwhile is not left on a deleted role.
     *
     * @throws InvalidArgumentException for a system role or a role still assigned to a user
     */
    public function delete(Role $role): void
    {
        $this->assertModifiable($role, 'System roles cannot be deleted.');

        DB::transaction(function () use ($role) {
            $locked = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            if ($locked->users()->count() > 0) {
                throw new InvalidArgumentException('Cannot delete role with assigned users. Reassign users first.');
            }

            $locked->permissions()->detach();
            $locked->delete();
        });
    }

    private function assertModifiable(Role $role, string $message): void
    {
        if ($role->is_system) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Refuses the first permission, in the order given, that the actor does not hold.
     *
     * @param  list<int>  $permissionIds
     *
     * @throws ValidationException
     */
    private function assertMayGrant(User $actor, array $permissionIds): void
    {
        if ($actor->is_super_admin || $permissionIds === []) {
            return;
        }

        $held = $actor->getAllPermissions();
        $permissions = Permission::whereIn('id', $permissionIds)->get()->keyBy('id');

        foreach ($permissionIds as $permissionId) {
            $permission = $permissions->get($permissionId);

            if ($permission && ! in_array($permission->slug, $held, true)) {
                throw ValidationException::withMessages([
                    'permission_ids' => ["Cannot assign permission '{$permission->name}' that you do not have."],
                ]);
            }
        }
    }
}
