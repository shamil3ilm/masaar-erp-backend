<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\MaintenanceServiceOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Service orders for maintenance done by external vendors, with their SLA
 * deadlines.
 */
class MaintenanceServiceOrderService
{
    /**
     * The organization's service orders, newest first.
     *
     * @param  array{status?: mixed, vendor_id?: ?int}  $filters
     */
    public function paginate(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return MaintenanceServiceOrder::query()
            ->where('organization_id', $organizationId)
            ->with(['equipment'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['vendor_id'] ?? null, fn ($query, $vendorId) => $query->where('vendor_id', $vendorId))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Draft a service order; its SLA deadlines run from now.
     */
    public function create(int $organizationId, int $userId, array $data): MaintenanceServiceOrder
    {
        return MaintenanceServiceOrder::create([
            ...$data,
            'organization_id' => $organizationId,
            'service_order_number' => 'SO-'.strtoupper(Str::random(8)),
            'status' => MaintenanceServiceOrder::STATUS_DRAFT,
            'sla_response_due_at' => isset($data['sla_response_hours'])
                ? now()->addHours((int) $data['sla_response_hours'])
                : null,
            'sla_resolution_due_at' => isset($data['sla_resolution_hours'])
                ? now()->addHours((int) $data['sla_resolution_hours'])
                : null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Update a service order. Completing it stamps today's date when none is
     * given; confirming it records when the vendor responded.
     */
    public function update(MaintenanceServiceOrder $serviceOrder, array $data): MaintenanceServiceOrder
    {
        $status = $data['status'] ?? null;

        if ($status === MaintenanceServiceOrder::STATUS_COMPLETED) {
            $data['completed_date'] ??= now()->toDateString();
        }

        if ($status === MaintenanceServiceOrder::STATUS_CONFIRMED) {
            $data['vendor_responded_at'] = now();
        }

        $serviceOrder->update($data);

        return $serviceOrder;
    }

    public function delete(MaintenanceServiceOrder $serviceOrder): void
    {
        $serviceOrder->delete();
    }
}
