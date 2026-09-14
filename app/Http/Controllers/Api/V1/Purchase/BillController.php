<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\BillResource;
use App\Models\Purchase\Bill;
use App\Services\Purchase\BillService;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private BillService $billService,
        private PurchaseOrderService $purchaseOrderService,
    ) {
    }

    /**
     * List bills with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $bills = $this->billService->list(
            $request->only(['status', 'supplier_id', 'bill_type', 'overdue', 'start_date', 'end_date', 'search']),
            $this->safeSortBy($request->sort_by, ['bill_number', 'bill_date', 'due_date', 'status', 'total', 'created_at', 'updated_at'], 'bill_date'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($bills, BillResource::class);
    }

    /**
     * Store a new bill.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', $this->ownedBy('contacts')],
            'purchase_order_id' => ['nullable', $this->ownedBy('purchase_orders')],
            'bill_number' => 'nullable|string|max:50',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'bill_type' => 'nullable|in:standard,debit_note,credit_note',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'received_date' => 'nullable|date',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'place_of_supply' => 'nullable|string|max:50',
            'is_reverse_charge' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            ...$this->lineRules(),
        ]);

        try {
            $bill = $this->billService->create(
                collect($validated)->except('lines')->toArray(),
                $validated['lines']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new BillResource($bill), 'Bill created successfully.');
    }

    /**
     * Show a specific bill.
     */
    public function show(Bill $bill): JsonResponse
    {
        return $this->success(new BillResource(
            $bill->load(['supplier', 'lines.product', 'lines.taxCategory', 'purchaseOrder', 'journalEntry.lines', 'paymentAllocations.payment'])
        ));
    }

    /**
     * Update a draft bill.
     */
    public function update(Request $request, Bill $bill): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['sometimes', 'nullable', $this->ownedBy('contacts')],
            'supplier_invoice_number' => 'nullable|string|max:100',
            'bill_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'received_date' => 'nullable|date',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'place_of_supply' => 'nullable|string|max:50',
            'is_reverse_charge' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'version' => 'sometimes|integer',
            'lines' => 'sometimes|array|min:1',
            ...$this->lineRules(),
        ]);

        try {
            $bill = $this->billService->update(
                $bill,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new BillResource($bill), 'Bill updated successfully.');
    }

    /**
     * Delete a draft bill.
     */
    public function destroy(Bill $bill): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->billService->delete($bill),
            'Bill deleted successfully.',
        );
    }

    /**
     * Approve a bill.
     */
    public function approve(Bill $bill): JsonResponse
    {
        return $this->tryAction(
            fn() => new BillResource($this->billService->approve($bill, auth()->id())),
            'Bill approved successfully.',
        );
    }

    /**
     * Void a bill.
     */
    public function void(Request $request, Bill $bill): JsonResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);

        return $this->tryAction(
            fn() => new BillResource($this->billService->void($bill, $validated['reason'] ?? '')),
            'Bill voided successfully.',
        );
    }

    /**
     * Create bill from purchase order.
     */
    public function createFromPurchaseOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', $this->ownedBy('purchase_orders')],
            'line_quantities' => 'nullable|array',
            'line_quantities.*' => 'numeric|min:0',
        ]);

        $order = $this->purchaseOrderService->find((int) $validated['purchase_order_id']);

        try {
            $bill = $this->billService->createFromPurchaseOrder(
                $order,
                $validated['line_quantities'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new BillResource($bill), 'Bill created from purchase order successfully.');
    }

    /**
     * Get bills summary/stats.
     */
    public function summary(Request $request): JsonResponse
    {
        return $this->success(
            $this->billService->summary($request->supplier_id ? (int) $request->supplier_id : null)
        );
    }

    /**
     * Validation for bill lines; every referenced row must belong to the caller's organization.
     *
     * @return array<string, mixed>
     */
    private function lineRules(): array
    {
        return [
            'lines.*.product_id' => ['nullable', $this->ownedBy('products')],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
            'lines.*.tax_category_id' => ['nullable', $this->ownedBy('tax_categories')],
            'lines.*.account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'lines.*.warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
        ];
    }
}
