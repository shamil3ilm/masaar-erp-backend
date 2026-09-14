<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Departments of the current organization.
 */
class DepartmentService
{
    /**
     * Departments with their parent, manager and active headcount.
     *
     * @param  array{search?: mixed, is_active?: ?bool, root_only?: bool, parent_id?: mixed}  $filters
     *         is_active applies when it is not null; other empty values are ignored
     * @param  string  $sortBy  a column the caller has already checked against its allowlist
     */
    public function list(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return Department::with(['parent', 'manager'])
            ->withCount('activeEmployees')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['root_only'] ?? false, fn ($q) => $q->root())
            ->when($filters['parent_id'] ?? null, fn ($q, $parentId) => $q->where('parent_id', $parentId))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    public function create(array $data, int $organizationId): Department
    {
        return Department::create(['organization_id' => $organizationId, ...$data])
            ->load(['parent', 'manager']);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->load(['parent', 'manager']);
    }

    /**
     * Deletes a department that has no employees and no sub-departments.
     *
     * @throws InvalidArgumentException naming what still depends on the department
     */
    public function delete(Department $department): void
    {
        if ($department->employees()->count() > 0) {
            throw new InvalidArgumentException('Cannot delete department with assigned employees. Reassign employees first.');
        }

        if ($department->children()->count() > 0) {
            throw new InvalidArgumentException('Cannot delete department with sub-departments. Remove or reassign sub-departments first.');
        }

        $department->delete();
    }
}
