<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Maintenance\EquipmentCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists, records and removes equipment categories. Queries run under the
 * caller's organization scope.
 */
class EquipmentCategoryService
{
    /**
     * Categories in name order, each with how much equipment it holds.
     */
    public function paginate(mixed $search, int $perPage): LengthAwarePaginator
    {
        return EquipmentCategory::query()
            ->when($search, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->withCount('equipment')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): EquipmentCategory
    {
        return EquipmentCategory::create($data);
    }

    public function update(EquipmentCategory $category, array $data): EquipmentCategory
    {
        $category->update($data);

        return $category->fresh();
    }

    /**
     * @throws BusinessRuleException when equipment is still assigned to the category
     */
    public function delete(EquipmentCategory $category): void
    {
        if ($category->equipment()->exists()) {
            throw new BusinessRuleException('Cannot delete a category that has equipment assigned to it.', 'HAS_EQUIPMENT');
        }

        $category->delete();
    }
}
