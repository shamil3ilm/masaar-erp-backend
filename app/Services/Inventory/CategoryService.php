<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Product categories of the current organization, kept as a tree: a category
 * can never sit under itself or one of its descendants.
 */
class CategoryService
{
    /**
     * All categories, or only root categories with their whole subtree when
     * $tree; only active ones when $activeOnly.
     *
     * @return Collection<int, Category>
     */
    public function list(bool $tree, bool $activeOnly): Collection
    {
        return Category::query()
            ->when($tree, fn ($q) => $q->whereNull('parent_id')->with('allChildren'))
            ->when($activeOnly, fn ($q) => $q->active())
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return Category::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        if (isset($data['parent_id'])) {
            $this->assertCanBeParent($category, $data['parent_id'], 'Cannot set a descendant as parent.');
        }

        $category->update($data);

        return $category->fresh();
    }

    public function move(Category $category, mixed $parentId): Category
    {
        $this->assertCanBeParent($category, $parentId, 'Cannot move category under its own descendant.');

        $category->update(['parent_id' => $parentId]);

        return $category->fresh(['parent']);
    }

    public function delete(Category $category): void
    {
        if ($category->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete category with products. Move products first.');
        }

        if ($category->children()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete category with subcategories.');
        }

        $category->delete();
    }

    private function assertCanBeParent(Category $category, mixed $parentId, string $descendantMessage): void
    {
        if ($parentId === $category->id) {
            throw new \InvalidArgumentException('Category cannot be its own parent.');
        }

        $parent = $parentId ? Category::find($parentId) : null;

        if ($parent && $category->isAncestorOf($parent)) {
            throw new \InvalidArgumentException($descendantMessage);
        }
    }
}
