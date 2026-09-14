<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Designation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Designations of the current organization.
 */
class DesignationService
{
    /**
     * Designations with their active headcount.
     *
     * @param  array{search?: mixed, is_active?: ?bool, level?: mixed}  $filters
     *         is_active applies when it is not null; other empty values are ignored
     * @param  string  $sortBy  a column the caller has already checked against its allowlist
     */
    public function list(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return Designation::query()
            ->withCount('activeEmployees')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['level'] ?? null, fn ($q, $level) => $q->byLevel((int) $level))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    public function create(array $data, int $organizationId): Designation
    {
        return Designation::create(['organization_id' => $organizationId, ...$data]);
    }

    public function update(Designation $designation, array $data): Designation
    {
        $designation->update($data);

        return $designation;
    }

    /**
     * Deletes a designation no employee holds.
     *
     * @throws InvalidArgumentException when an employee holds it
     */
    public function delete(Designation $designation): void
    {
        if ($designation->employees()->count() > 0) {
            throw new InvalidArgumentException('Cannot delete designation with assigned employees. Reassign employees first.');
        }

        $designation->delete();
    }
}
