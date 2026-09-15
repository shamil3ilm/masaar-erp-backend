<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Manufacturing\ReturnsInspectionDefectResource;
use App\Http\Resources\Manufacturing\ReturnsInspectionLotResource;
use App\Models\Manufacturing\ReturnsInspectionDefect;
use App\Services\Manufacturing\ReturnsInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReturnsInspectionController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ReturnsInspectionService $service,
    ) {}

    /**
     * List returns inspection lots with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status',
            'return_type',
            'product_id',
            'warehouse_id',
            'search',
        ]);

        $lots = $this->service->list(
            $filters,
            $request->integer('per_page', 20)
        );

        return $this->paginated(ReturnsInspectionLotResource::collection($lots)->resource);
    }

    /**
     * Create a new returns inspection lot.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rma_request_id'     => ['nullable', $this->ownedBy('rma_requests')],
            'sales_return_id'    => ['nullable', $this->ownedBy('sales_returns')],
            'purchase_return_id' => ['nullable', $this->ownedBy('purchase_returns')],
            'product_id'         => ['required', $this->ownedBy('products')],
            'warehouse_id'       => ['nullable', $this->ownedBy('warehouses')],
            'return_type'        => 'nullable|in:customer_return,vendor_return,internal_return',
            'received_quantity'  => 'required|numeric|min:0.0001',
            'quality_plan_id'    => ['nullable', $this->ownedBy('quality_plans')],
        ]);

        $validated['created_by'] = auth()->id();

        $lot = $this->service->create($validated);

        return $this->created(new ReturnsInspectionLotResource($lot->load('product')));
    }

    /**
     * Show a single returns inspection lot.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $lot = $this->service->show($id);
        } catch (InvalidArgumentException) {
            return $this->notFound('Returns inspection lot not found.');
        }

        return $this->success(new ReturnsInspectionLotResource($lot));
    }

    /**
     * Transition the lot from open → in_inspection.
     */
    public function startInspection(string $id): JsonResponse
    {
        try {
            $lot = $this->service->show($id);
            $lot = $this->service->startInspection($lot);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new ReturnsInspectionLotResource($lot));
    }

    /**
     * Add a defect record to an inspection lot.
     */
    public function addDefect(Request $request, string $id): JsonResponse
    {
        try {
            $lot = $this->service->show($id);
        } catch (InvalidArgumentException) {
            return $this->notFound('Returns inspection lot not found.');
        }

        $validated = $request->validate([
            'defect_code'         => 'required|string|max:50',
            'defect_description'  => 'nullable|string',
            'severity'            => 'nullable|in:critical,major,minor,cosmetic',
            'quantity_affected'   => 'nullable|numeric|min:0',
            'recommended_action'  => 'nullable|in:scrap,return_to_vendor,rework,repack,accept',
            'actual_action_taken' => 'nullable|in:scrapped,returned_to_vendor,reworked,repacked,accepted',
            'notes'               => 'nullable|string',
        ]);

        $validated['recorded_by'] = auth()->id();

        $defect = $this->service->addDefect($lot, $validated);

        return $this->created(new ReturnsInspectionDefectResource($defect));
    }

    /**
     * Update an existing defect record.
     */
    public function updateDefect(Request $request, string $id, string $defectId): JsonResponse
    {
        $defect = $this->defectOf($id, $defectId);

        if ($defect === null) {
            return $this->notFound('Defect record not found.');
        }

        $validated = $request->validate([
            'defect_code'         => 'sometimes|required|string|max:50',
            'defect_description'  => 'nullable|string',
            'severity'            => 'nullable|in:critical,major,minor,cosmetic',
            'quantity_affected'   => 'nullable|numeric|min:0',
            'recommended_action'  => 'nullable|in:scrap,return_to_vendor,rework,repack,accept',
            'actual_action_taken' => 'nullable|in:scrapped,returned_to_vendor,reworked,repacked,accepted',
            'notes'               => 'nullable|string',
        ]);

        $defect = $this->service->updateDefect($defect, $validated);

        return $this->success(new ReturnsInspectionDefectResource($defect));
    }

    /**
     * Remove a defect record from an inspection lot.
     */
    public function removeDefect(string $id, string $defectId): JsonResponse
    {
        $defect = $this->defectOf($id, $defectId);

        if ($defect === null) {
            return $this->notFound('Defect record not found.');
        }

        $this->service->removeDefect($defect);

        return $this->success(null, 'Defect record removed successfully.');
    }

    /**
     * Record the usage decision for a lot.
     */
    public function makeUsageDecision(Request $request, string $id): JsonResponse
    {
        try {
            $lot = $this->service->show($id);
        } catch (InvalidArgumentException) {
            return $this->notFound('Returns inspection lot not found.');
        }

        $validated = $request->validate([
            'usage_decision'    => 'required|in:accept,reject,rework,partial_accept',
            'accepted_quantity' => 'required|numeric|min:0',
            'rejected_quantity' => 'required|numeric|min:0',
            'rework_quantity'   => 'required|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        $validated['user_id'] = auth()->id();

        try {
            $lot = $this->service->makeUsageDecision($lot, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new ReturnsInspectionLotResource($lot->load('defects')));
    }

    /**
     * Post stock movements and close the lot.
     */
    public function postStock(string $id): JsonResponse
    {
        try {
            $lot = $this->service->show($id);
            $lot = $this->service->postStockMovements($lot);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new ReturnsInspectionLotResource($lot));
    }

    /**
     * Cancel an open inspection lot.
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $lot = $this->service->show($id);
            $lot = $this->service->cancel($lot);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new ReturnsInspectionLotResource($lot));
    }

    /**
     * The defect of the organization's lot named in the URL, or null when
     * either is not found.
     */
    private function defectOf(string $id, string $defectId): ?ReturnsInspectionDefect
    {
        try {
            return $this->service->findDefect($this->service->show($id), $defectId);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
