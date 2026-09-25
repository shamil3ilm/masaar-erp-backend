<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\IntercompanyBillingDocumentResource;
use App\Http\Resources\Sales\IntercompanySalesOrderResource;
use App\Services\Sales\IntercompanySalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Intercompany sales orders. A user sees and acts on an order only when their
 * organization is its seller or its buyer.
 */
class IntercompanySalesController extends Controller
{
    use ReportsBusinessRules;
    use ValidatesOwnedRows;

    public function __construct(
        private IntercompanySalesService $service
    ) {}

    /**
     * List intercompany sales orders.
     * Query params: selling_organization_id, buying_organization_id, status, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['selling_organization_id', 'buying_organization_id', 'status']);
        $perPage = $request->integer('per_page', 20);

        $paginator = $this->service->list($this->callerOrganizationId(), $filters, $perPage);

        return $this->paginated($paginator, IntercompanySalesOrderResource::class);
    }

    /**
     * Create a new intercompany sales order. The caller's organization must be
     * the seller or the buyer; products and the transfer price version belong
     * to the seller.
     */
    public function store(Request $request): JsonResponse
    {
        $callerOrganizationId = $this->callerOrganizationId();
        $sellerId = $request->integer('selling_organization_id');

        $validated = $request->validate([
            'selling_organization_id'    => [
                'required', 'integer', $this->inCallerGroup(),
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $callerOrganizationId): void {
                    if ((int) $value !== $callerOrganizationId && $request->integer('buying_organization_id') !== $callerOrganizationId) {
                        $fail('Your organization must be the selling or the buying organization.');
                    }
                },
            ],
            'buying_organization_id'     => ['required', 'integer', $this->inCallerGroup(), 'different:selling_organization_id'],
            'order_number'               => 'required|string|max:50',
            'order_date'                 => 'required|date',
            'requested_delivery_date'    => 'nullable|date|after_or_equal:order_date',
            'transfer_price_version_id'  => ['nullable', 'integer', Rule::exists('transfer_price_versions', 'id')->where('organization_id', $sellerId)],
            'currency_code'              => 'nullable|string|size:3',
            'notes'                      => 'nullable|string|max:5000',
            'lines'                      => 'required|array|min:1',
            'lines.*.product_id'         => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', $sellerId)],
            'lines.*.line_number'        => 'required|integer|min:1',
            'lines.*.description'        => 'nullable|string|max:500',
            'lines.*.quantity'           => 'required|numeric|min:0.0001',
            'lines.*.unit_of_measure'    => 'nullable|string|max:20',
            'lines.*.transfer_price'     => 'required|numeric|min:0',
            'lines.*.list_price'         => 'nullable|numeric|min:0',
            'lines.*.tax_rate'           => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['created_by'] = auth()->id();

        $order = $this->service->create($validated);

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order created.', 201);
    }

    /**
     * Show an intercompany sales order with lines, PO link, and billing documents.
     */
    public function show(int|string $id): JsonResponse
    {
        $order = $this->service->orderDetails($this->callerOrganizationId(), $id);

        return $this->success(new IntercompanySalesOrderResource($order));
    }

    /**
     * Update header fields of a draft intercompany sales order.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);

        $validated = $request->validate([
            'order_date'                 => 'sometimes|date',
            'requested_delivery_date'    => 'nullable|date',
            'transfer_price_version_id'  => [
                'nullable', 'integer',
                Rule::exists('transfer_price_versions', 'id')->where('organization_id', $order->selling_organization_id),
            ],
            'currency_code'              => 'nullable|string|size:3',
            'notes'                      => 'nullable|string|max:5000',
        ]);

        $order = $this->service->update($order, $validated);

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order updated.');
    }

    /**
     * Confirm a draft intercompany sales order.
     */
    public function confirm(int|string $id): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);

        try {
            $order = $this->service->confirm($order);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order confirmed.');
    }

    /**
     * Link the buying org's purchase order to this IC sales order.
     * Body: { "purchase_order_id": 123 }
     */
    public function linkPurchaseOrder(Request $request, int|string $id): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);

        $validated = $request->validate([
            'purchase_order_id' => [
                'required', 'integer',
                Rule::exists('purchase_orders', 'id')->where('organization_id', $order->buying_organization_id),
            ],
        ]);

        $link = $this->service->linkPurchaseOrder($order, (int) $validated['purchase_order_id']);

        return $this->success($link, 'Purchase order linked.');
    }

    /**
     * Transition a confirmed order to in_delivery.
     */
    public function startDelivery(int|string $id): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);

        try {
            $order = $this->service->startDelivery($order);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(new IntercompanySalesOrderResource($order), 'Delivery started.');
    }

    /**
     * Create a draft intercompany billing document (IV) for an order.
     */
    public function createBillingDocument(Request $request, int|string $id): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);

        $validated = $request->validate([
            'document_number' => 'required|string|max:50',
            'billing_date'    => 'required|date',
            'currency_code'   => 'nullable|string|size:3',
            'subtotal'        => 'required|numeric|min:0',
            'tax_amount'      => 'nullable|numeric|min:0',
            'total_amount'    => 'required|numeric|min:0',
            'notes'           => 'nullable|string|max:5000',
        ]);

        try {
            $doc = $this->service->createBillingDocument($order, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(new IntercompanyBillingDocumentResource($doc), 'Billing document created.', 201);
    }

    /**
     * Post an intercompany billing document of the order.
     */
    public function postBillingDocument(int|string $id, int|string $billingDocId): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);
        $doc   = $this->service->billingDocumentOf($order, $billingDocId);

        try {
            $doc = $this->service->postBillingDocument($doc);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(new IntercompanyBillingDocumentResource($doc), 'Billing document posted.');
    }

    /**
     * Cancel an intercompany sales order.
     */
    public function cancel(int|string $id): JsonResponse
    {
        $order = $this->service->orderFor($this->callerOrganizationId(), $id);

        try {
            $order = $this->service->cancel($order);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order cancelled.');
    }

    private function callerOrganizationId(): int
    {
        return (int) auth()->user()->organization_id;
    }
}
