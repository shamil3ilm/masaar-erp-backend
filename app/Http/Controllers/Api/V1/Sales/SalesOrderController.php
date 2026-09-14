<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesOrder;
use App\Services\Accounting\CreditManagementService;
use App\Services\Sales\InvoiceConversionService;
use App\Services\Sales\SalesOrderDeliveryService;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly CreditManagementService $creditService,
        private readonly SalesOrderService $salesOrderService,
        private readonly InvoiceConversionService $invoiceConversion,
        private readonly SalesOrderDeliveryService $deliveryService,
    ) {}

    /**
     * List sales orders with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $salesOrders = $this->salesOrderService->list([
            'customer_id' => $request->customer_id,
            'status' => $request->status,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'salesperson_id' => $request->salesperson_id,
            'warehouse_id' => $request->warehouse_id,
            'search' => $request->search,
        ], $request->integer('per_page', 15));

        return $this->paginated($salesOrders);
    }

    /**
     * Create a new sales order.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'delivery_instructions' => 'nullable|string|max:2000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $orgId)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('organization_id', $orgId)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')->where('organization_id', $orgId)],
            'lines.*.warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
        ]);

        $user = $request->user();
        $branchId = $validated['branch_id']
            ?? $request->attributes->get('branch')?->id
            ?? $user->getDefaultBranch()?->id;

        $salesOrder = $this->salesOrderService->create($validated, $user, $branchId);

        return $this->created($salesOrder, 'Sales order created successfully.');
    }

    /**
     * Show a sales order with lines.
     */
    public function show(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder = $this->salesOrderService->loadDetails($salesOrder);

        $data = $salesOrder->toArray();
        $data['fulfillment_progress'] = $salesOrder->getFulfillmentProgress();

        return $this->success($data);
    }

    /**
     * Update a draft sales order.
     */
    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        try {
            $this->salesOrderService->assertDraft($salesOrder, 'updated');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'order_date' => 'sometimes|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency_code' => 'sometimes|string|size:3',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'delivery_instructions' => 'nullable|string|max:2000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $orgId)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('organization_id', $orgId)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')->where('organization_id', $orgId)],
            'lines.*.warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
        ]);

        return $this->tryAction(
            fn () => $this->salesOrderService->update($salesOrder, $validated),
            'Sales order updated successfully.'
        );
    }

    /**
     * Delete a draft sales order.
     */
    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->salesOrderService->delete($salesOrder),
            'Sales order deleted successfully.'
        );
    }

    /**
     * Confirm a draft sales order.
     */
    public function confirm(SalesOrder $salesOrder): JsonResponse
    {
        try {
            $this->salesOrderService->assertConfirmable($salesOrder);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        // SAP SD credit check at order confirmation (VKM1/VKM3 equivalent).
        // We check here (not just at invoicing) so sales reps get early warning.
        $customer = $salesOrder->customer;
        $orderAmount = (float) $salesOrder->total;

        if ($customer && ! $this->creditService->checkCreditLimit($customer, $orderAmount)) {
            return $this->error(
                "Customer '{$customer->getDisplayName()}' has exceeded their credit limit. Release the credit hold or obtain approval before confirming.",
                'CREDIT_LIMIT_EXCEEDED',
                422
            );
        }

        return $this->tryAction(
            fn () => $this->salesOrderService->confirm($salesOrder),
            'Sales order confirmed successfully.'
        );
    }

    /**
     * Return the real-time credit exposure for the customer of this order.
     * Equivalent to SAP SD credit check (FD32 / VKM1 pre-check).
     *
     * GET /sales-orders/{salesOrder}/credit-check
     */
    public function creditCheck(SalesOrder $salesOrder): JsonResponse
    {
        $customer = $salesOrder->customer;

        if (! $customer) {
            return $this->error('Order has no customer assigned.', 'NO_CUSTOMER', 422);
        }

        $exposure = $this->creditService->getCreditExposure($customer);
        $orderAmount = (float) $salesOrder->total;
        $available = (float) $exposure['available_credit'];

        return $this->success([
            'customer_id' => $customer->id,
            'customer_name' => $customer->getDisplayName(),
            'credit_limit' => (float) $exposure['credit_limit'],
            'current_exposure' => (float) $exposure['total_exposure'],
            'available_credit' => $available,
            'utilization_pct' => (float) $exposure['utilization_pct'],
            'order_amount' => $orderAmount,
            'will_exceed' => ($available - $orderAmount) < 0,
            'on_credit_hold' => $this->creditService->hasOpenHold($customer),
            'currency_code' => $exposure['currency_code'],
        ], 'Credit check completed.');
    }

    /**
     * Cancel a sales order.
     */
    public function cancel(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->salesOrderService->cancel($salesOrder),
            'Sales order cancelled successfully.'
        );
    }

    /**
     * Convert a sales order to an invoice.
     */
    public function convertToInvoice(SalesOrder $salesOrder): JsonResponse
    {
        if (! $salesOrder->canBeInvoiced()) {
            return $this->error(
                'Sales order cannot be invoiced in its current status. Order must be partially delivered or delivered.',
                'VALIDATION_ERROR',
                422
            );
        }

        $invoice = $this->invoiceConversion->createFromSalesOrder($salesOrder);

        $result = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ];

        return $this->created($result, 'Invoice created from sales order successfully.');
    }

    /**
     * Create a delivery goods issue for a confirmed sales order.
     *
     * POST /api/v1/sales/sales-orders/{salesOrder}/create-delivery
     */
    public function createDelivery(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $request->user()->organization_id)],
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => ['integer'],
        ]);

        try {
            $gi = $this->deliveryService->createDeliveryGoodsIssue(
                order: $salesOrder,
                warehouseId: (int) $validated['warehouse_id'],
                userId: auth()->id(),
                lineIds: $validated['line_ids'] ?? null,
            );

            return $this->created($gi, 'Delivery goods issue created and posted.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'DELIVERY_FAILED', 422);
        }
    }
}
