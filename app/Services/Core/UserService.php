<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Staff accounts of an organization: listing, creating, changing and
 * deactivating them with their roles and branches.
 *
 * A role grants the permissions it holds, so assigning one is granting them.
 * An actor who is not a super admin may assign only roles whose permissions
 * they hold, and may change or remove only accounts holding nothing beyond
 * their own permissions. Without both, a user administrator could hand anyone
 * the owner's role, or reset the owner's password and sign in as them.
 */
class UserService
{
    /** Columns the user list may be sorted by. */
    public const SORTABLE = ['name', 'email', 'created_at', 'updated_at', 'is_active'];

    /**
     * Users of the organization with their branches and roles, narrowed by
     * the filters that are set; search matches name, email or phone.
     *
     * @param  array{search?: ?string, role?: ?string, is_active?: ?bool, branch_id?: mixed}  $filters
     */
    public function list(int $organizationId, array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return User::where('organization_id', $organizationId)
            ->with(['branches', 'roles'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($roles) => $roles->where('slug', $role)))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->whereHas('branches', fn ($branches) => $branches->where('branches.id', $branchId)))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * A user of the organization by id; another organization's user is not found.
     */
    public function findInOrganization(int $organizationId, int $userId): User
    {
        return User::where('organization_id', $organizationId)->findOrFail($userId);
    }

    public function details(User $user): User
    {
        return $user->load(['branches', 'roles.permissions', 'organization']);
    }

    /**
     * Whether the actor may change or remove the target account: a super admin
     * always may; anyone else only when the target is not a super admin and
     * holds no permission the actor lacks.
     */
    public function mayManage(User $actor, User $target): bool
    {
        if ($actor->is_super_admin) {
            return true;
        }

        if ($target->is_super_admin) {
            return false;
        }

        return array_diff($target->getAllPermissions(), $actor->getAllPermissions()) === [];
    }

    /**
     * Creates a user in the actor's organization with its roles and branches.
     *
     * @param  array<string, mixed>  $data  validated fields; role and branch ids belong to the organization
     *
     * @throws ValidationException when a role holds a permission the actor lacks
     */
    public function create(User $actor, array $data): User
    {
        $this->assertMayAssignRoles($actor, $data['role_ids'] ?? []);

        return DB::transaction(function () use ($actor, $data) {
            // Language and timezone are not nullable; when left out the column defaults apply.
            $user = User::create(array_merge([
                'organization_id' => $actor->organization_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ], array_filter(
                ['preferred_language' => $data['preferred_language'] ?? null, 'timezone' => $data['timezone'] ?? null],
                fn ($value) => $value !== null
            )));

            if (! empty($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            if (! empty($data['branch_ids'])) {
                $defaultBranchId = $data['default_branch_id'] ?? $data['branch_ids'][0] ?? null;
                $user->branches()->sync($this->branchAssignments($data['branch_ids'], $defaultBranchId));
            }

            return $user->load(['branches', 'roles']);
        });
    }

    /**
     * Changes a user's details and, when given, replaces its roles and branches.
     * Phone may be cleared; other fields sent as null are kept, language and
     * timezone included, because their columns are not nullable.
     *
     * @param  array<string, mixed>  $data  validated fields; role and branch ids belong to the organization
     *
     * @throws ValidationException when a role holds a permission the actor lacks
     */
    public function update(User $actor, User $user, array $data): User
    {
        if (array_key_exists('role_ids', $data)) {
            $this->assertMayAssignRoles($actor, $data['role_ids'] ?? []);
        }

        return DB::transaction(function () use ($user, $data) {
            $changes = collect($data)
                ->only(['name', 'email', 'phone', 'preferred_language', 'timezone', 'is_active'])
                ->filter(fn ($value, $key) => $value !== null || $key === 'phone')
                ->toArray();

            if (! empty($data['password'])) {
                $changes['password'] = Hash::make($data['password']);
            }

            $user->update($changes);

            if (array_key_exists('role_ids', $data)) {
                $user->roles()->sync($data['role_ids'] ?? []);
            }

            if (array_key_exists('branch_ids', $data)) {
                $branchIds = $data['branch_ids'] ?? [];
                $defaultBranchId = $data['default_branch_id'] ?? ($branchIds[0] ?? null);
                $user->branches()->sync($this->branchAssignments($branchIds, $defaultBranchId));
            }

            return $user->fresh(['branches', 'roles']);
        });
    }

    /**
     * Deactivates and soft-deletes a user in one change.
     *
     * @throws InvalidArgumentException when the actor names their own account
     */
    public function deactivate(User $actor, User $user): void
    {
        if ($user->id === $actor->id) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }

        DB::transaction(function () use ($user) {
            $user->update(['is_active' => false]);
            $user->delete();
        });
    }

    /**
     * @param  list<int>  $roleIds
     *
     * @throws ValidationException naming the first role that holds a permission the actor lacks
     */
    private function assertMayAssignRoles(User $actor, array $roleIds): void
    {
        if ($actor->is_super_admin || $roleIds === []) {
            return;
        }

        $held = $actor->getAllPermissions();

        foreach (Role::with('permissions')->whereIn('id', $roleIds)->orderBy('id')->get() as $role) {
            if ($role->permissions->pluck('slug')->diff($held)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'role_ids' => ["Cannot assign role '{$role->name}': it holds permissions you do not have."],
                ]);
            }
        }
    }

    /**
     * Pivot rows for user_branches, marking the default branch.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{is_default: bool}>
     */
    private function branchAssignments(array $branchIds, mixed $defaultBranchId): array
    {
        $assignments = [];

        foreach ($branchIds as $branchId) {
            $assignments[$branchId] = ['is_default' => $branchId === $defaultBranchId];
        }

        return $assignments;
    }
}
