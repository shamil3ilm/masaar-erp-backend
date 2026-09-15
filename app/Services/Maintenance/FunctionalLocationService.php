<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Maintenance\FunctionalLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists, records and removes functional locations, the places equipment is
 * installed at. Queries run under the caller's organization scope.
 */
class FunctionalLocationService
{
    /**
     * Locations in name order. The search matches the name or the code and
     * applies together with the other filters.
     *
     * @param  array{search?: mixed, location_type?: mixed, roots_only?: bool, parent_id?: mixed}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return FunctionalLocation::query()
            ->with('parent')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->when($filters['location_type'] ?? null, fn ($query, $type) => $query->where('location_type', $type))
            ->when($filters['roots_only'] ?? false, fn ($query) => $query->roots())
            ->when($filters['parent_id'] ?? null, fn ($query, $parentId) => $query->where('parent_id', $parentId))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): FunctionalLocation
    {
        return FunctionalLocation::create($data);
    }

    public function update(FunctionalLocation $location, array $data): FunctionalLocation
    {
        $location->update($data);

        return $location->fresh();
    }

    /**
     * @throws BusinessRuleException when the location still has child locations or equipment
     */
    public function delete(FunctionalLocation $location): void
    {
        if ($location->children()->exists()) {
            throw new BusinessRuleException('Cannot delete a location that has child locations.', 'HAS_CHILDREN');
        }

        if ($location->equipment()->exists()) {
            throw new BusinessRuleException('Cannot delete a location with assigned equipment.', 'HAS_EQUIPMENT');
        }

        $location->delete();
    }
}
