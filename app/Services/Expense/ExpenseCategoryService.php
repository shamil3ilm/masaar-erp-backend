<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Expense\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The organization's expense category tree.
 *
 * A category is kept out of its own subtree: a parent that is the category
 * itself or one of its descendants would turn the tree into a loop.
 */
final class ExpenseCategoryService
{
    /**
     * Every category by name, for a flat dropdown.
     *
     * @return Collection<int, ExpenseCategory>
     */
    public function allCategories(?bool $isActive): Collection
    {
        return $this->withTreeRelations($isActive)->orderBy('name')->get();
    }

    /**
     * The root categories by name, each with its children.
     */
    public function paginateRootCategories(?bool $isActive, int $perPage): LengthAwarePaginator
    {
        return $this->withTreeRelations($isActive)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($data)->load(['parent', 'defaultAccount:id,code,name']);
    }

    /**
     * @throws BusinessRuleException when the new parent is the category or below it
     */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        if (isset($data['parent_id'])) {
            $this->assertParentOutsideSubtree($category, (int) $data['parent_id']);
        }

        $category->update($data);

        return $category->fresh(['parent', 'children', 'defaultAccount:id,code,name']);
    }

    /**
     * @throws BusinessRuleException when the category has expenses or sub-categories
     */
    public function delete(ExpenseCategory $category): void
    {
        if ($category->expenses()->exists()) {
            throw new BusinessRuleException('Cannot delete category with existing expenses', 'HAS_DEPENDENCIES', 400);
        }

        if ($category->children()->exists()) {
            throw new BusinessRuleException('Cannot delete category with sub-categories', 'HAS_DEPENDENCIES', 400);
        }

        $category->delete();
    }

    private function withTreeRelations(?bool $isActive): Builder
    {
        return ExpenseCategory::with(['children', 'defaultAccount:id,code,name'])
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive));
    }

    /**
     * Walks up from the proposed parent; reaching the category means the
     * parent sits inside its subtree. Ancestors already seen stop the walk, so
     * a loop already in the data cannot hang it.
     */
    private function assertParentOutsideSubtree(ExpenseCategory $category, int $parentId): void
    {
        if ($parentId === $category->id) {
            throw new BusinessRuleException('A category cannot be its own parent', 'VALIDATION_ERROR', 422);
        }

        $seen = [];
        $ancestorId = $parentId;

        while ($ancestorId !== null && ! isset($seen[$ancestorId])) {
            if ($ancestorId === $category->id) {
                throw new BusinessRuleException('A category cannot be placed under one of its own sub-categories', 'VALIDATION_ERROR', 422);
            }

            $seen[$ancestorId] = true;
            $next = ExpenseCategory::whereKey($ancestorId)->value('parent_id');
            $ancestorId = $next === null ? null : (int) $next;
        }
    }
}
