<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\SupplierDeliveryRecordResource;
use App\Http\Resources\Purchase\SupplierIncidentResource;
use App\Http\Resources\Purchase\SupplierScorecardResource;
use App\Services\Purchase\SupplierPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPerformanceController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly SupplierPerformanceService $performanceService
    ) {}

    // -------------------------------------------------------------------------
    // Evaluation Criteria
    // -------------------------------------------------------------------------

    public function indexCriteria(Request $request): JsonResponse
    {
        $criteria = $this->performanceService->listCriteria(
            ['category' => $request->category, 'active_only' => $request->boolean('active_only')],
            $request->integer('per_page', 50),
        );

        return $this->paginated($criteria, null);
    }

    public function storeCriteria(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:quality,delivery,price,service,compliance',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $criteria = $this->performanceService->createCriteria(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created($criteria, 'Evaluation criteria created.');
    }

    public function updateCriteria(Request $request, int $id): JsonResponse
    {
        $criteria = $this->performanceService->findCriteria($id);

        if ($criteria === null) {
            return $this->notFound('Criteria not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|in:quality,delivery,price,service,compliance',
            'weight_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $updated = $this->performanceService->updateCriteria($criteria, $validated, (int) auth()->id());

        return $this->success($updated, 'Evaluation criteria updated.');
    }

    public function destroyCriteria(int $id): JsonResponse
    {
        $criteria = $this->performanceService->findCriteria($id);

        if ($criteria === null) {
            return $this->notFound('Criteria not found.');
        }

        $this->performanceService->deleteCriteria($criteria);

        return $this->success(null, 'Evaluation criteria deleted.');
    }

    // -------------------------------------------------------------------------
    // Scorecards
    // -------------------------------------------------------------------------

    public function indexScorecards(Request $request): JsonResponse
    {
        $scorecards = $this->performanceService->listScorecards(
            $request->only(['supplier_id', 'status', 'from', 'to']),
            $request->integer('per_page', 15),
        );

        return $this->paginated($scorecards, SupplierScorecardResource::class);
    }

    public function storeScorecard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', $this->ownedBy('contacts')],
            'evaluation_period_start' => 'required|date',
            'evaluation_period_end' => 'required|date|after_or_equal:evaluation_period_start',
            'notes' => 'nullable|string',
            'ratings' => 'required|array|min:1',
            'ratings.*.criterion_id' => ['required', 'integer', $this->ownedBy('supplier_evaluation_criteria')],
            'ratings.*.score' => 'required|numeric|min:0|max:100',
            'ratings.*.comments' => 'nullable|string',
        ]);

        $scorecard = $this->performanceService->createScorecard(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created(new SupplierScorecardResource($scorecard), 'Scorecard created.');
    }

    public function showScorecard(int $id): JsonResponse
    {
        $scorecard = $this->performanceService->findScorecard($id, ['supplier', 'evaluator', 'ratings.criterion']);

        if ($scorecard === null) {
            return $this->notFound('Scorecard not found.');
        }

        return $this->success(new SupplierScorecardResource($scorecard));
    }

    public function updateScorecard(Request $request, int $id): JsonResponse
    {
        $scorecard = $this->performanceService->findScorecard($id);

        if ($scorecard === null) {
            return $this->notFound('Scorecard not found.');
        }

        // Refused before validation, as before; the service checks again on the locked row.
        if (! $scorecard->isDraft()) {
            return $this->error('Only draft scorecards can be updated.', 'SCORECARD_NOT_EDITABLE', 422);
        }

        $validated = $request->validate([
            'supplier_id' => ['sometimes', 'integer', $this->ownedBy('contacts')],
            'evaluation_period_start' => 'sometimes|date',
            'evaluation_period_end' => 'sometimes|date|after_or_equal:evaluation_period_start',
            'notes' => 'nullable|string',
            'ratings' => 'sometimes|array|min:1',
            'ratings.*.criterion_id' => ['required_with:ratings', 'integer', $this->ownedBy('supplier_evaluation_criteria')],
            'ratings.*.score' => 'required_with:ratings|numeric|min:0|max:100',
            'ratings.*.comments' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn () => new SupplierScorecardResource(
                $this->performanceService->updateScorecard($scorecard, $validated, (int) auth()->id())
            ),
            'Scorecard updated.',
            'SCORECARD_NOT_EDITABLE'
        );
    }

    public function finalizeScorecard(int $id): JsonResponse
    {
        $scorecard = $this->performanceService->findScorecard($id);

        if ($scorecard === null) {
            return $this->notFound('Scorecard not found.');
        }

        return $this->tryAction(
            fn () => new SupplierScorecardResource(
                $this->performanceService->finalizeScorecard($scorecard, (int) auth()->id())
            ),
            'Scorecard finalized.',
            'SCORECARD_ALREADY_FINALIZED'
        );
    }

    // -------------------------------------------------------------------------
    // Delivery Records
    // -------------------------------------------------------------------------

    public function indexDeliveryRecords(Request $request): JsonResponse
    {
        $records = $this->performanceService->listDeliveryRecords(
            $request->only(['supplier_id', 'from', 'to', 'is_on_time']),
            $request->integer('per_page', 15),
        );

        return $this->paginated($records, SupplierDeliveryRecordResource::class);
    }

    public function storeDeliveryRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', $this->ownedBy('purchase_orders')],
            'supplier_id' => ['required', 'integer', $this->ownedBy('contacts')],
            'promised_date' => 'required|date',
            'actual_date' => 'nullable|date',
            'quantity_ordered' => 'required|numeric|min:0',
            'quantity_received' => 'nullable|numeric|min:0',
            'quality_accepted' => 'nullable|boolean',
            'defect_quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $record = $this->performanceService->recordDelivery(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created(new SupplierDeliveryRecordResource($record), 'Delivery record created.');
    }

    // -------------------------------------------------------------------------
    // Incidents
    // -------------------------------------------------------------------------

    public function indexIncidents(Request $request): JsonResponse
    {
        $incidents = $this->performanceService->listIncidents(
            array_merge(
                $request->only(['supplier_id', 'severity', 'incident_type', 'from', 'to']),
                ['open_only' => $request->boolean('open_only')],
            ),
            $request->integer('per_page', 15),
        );

        return $this->paginated($incidents, SupplierIncidentResource::class);
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', $this->ownedBy('contacts')],
            'incident_type' => 'required|in:late_delivery,quality_issue,pricing_dispute,compliance_breach,communication',
            'severity' => 'required|in:low,medium,high,critical',
            'description' => 'required|string',
            'occurred_at' => 'required|date',
        ]);

        $incident = $this->performanceService->createIncident(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created(new SupplierIncidentResource($incident), 'Incident recorded.');
    }

    public function resolveIncident(Request $request, int $id): JsonResponse
    {
        $incident = $this->performanceService->findIncident($id);

        if ($incident === null) {
            return $this->notFound('Incident not found.');
        }

        // Refused before validation, as before; the service checks again on the locked row.
        if ($incident->isResolved()) {
            return $this->error('Incident is already resolved.', 'INCIDENT_ALREADY_RESOLVED', 422);
        }

        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        return $this->tryAction(
            fn () => new SupplierIncidentResource(
                $this->performanceService->resolveIncident($incident, $validated['resolution_notes'], (int) auth()->id())
            ),
            'Incident resolved.',
            'INCIDENT_ALREADY_RESOLVED'
        );
    }

    // -------------------------------------------------------------------------
    // Analytics
    // -------------------------------------------------------------------------

    public function supplierStats(Request $request, int $supplierId): JsonResponse
    {
        $stats = $this->performanceService->getSupplierStats(
            (int) auth()->user()->organization_id,
            $supplierId
        );

        return $this->success($stats);
    }

    public function supplierRanking(Request $request): JsonResponse
    {
        $ranking = $this->performanceService->getSupplierRanking(
            (int) auth()->user()->organization_id
        );

        return $this->success($ranking);
    }
}
