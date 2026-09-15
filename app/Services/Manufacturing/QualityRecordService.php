<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\DefectRecord;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\QualityNotification;
use App\Models\Manufacturing\QualityPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists and lookups of the organization's quality plans, inspection lots and
 * quality notifications. Their workflows run in QualityManagementService.
 */
class QualityRecordService
{
    /** Columns a quality plan list may be sorted by. */
    public const PLAN_SORT_COLUMNS = ['name', 'inspection_stage', 'created_at', 'updated_at'];

    /** Columns an inspection lot list may be sorted by. */
    public const LOT_SORT_COLUMNS = ['lot_number', 'status', 'created_at', 'inspection_date'];

    /** Columns a quality notification list may be sorted by. */
    public const NOTIFICATION_SORT_COLUMNS = ['notification_number', 'priority', 'status', 'due_date', 'created_at'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePlans(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        $isActive = $filters['is_active'] ?? null;

        return QualityPlan::with(['product', 'productCategory'])
            ->withCount('characteristics')
            ->when($isActive !== null, fn ($q) => $q->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['inspection_stage'] ?? null, fn ($q, $stage) => $q->forStage($stage))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * One of the organization's quality plans, or null.
     *
     * @param  list<string>  $with
     */
    public function findPlan(int $id, array $with = []): ?QualityPlan
    {
        return QualityPlan::with($with)->find($id);
    }

    public function updatePlan(QualityPlan $plan, array $data): QualityPlan
    {
        $plan->update($data);

        return $plan->fresh(['characteristics', 'product']);
    }

    public function deletePlan(QualityPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateLots(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return InspectionLot::with(['product', 'warehouse', 'qualityPlan', 'inspector'])
            ->withCount('results')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['source_type'] ?? null, fn ($q, $type) => $q->where('source_type', $type))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('lot_number', 'like', "%{$search}%"))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * One of the organization's inspection lots, or null.
     *
     * @param  list<string>  $with
     */
    public function findLot(int $id, array $with = []): ?InspectionLot
    {
        return InspectionLot::with($with)->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateNotifications(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return QualityNotification::with(['product', 'assignee', 'creator'])
            ->withCount('defects')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['notification_type'] ?? null, fn ($q, $type) => $q->where('notification_type', $type))
            ->when($filters['assigned_to'] ?? null, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when(($filters['overdue'] ?? null) === 'true', fn ($q) => $q->overdue())
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('notification_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * One of the organization's quality notifications, or null.
     *
     * @param  list<string>  $with
     */
    public function findNotification(int $id, array $with = []): ?QualityNotification
    {
        return QualityNotification::with($with)->find($id);
    }

    /**
     * The notification's defect records, oldest first.
     *
     * @return Collection<int, DefectRecord>
     */
    public function defectsOf(QualityNotification $notification): Collection
    {
        return DefectRecord::where('quality_notification_id', $notification->id)
            ->orderBy('created_at')
            ->get();
    }
}
