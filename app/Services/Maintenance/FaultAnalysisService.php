<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\MaintenanceFaultCode;
use App\Models\Maintenance\MaintenanceRca;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fault codes and the root cause analyses recorded against maintenance orders.
 */
class FaultAnalysisService
{
    public function activeFaultCodes(int $organizationId): Collection
    {
        return MaintenanceFaultCode::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();
    }

    public function createFaultCode(int $organizationId, array $data): MaintenanceFaultCode
    {
        return MaintenanceFaultCode::create([
            ...$data,
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * The organization's analyses, newest first.
     *
     * @param  array{status?: mixed, equipment_id?: ?int}  $filters
     */
    public function paginateAnalyses(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return MaintenanceRca::query()
            ->where('organization_id', $organizationId)
            ->with(['faultCode', 'maintenanceOrder'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['equipment_id'] ?? null, fn ($query, $equipmentId) => $query->where('equipment_id', $equipmentId))
            ->latest()
            ->paginate($perPage);
    }

    public function createAnalysis(int $organizationId, array $data): MaintenanceRca
    {
        return MaintenanceRca::create([
            ...$data,
            'organization_id' => $organizationId,
        ])->load('faultCode');
    }

    /**
     * Update an analysis. Closing it stamps today's date when none is given.
     */
    public function updateAnalysis(MaintenanceRca $analysis, array $data): MaintenanceRca
    {
        if (($data['status'] ?? null) === MaintenanceRca::STATUS_CLOSED) {
            $data['closed_date'] ??= now()->toDateString();
        }

        $analysis->update($data);

        return $analysis;
    }
}
