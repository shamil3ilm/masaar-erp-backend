<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The platform's directory of tenant organizations and their users, for super admins.
 *
 * These reads span every organization by design; callers must already have
 * confirmed the super-admin role.
 */
class PlatformOrganizationService
{
    /**
     * Organizations with their user counts, newest first.
     *
     * @param  array{status?: ?string, search?: ?string}  $filters
     */
    public function paginateOrganizations(array $filters, mixed $perPage): LengthAwarePaginator
    {
        return Organization::when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->withCount('users')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function withCounts(Organization $organization): Organization
    {
        return $organization->loadCount('users', 'branches');
    }

    public function suspend(Organization $organization): Organization
    {
        $organization->update(['status' => 'suspended']);

        return $organization->fresh();
    }

    public function activate(Organization $organization): Organization
    {
        $organization->update(['status' => 'active']);

        return $organization->fresh();
    }

    /**
     * Users of every organization, searched by name or email, newest first.
     */
    public function paginateUsers(?string $search, mixed $perPage): LengthAwarePaginator
    {
        return User::with('organization')
            ->when($search, fn ($query, $term) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
