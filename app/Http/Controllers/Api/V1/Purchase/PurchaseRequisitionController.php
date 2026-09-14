<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\PurchaseOrderResource;
use App\Http\Resources\Purchase\PurchaseRequisitionResource;
use App\Models\Purchase\PurchaseRequisition;
use App\Services\Purchase\PurchaseRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequisitionController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private PurchaseRequisitionService $service
    ) {}

    /**
     * List purchase requisitions with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $requisitions = $this->service->index($request->only([
            'status',
            'requested_by',
            'requisition_type',
            'start_date',
            'end_date',
            'search',
            'per_page',
        ]));

        return $this->paginated($requisitions, PurchaseRequisitionResource::class);
    }

    /**
     * Create a new purchase requisition.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requisition_date' => 'required|date',
            'required_by_date' => 'nullable|date|after_or_equal:requisition_date',
            'requisition_type' => 'nullable|in:standard,subcontracting,consignment,stock_transfer',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['required', $this->ownedBy('products')],
            'lines.*.variant_id' => ['nullable', $this->ownedBy('product_variants')],
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.uom_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'lines.*.estimated_unit_price' => 'nullable|numeric|min:0',
            'lines.*.preferred_vendor_id' => ['nullable', $this->ownedBy('contacts')],
            'lines.*.warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'lines.*.required_by_date' => 'nullable|date',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            $requisition = $this->service->store($validated);
        } catch (\Exception $e) {
            report($e);
            return $this->error('Failed to create purchase requisition.', 'SERVER_ERROR', 500);
        }

        return $this->created(
            new PurchaseRequisitionResource($requisition),
            'Purchase requisition created successfully.'
        );
    }

    /**
     * Show a specific purchase requisition.
     */
    public function show(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        return $this->success(
            new PurchaseRequisitionResource(
                $purchaseRequisition->load(['lines.product', 'lines.variant', 'lines.preferredVendor', 'requester', 'approver'])
            )
        );
    }

    /**
     * Update a draft purchase requisition.
     */
    public function update(Request $request, PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        $validated = $request->validate([
            'requisition_date' => 'sometimes|date',
            'required_by_date' => 'nullable|date',
            'requisition_type' => 'nullable|in:standard,subcontracting,consignment,stock_transfer',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->tryAction(
            fn () => new PurchaseRequisitionResource($this->service->update($purchaseRequisition, $validated)),
            'Purchase requisition updated successfully.'
        );
    }

    /**
     * Delete a draft purchase requisition.
     */
    public function destroy(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->service->delete($purchaseRequisition),
            'Purchase requisition deleted successfully.'
        );
    }

    /**
     * Submit requisition for approval.
     */
    public function submit(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        return $this->tryAction(
            fn() => new PurchaseRequisitionResource($this->service->submit($purchaseRequisition)),
            'Purchase requisition submitted for approval.'
        );
    }

    /**
     * Approve a pending requisition.
     */
    public function approve(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        return $this->tryAction(
            fn() => new PurchaseRequisitionResource($this->service->approve($purchaseRequisition)),
            'Purchase requisition approved.'
        );
    }

    /**
     * Convert approved requisition to purchase order(s).
     */
    public function convertToPO(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        try {
            $orders = $this->service->convertToPurchaseOrder($purchaseRequisition);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('Failed to convert requisition to purchase order.', 'SERVER_ERROR', 500);
        }

        return $this->success(
            PurchaseOrderResource::collection($orders),
            'Purchase order(s) created from requisition.'
        );
    }

    /**
     * Cancel a purchase requisition.
     */
    public function cancel(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        return $this->tryAction(
            fn() => new PurchaseRequisitionResource($this->service->cancel($purchaseRequisition)),
            'Purchase requisition cancelled.'
        );
    }
}
