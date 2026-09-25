<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

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
     * Sets an organization's parent, or clears it when $parentId is null.
     *
     * A group is a parent with its subsidiaries and nothing deeper, because
     * Organization::groupIds() reads membership from that one link: the root
     * of an organization is its parent or itself, so a grandchild would sit
     * in a group of its own while its records name the grandparent's. The
     * four refusals below are what holds the stored links to that shape.
     *
     * @throws InvalidArgumentException when the link would not leave a group
     *                                  two levels deep and free of cycles
     */
    public function setParent(Organization $organization, ?int $parentId): Organization
    {
        if ($parentId !== null) {
            $this->assertParentIsAllowed($organization, $parentId);
        }

        $organization->parent_organization_id = $parentId;
        $organization->save();

        return $organization->fresh();
    }

    private function assertParentIsAllowed(Organization $organization, int $parentId): void
    {
        if ($parentId === (int) $organization->id) {
            throw new InvalidArgumentException('An organization cannot be its own parent.');
        }

        $parent = Organization::find($parentId);

        if ($parent === null) {
            throw new InvalidArgumentException('The proposed parent organization was not found.');
        }

        if ($this->isAncestorOf($organization, $parent)) {
            throw new InvalidArgumentException('The proposed parent is below this organization, which would close a cycle.');
        }

        if ($parent->parent_organization_id !== null) {
            throw new InvalidArgumentException('The proposed parent already has a parent, and a group is only two levels deep.');
        }

        if ($organization->subsidiaries()->exists()) {
            throw new InvalidArgumentException('This organization already has subsidiaries, and a group is only two levels deep.');
        }
    }

    /**
     * Whether $organization is above $candidate in the chain of parents.
     *
     * The walk climbs from $candidate rather than trusting the two-level
     * shape, and stops on an id it has already seen so a cycle left by older
     * data cannot spin here.
     */
    private function isAncestorOf(Organization $organization, Organization $candidate): bool
    {
        $seen = [];
        $ancestorId = $candidate->parent_organization_id === null ? null : (int) $candidate->parent_organization_id;

        while ($ancestorId !== null && ! isset($seen[$ancestorId])) {
            if ($ancestorId === (int) $organization->id) {
                return true;
            }

            $seen[$ancestorId] = true;
            $next = Organization::query()->whereKey($ancestorId)->value('parent_organization_id');
            $ancestorId = $next === null ? null : (int) $next;
        }

        return false;
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
