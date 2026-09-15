<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Lists, records and removes equipment, and finds equipment due for
 * maintenance. Queries run under the caller's organization scope.
 */
class EquipmentService
{
    /**
     * Equipment in name order. The search matches the name, the equipment
     * number or the serial number.
     *
     * @param  array{search?: mixed, status?: mixed, equipment_category_id?: mixed, functional_location_id?: mixed}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Equipment::query()
            ->with(['category', 'functionalLocation'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('equipment_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['equipment_category_id'] ?? null, fn ($query, $id) => $query->where('equipment_category_id', $id))
            ->when($filters['functional_location_id'] ?? null, fn ($query, $id) => $query->where('functional_location_id', $id))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data, int $userId): Equipment
    {
        return Equipment::create(array_merge($data, ['created_by' => $userId]))
            ->load(['category', 'functionalLocation']);
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);

        return $equipment->fresh();
    }

    /**
     * @throws BusinessRuleException when an open or in-progress order still needs the equipment
     */
    public function delete(Equipment $equipment): void
    {
        $hasOpenOrders = $equipment->maintenanceOrders()
            ->whereIn('status', [MaintenanceOrder::STATUS_OPEN, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->exists();

        if ($hasOpenOrders) {
            throw new BusinessRuleException('Cannot delete equipment with open or in-progress maintenance orders.', 'HAS_OPEN_ORDERS');
        }

        $equipment->delete();
    }

    /**
     * The organization's equipment whose next maintenance date falls within
     * the next $days days, soonest first.
     */
    public function dueWithin(int $organizationId, int $days): Collection
    {
        return Equipment::forOrganization($organizationId)
            ->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', now()->addDays($days)->toDateString())
            ->with(['category', 'functionalLocation'])
            ->orderBy('next_maintenance_date')
            ->get();
    }
}
