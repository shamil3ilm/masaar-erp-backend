<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\SupplierDeliveryRecord;
use App\Models\Purchase\SupplierEvaluationCriteria;
use App\Models\Purchase\SupplierIncident;
use App\Models\Purchase\SupplierScorecard;
use App\Models\Purchase\SupplierScorecardRating;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceService
{
    // -------------------------------------------------------------------------
    // Evaluation Criteria
    // -------------------------------------------------------------------------

    /**
     * A page of evaluation criteria ordered by category and name.
     *
     * @param  array{category?: string|null, active_only?: bool}  $filters
     */
    public function listCriteria(array $filters, int $perPage): LengthAwarePaginator
    {
        return SupplierEvaluationCriteria::query()
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->forCategory($category))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->orderBy('category')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findCriteria(int $id): ?SupplierEvaluationCriteria
    {
        return SupplierEvaluationCriteria::find($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCriteria(int $orgId, array $data, int $userId): SupplierEvaluationCriteria
    {
        return SupplierEvaluationCriteria::create(array_merge(
            $data,
            ['organization_id' => $orgId]
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCriteria(SupplierEvaluationCriteria $criteria, array $data, int $userId): SupplierEvaluationCriteria
    {
        $criteria->update($data);

        return $criteria->fresh();
    }

    public function deleteCriteria(SupplierEvaluationCriteria $criteria): void
    {
        $criteria->delete();
    }

    // -------------------------------------------------------------------------
    // Scorecards
    // -------------------------------------------------------------------------

    /**
     * A page of scorecards, latest period first, with supplier and evaluator loaded.
     *
     * @param  array<string, mixed>  $filters  supplier_id, status, from, to
     */
    public function listScorecards(array $filters, int $perPage): LengthAwarePaginator
    {
        return SupplierScorecard::with(['supplier', 'evaluator'])
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('evaluation_period_start', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('evaluation_period_end', '<=', $date))
            ->orderBy('evaluation_period_start', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $with
     */
    public function findScorecard(int $id, array $with = []): ?SupplierScorecard
    {
        return SupplierScorecard::with($with)->find($id);
    }

    /**
     * Create a scorecard with optional ratings.
     *
     * @param array<string, mixed> $data
     */
    public function createScorecard(int $orgId, array $data, int $userId): SupplierScorecard
    {
        return DB::transaction(function () use ($orgId, $data, $userId): SupplierScorecard {
            $scorecard = SupplierScorecard::create([
                'organization_id'         => $orgId,
                'supplier_id'             => $data['supplier_id'],
                'evaluation_period_start' => $data['evaluation_period_start'],
                'evaluation_period_end'   => $data['evaluation_period_end'],
                'notes'                   => $data['notes'] ?? null,
                'status'                  => SupplierScorecard::STATUS_DRAFT,
                'created_by'              => $userId,
            ]);

            foreach ($data['ratings'] ?? [] as $rating) {
                SupplierScorecardRating::create([
                    'scorecard_id' => $scorecard->id,
                    'criterion_id' => $rating['criterion_id'],
                    'score'        => $rating['score'],
                    'comments'     => $rating['comments'] ?? null,
                ]);
            }

            return $scorecard->load(['ratings.criterion', 'supplier']);
        });
    }

    /**
     * Update a draft scorecard and replace its ratings when new ones are given.
     *
     * The draft status is checked on the locked scorecard, so one finalized
     * meanwhile is not changed.
     *
     * @param array<string, mixed> $data
     */
    public function updateScorecard(SupplierScorecard $scorecard, array $data, int $userId): SupplierScorecard
    {
        return $scorecard->lockForTransition(function (SupplierScorecard $scorecard) use ($data): SupplierScorecard {
            if (! $scorecard->isDraft()) {
                throw new \InvalidArgumentException('Only draft scorecards can be updated.');
            }

            $fields = array_filter([
                'supplier_id'             => $data['supplier_id'] ?? null,
                'evaluation_period_start' => $data['evaluation_period_start'] ?? null,
                'evaluation_period_end'   => $data['evaluation_period_end'] ?? null,
                'notes'                   => $data['notes'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($fields)) {
                $scorecard->update($fields);
            }

            if (!empty($data['ratings'])) {
                $scorecard->ratings()->delete();

                foreach ($data['ratings'] as $rating) {
                    SupplierScorecardRating::create([
                        'scorecard_id' => $scorecard->id,
                        'criterion_id' => $rating['criterion_id'],
                        'score'        => $rating['score'],
                        'comments'     => $rating['comments'] ?? null,
                    ]);
                }
            }

            return $scorecard->fresh(['ratings.criterion', 'supplier']);
        });
    }

    /**
     * Finalize a scorecard: calculate per-category and overall scores.
     *
     * The status is checked on the locked scorecard: finalizing a stale copy
     * would recalculate a finalized scorecard and replace its evaluator.
     */
    public function finalizeScorecard(SupplierScorecard $scorecard, int $userId): SupplierScorecard
    {
        return $scorecard->lockForTransition(function (SupplierScorecard $scorecard) use ($userId): SupplierScorecard {
            if ($scorecard->isFinalized()) {
                throw new \InvalidArgumentException('Scorecard is already finalized.');
            }

            return $scorecard->finalize($userId);
        });
    }

    // -------------------------------------------------------------------------
    // Delivery Records
    // -------------------------------------------------------------------------

    /**
     * A page of delivery records, latest promised date first.
     *
     * @param  array<string, mixed>  $filters  supplier_id, from, to, is_on_time (applied when present)
     */
    public function listDeliveryRecords(array $filters, int $perPage): LengthAwarePaginator
    {
        return SupplierDeliveryRecord::with(['supplier', 'purchaseOrder'])
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('promised_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('promised_date', '<=', $date))
            ->when(
                array_key_exists('is_on_time', $filters),
                fn ($q) => $q->where('is_on_time', filter_var($filters['is_on_time'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('promised_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Record a delivery event, auto-calculating on-time and completeness flags.
     *
     * @param array<string, mixed> $data
     */
    public function recordDelivery(int $orgId, array $data, int $userId): SupplierDeliveryRecord
    {
        return DB::transaction(function () use ($orgId, $data): SupplierDeliveryRecord {
            $promisedDate = $data['promised_date'];
            $actualDate   = $data['actual_date'] ?? null;
            $qtyOrdered   = (float) $data['quantity_ordered'];
            $qtyReceived  = (float) ($data['quantity_received'] ?? 0);
            $defectQty    = (float) ($data['defect_quantity'] ?? 0);

            $isOnTime   = $actualDate !== null ? ($actualDate <= $promisedDate) : null;
            $isComplete = $qtyOrdered > 0 ? ($qtyReceived >= $qtyOrdered) : null;

            return SupplierDeliveryRecord::create([
                'organization_id'   => $orgId,
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id'       => $data['supplier_id'],
                'promised_date'     => $promisedDate,
                'actual_date'       => $actualDate,
                'quantity_ordered'  => $qtyOrdered,
                'quantity_received' => $qtyReceived,
                'is_on_time'        => $isOnTime,
                'is_complete'       => $isComplete,
                'quality_accepted'  => $data['quality_accepted'] ?? null,
                'defect_quantity'   => $defectQty,
                'notes'             => $data['notes'] ?? null,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Incidents
    // -------------------------------------------------------------------------

    /**
     * A page of incidents, latest first, with supplier and creator loaded.
     *
     * @param  array<string, mixed>  $filters  supplier_id, severity, incident_type, open_only, from, to
     */
    public function listIncidents(array $filters, int $perPage): LengthAwarePaginator
    {
        return SupplierIncident::with(['supplier', 'createdBy'])
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->forSupplier((int) $id))
            ->when($filters['severity'] ?? null, fn ($q, $severity) => $q->ofSeverity($severity))
            ->when($filters['incident_type'] ?? null, fn ($q, $type) => $q->where('incident_type', $type))
            ->when($filters['open_only'] ?? false, fn ($q) => $q->open())
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('occurred_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('occurred_at', '<=', $date))
            ->orderBy('occurred_at', 'desc')
            ->paginate($perPage);
    }

    public function findIncident(int $id): ?SupplierIncident
    {
        return SupplierIncident::find($id);
    }

    /**
     * Record a new supplier incident.
     *
     * @param array<string, mixed> $data
     */
    public function createIncident(int $orgId, array $data, int $userId): SupplierIncident
    {
        return DB::transaction(function () use ($orgId, $data, $userId): SupplierIncident {
            return SupplierIncident::create([
                'organization_id' => $orgId,
                'supplier_id'     => $data['supplier_id'],
                'incident_type'   => $data['incident_type'],
                'severity'        => $data['severity'],
                'description'     => $data['description'],
                'occurred_at'     => $data['occurred_at'],
                'created_by'      => $userId,
            ]);
        });
    }

    /**
     * Resolve an open incident, checked on the locked row so a resolution is
     * not overwritten by a second one.
     */
    public function resolveIncident(
        SupplierIncident $incident,
        string $resolutionNotes,
        int $userId
    ): SupplierIncident {
        return $incident->lockForTransition(function (SupplierIncident $incident) use ($resolutionNotes): SupplierIncident {
            if ($incident->isResolved()) {
                throw new \InvalidArgumentException('Incident is already resolved.');
            }

            $incident->update([
                'resolved_at'      => now()->toDateString(),
                'resolution_notes' => $resolutionNotes,
            ]);

            return $incident->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Analytics
    // -------------------------------------------------------------------------

    /**
     * Compute stats for a supplier within an organisation.
     *
     * @return array<string, mixed>
     */
    public function getSupplierStats(int $orgId, int $supplierId): array
    {
        $deliveries = SupplierDeliveryRecord::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('supplier_id', $supplierId)
            ->get();

        $totalOrders   = $deliveries->count();
        $onTimeCount   = $deliveries->where('is_on_time', true)->count();
        $completeCount = $deliveries->where('is_complete', true)->count();
        $totalReceived = (float) $deliveries->sum('quantity_received');
        $totalDefect   = (float) $deliveries->sum('defect_quantity');

        $openIncidents = SupplierIncident::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('supplier_id', $supplierId)
            ->whereNull('resolved_at')
            ->count();

        $avgScore = SupplierScorecard::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('supplier_id', $supplierId)
            ->where('status', SupplierScorecard::STATUS_FINALIZED)
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        $onTimeRate = $totalOrders > 0
            ? (float) bcmul(bcdiv((string) $onTimeCount, (string) $totalOrders, 6), '100', 2)
            : null;

        $completeRate = $totalOrders > 0
            ? (float) bcmul(bcdiv((string) $completeCount, (string) $totalOrders, 6), '100', 2)
            : null;

        $defectRate = $totalReceived > 0
            ? (float) bcmul(bcdiv((string) $totalDefect, (string) $totalReceived, 6), '100', 2)
            : null;

        return [
            'supplier_id'    => $supplierId,
            'total_orders'   => $totalOrders,
            'on_time_rate'   => $onTimeRate,
            'complete_rate'  => $completeRate,
            'defect_rate'    => $defectRate,
            'open_incidents' => $openIncidents,
            'avg_score'      => $avgScore !== null ? round((float) $avgScore, 2) : null,
        ];
    }

    /**
     * Rank all suppliers in an org by avg overall_score from finalized scorecards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSupplierRanking(int $orgId): array
    {
        $rows = SupplierScorecard::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', SupplierScorecard::STATUS_FINALIZED)
            ->whereNotNull('overall_score')
            ->selectRaw('supplier_id, AVG(overall_score) as avg_score, COUNT(*) as scorecard_count')
            ->groupBy('supplier_id')
            ->orderByDesc('avg_score')
            ->get();

        return $rows->values()->map(function ($row, int $idx): array {
            return [
                'rank'            => $idx + 1,
                'supplier_id'     => $row->supplier_id,
                'avg_score'       => round((float) $row->avg_score, 2),
                'scorecard_count' => (int) $row->scorecard_count,
            ];
        })->all();
    }
}
