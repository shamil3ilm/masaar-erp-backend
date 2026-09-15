<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Manufacturing\SubcontractReceipt;
use App\Models\Manufacturing\SubcontractTransfer;
use App\Services\Manufacturing\SubcontractingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcontractingController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private SubcontractingService $subcontractingService,
    ) {}

    // -------------------------------------------------------------------------
    // Subcontract Orders
    // -------------------------------------------------------------------------

    /**
     * List subcontract orders.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->subcontractingService->paginate(
            $request->only(['status', 'contact_id', 'branch_id', 'search', 'from_date', 'to_date']),
            $this->safeSortBy($request->sort_by, SubcontractingService::SORT_COLUMNS, 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a subcontract order.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'             => ['required', $this->ownedBy('contacts')],
            'issued_date'            => 'nullable|date',
            'expected_receipt_date'  => 'nullable|date|after_or_equal:issued_date',
            'currency_code'          => 'nullable|string|size:3',
            'service_charge'         => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string',
            'purchase_order_id'      => ['nullable', $this->ownedBy('purchase_orders')],
            'branch_id'              => ['nullable', $this->ownedBy('branches')],
            'lines'                  => 'required|array|min:1',
            'lines.*.product_id'     => ['required', $this->ownedBy('products')],
            'lines.*.variant_id'     => ['nullable', $this->ownedVariant()],
            'lines.*.ordered_quantity'    => 'required|numeric|min:0.0001',
            'lines.*.unit_id'             => ['required', $this->ownedBy('units_of_measure')],
            'lines.*.unit_service_charge' => 'nullable|numeric|min:0',
            'components'                  => 'nullable|array',
            'components.*.product_id'     => ['required', $this->ownedBy('products')],
            'components.*.variant_id'     => ['nullable', $this->ownedVariant()],
            'components.*.required_quantity' => 'required|numeric|min:0.0001',
            'components.*.unit_id'           => ['required', $this->ownedBy('units_of_measure')],
            'components.*.warehouse_id'      => ['required', $this->ownedBy('warehouses')],
        ]);

        $order = $this->subcontractingService->createOrder($data);

        return $this->success($order, 'Subcontract order created.', 201);
    }

    /**
     * Show a single subcontract order.
     */
    public function show(SubcontractOrder $subcontractOrder): JsonResponse
    {
        return $this->success($this->subcontractingService->withDetails($subcontractOrder));
    }

    /**
     * Update a draft subcontract order.
     */
    public function update(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        if (!$subcontractOrder->isDraft()) {
            return $this->error('Only draft orders can be updated.', 'INVALID_STATUS', 422);
        }

        $data = $request->validate([
            'contact_id'            => ['sometimes', $this->ownedBy('contacts')],
            'issued_date'           => 'nullable|date',
            'expected_receipt_date' => 'nullable|date',
            'currency_code'         => 'nullable|string|size:3',
            'service_charge'        => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string',
            'purchase_order_id'     => ['nullable', $this->ownedBy('purchase_orders')],
            'branch_id'             => ['nullable', $this->ownedBy('branches')],
        ]);

        try {
            $order = $this->subcontractingService->update($subcontractOrder, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->success($order, 'Order updated.');
    }

    /**
     * Send the order to the vendor (draft → sent).
     */
    public function sendToVendor(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $order = $this->subcontractingService->sendToVendor($subcontractOrder);

        return $this->success($order, 'Order sent to vendor.');
    }

    // -------------------------------------------------------------------------
    // Material Transfers
    // -------------------------------------------------------------------------

    /**
     * Transfer raw materials to the vendor.
     */
    public function transferMaterials(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        $data = $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.component_id'     => 'required|integer',
            'items.*.quantity'         => 'required|numeric|min:0.0001',
            'items.*.batch_number'     => 'nullable|string|max:100',
        ]);

        $transfer = $this->subcontractingService->transferMaterialsToVendor(
            $subcontractOrder,
            $data['items']
        );

        return $this->success($transfer, 'Materials transferred to vendor.', 201);
    }

    /**
     * List transfers for an order.
     */
    public function indexTransfers(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        return $this->paginated($this->subcontractingService->paginateTransfers(
            $subcontractOrder,
            $request->transfer_type,
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Show a single transfer document.
     */
    public function showTransfer(SubcontractTransfer $transfer): JsonResponse
    {
        return $this->success($this->subcontractingService->transferWithDetails($transfer));
    }

    // -------------------------------------------------------------------------
    // Receipts
    // -------------------------------------------------------------------------

    /**
     * Receive finished goods from the vendor.
     */
    public function receiveFromVendor(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id'                    => ['required', $this->ownedBy('warehouses')],
            'receipt_date'                    => 'nullable|date',
            'notes'                           => 'nullable|string',
            'lines'                           => 'required|array|min:1',
            'lines.*.order_line_id'           => 'required|integer',
            'lines.*.quantity_received'       => 'required|numeric|min:0',
            'lines.*.quantity_rejected'       => 'nullable|numeric|min:0',
            'lines.*.unit_cost'               => 'nullable|numeric|min:0',
            'lines.*.batch_number'            => 'nullable|string|max:100',
            'lines.*.expiry_date'             => 'nullable|date',
        ]);

        $receipt = $this->subcontractingService->receiveFromVendor($subcontractOrder, $data);

        return $this->success($receipt, 'Goods received from vendor.', 201);
    }

    /**
     * List receipts for an order.
     */
    public function indexReceipts(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        return $this->paginated($this->subcontractingService->paginateReceipts(
            $subcontractOrder,
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Show a single receipt document.
     */
    public function showReceipt(SubcontractReceipt $receipt): JsonResponse
    {
        return $this->success($this->subcontractingService->receiptWithDetails($receipt));
    }

    // -------------------------------------------------------------------------
    // Order Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Close the order and settle outstanding quantities.
     */
    public function closeOrder(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $order = $this->subcontractingService->closeOrder($subcontractOrder);

        return $this->success($order, 'Subcontract order closed.');
    }

    /**
     * Cancel a draft or sent order.
     */
    public function cancel(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $order = $this->subcontractingService->cancel($subcontractOrder);

        return $this->success($order, 'Subcontract order cancelled.');
    }
}
