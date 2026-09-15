<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\CategoryResource;
use App\Models\Inventory\Category;
use App\Services\Inventory\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CategoryController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private CategoryService $categoryService
    ) {}

    /**
     * List categories as tree or flat.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->list($request->boolean('tree'), $request->boolean('active_only'));

        return $this->success(CategoryResource::collection($categories));
    }

    /**
     * Create a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('categories')],
            'name' => 'required|string|max:100',
            'slug' => ['nullable', 'string', 'max:100', $this->uniqueSlug()],
            'description' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $category = $this->categoryService->create($validated);

        return $this->created(new CategoryResource($category), 'Category created successfully.');
    }

    /**
     * Show a category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children', 'products']);

        return $this->success(new CategoryResource($category));
    }

    /**
     * Update a category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('categories')],
            'name' => 'sometimes|required|string|max:100',
            'slug' => ['nullable', 'string', 'max:100', $this->uniqueSlug()->ignore($category->id)],
            'description' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        try {
            $category = $this->categoryService->update($category, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new CategoryResource($category), 'Category updated successfully.');
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->delete($category);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(null, 'Category deleted successfully.');
    }

    /**
     * Move a category to a new parent.
     */
    public function move(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('categories')],
        ]);

        try {
            $category = $this->categoryService->move($category, $request->input('parent_id'));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new CategoryResource($category), 'Category moved successfully.');
    }

    /**
     * Slugs are unique within an organization, as the database index is.
     */
    private function uniqueSlug(): Unique
    {
        return Rule::unique('categories', 'slug')->where('organization_id', auth()->user()->organization_id);
    }
}
