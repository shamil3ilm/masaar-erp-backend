<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\SupportsAgGrid;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\PurchaseOrderResource;
use App\Models\Purchase\PurchaseOrder;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    use SupportsAgGrid;
    use ValidatesOwnedRows;

    public function __construct(
        private PurchaseOrderService $purchaseOrderService
    ) {
    }

    /**
     * List purchase orders with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->purchaseOrderService->listQuery(
            $request->only(['status', 'supplier_id', 'pending_receipt', 'start_date', 'end_date', 'search']),
            $this->safeSortBy($request->sort_by, ['po_number', 'order_date', 'expected_delivery_date', 'status', 'total', 'created_at', 'updated_at'], 'order_date'),
            $this->safeSortOrder($request->sort_order, 'desc'),
        );

        if ($this->isAgGridRequest($request)) {
            return $this->applyAgGrid($query, $request);
        }

        $orders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($orders, PurchaseOrderResource::class);
    }

    /**
     * Store a new purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', $this->ownedBy('contacts')],
            'order_number' => 'nullable|string|max:50',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'delivery_address' => 'nullable|string|max:500',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            ...$this->lineRules(),
        ]);

        try {
            $order = $this->purchaseOrderService->create(
                collect($validated)->except('lines')->toArray(),
                $validated['lines']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new PurchaseOrderResource($order), 'Purchase order created successfully.');
    }

    /**
     * Show a specific purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->success(new PurchaseOrderResource(
            $purchaseOrder->load(['supplier', 'warehouse', 'lines.product', 'lines.variant', 'lines.taxCategory', 'bills'])
        ));
    }

    /**
     * Update a draft purchase order.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'order_date' => 'sometimes|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'delivery_address' => 'nullable|string|max:500',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'reference' => 'nullable|string|max:100',
            'version' => 'sometimes|integer',
            'lines' => 'sometimes|array|min:1',
            ...$this->lineRules(),
        ]);

        try {
            $order = $this->purchaseOrderService->update(
                $purchaseOrder,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PurchaseOrderResource($order), 'Purchase order updated successfully.');
    }

    /**
     * Delete a draft purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->purchaseOrderService->delete($purchaseOrder),
            'Purchase order deleted successfully.',
        );
    }

    /**
     * Send purchase order to supplier.
     */
    public function send(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $order = $this->purchaseOrderService->send($purchaseOrder);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PurchaseOrderResource($order), 'Purchase order sent successfully.');
    }

    /**
     * Confirm a purchase order.
     */
    public function confirm(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $order = $this->purchaseOrderService->confirm($purchaseOrder, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PurchaseOrderResource($order), 'Purchase order confirmed successfully.');
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $order = $this->purchaseOrderService->cancel($purchaseOrder, $validated['reason'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PurchaseOrderResource($order), 'Purchase order cancelled successfully.');
    }

    /**
     * Receive items against purchase order.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'line_quantities' => 'nullable|array',
            'line_quantities.*' => 'numeric|min:0',
            'lines' => 'nullable|array',
            'lines.*.line_id' => 'required_with:lines|integer',
            'lines.*.quantity_received' => 'required_with:lines|numeric|min:0',
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
        ]);

        // Support both formats: line_quantities (flat) and lines (array of objects)
        $lineQuantities = $validated['line_quantities'] ?? [];
        if (empty($lineQuantities) && !empty($validated['lines'])) {
            foreach ($validated['lines'] as $line) {
                $lineQuantities[$line['line_id']] = $line['quantity_received'];
            }
        }

        if (empty($lineQuantities)) {
            return $this->error('Line quantities are required.', 'VALIDATION_ERROR', 422);
        }

        try {
            $order = $this->purchaseOrderService->receive(
                $purchaseOrder,
                $lineQuantities,
                $validated['warehouse_id'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PurchaseOrderResource($order), 'Items received successfully.');
    }

    /**
     * Duplicate a purchase order.
     */
    public function duplicate(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $newOrder = $this->purchaseOrderService->duplicate($purchaseOrder);

        return $this->created(new PurchaseOrderResource($newOrder), 'Purchase order duplicated successfully.');
    }

    /**
     * Get purchase orders summary/stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->purchaseOrderService->getSummary(
            $request->supplier_id ? (int) $request->supplier_id : null
        );

        return $this->success($summary);
    }

    /**
     * Approve or reject a PO pending approval.
     * POST /purchase-orders/{id}/review-approval  {"action": "approve"|"reject", "notes/reason": "..."}
     */
    public function reviewApproval(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes'  => 'nullable|string|max:1000',
            'reason' => 'required_if:action,reject|string|max:1000',
        ]);

        try {
            if ($validated['action'] === 'approve') {
                $order = $this->purchaseOrderService->approvePO(
                    $purchaseOrder,
                    auth()->id(),
                    $validated['notes'] ?? null
                );
                return $this->success(new PurchaseOrderResource($order), 'Purchase order approved successfully.');
            }

            $order = $this->purchaseOrderService->rejectPO(
                $purchaseOrder,
                auth()->id(),
                $validated['reason']
            );
            return $this->success(new PurchaseOrderResource($order), 'Purchase order approval rejected.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Validation for order lines; every referenced row must belong to the caller's organization.
     *
     * @return array<string, mixed>
     */
    private function lineRules(): array
    {
        return [
            'lines.*.product_id' => ['nullable', $this->ownedBy('products')],
            'lines.*.variant_id' => ['nullable', $this->ownedBy('product_variants')],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
            'lines.*.tax_category_id' => ['nullable', $this->ownedBy('tax_categories')],
            'lines.*.warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
        ];
    }
}
