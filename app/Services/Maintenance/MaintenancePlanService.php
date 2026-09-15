<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\MaintenancePlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists, records and switches maintenance plans on and off. Generating an
 * order from a plan belongs to MaintenanceService, which numbers orders.
 * Queries run under the caller's organization scope.
 */
class MaintenancePlanService
{
    /**
     * Plans in name order. is_active filters only when it is given.
     *
     * @param  array{equipment_id?: mixed, is_active?: bool|null, maintenance_type?: mixed}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $isActive = $filters['is_active'] ?? null;

        return MaintenancePlan::query()
            ->with('equipment')
            ->when($filters['equipment_id'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when($filters['maintenance_type'] ?? null, fn ($query, $type) => $query->where('maintenance_type', $type))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data, int $userId): MaintenancePlan
    {
        return MaintenancePlan::create(array_merge($data, ['created_by' => $userId]))->load('equipment');
    }

    public function update(MaintenancePlan $plan, array $data): MaintenancePlan
    {
        $plan->update($data);

        return $plan->fresh();
    }

    public function toggleActive(MaintenancePlan $plan): MaintenancePlan
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return $plan->fresh();
    }
}
