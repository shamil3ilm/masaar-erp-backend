<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\ThirdPartyOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ThirdPartyOrderController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(
        private ThirdPartyOrderService $thirdPartyOrderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->thirdPartyOrderService->list(
            $request->only(['status', 'vendor_id', 'sales_order_id']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sales_order_id' => ['required', $this->ownedBy('sales_orders')],
            'vendor_id' => ['required', $this->ownedBy('contacts')],
            'shipping_address_line1' => 'nullable|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_country_code' => 'nullable|string|size:2',
            'vendor_reference' => 'nullable|string|max:100',
            'estimated_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'lines' => 'nullable|array',
            'lines.*.product_id' => ['required', $this->ownedBy('products')],
            'lines.*.sales_order_line_id' => ['nullable', $this->ownedThrough('sales_order_lines', 'sales_order_id', 'sales_orders')],
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vendor_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $order = $this->thirdPartyOrderService->create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($order);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->thirdPartyOrderService->orderDetails($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $order = $this->thirdPartyOrderService->orderOf($id);

        $validator = Validator::make($request->all(), [
            'shipping_address_line1' => 'nullable|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_country_code' => 'nullable|string|size:2',
            'vendor_reference' => 'nullable|string|max:100',
            'estimated_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->thirdPartyOrderService->update($order, $validator->validated());

        return $this->success($updated);
    }

    public function createPO(int $id): JsonResponse
    {
        $order = $this->thirdPartyOrderService->orderOf($id);

        try {
            $po = $this->thirdPartyOrderService->createPurchaseOrder($order);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->created($po, 'Purchase order created successfully.');
    }

    public function confirmShipment(Request $request, int $id): JsonResponse
    {
        $order = $this->thirdPartyOrderService->orderOf($id);

        $validator = Validator::make($request->all(), [
            'shipping_confirmation' => 'nullable|string|max:100',
            'vendor_reference' => 'nullable|string|max:100',
            'estimated_delivery_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->thirdPartyOrderService->confirmShipment($order, $validator->validated());

        return $this->success($updated, 'Shipment confirmed.');
    }

    public function confirmDelivery(Request $request, int $id): JsonResponse
    {
        $order = $this->thirdPartyOrderService->orderOf($id);

        $validator = Validator::make($request->all(), [
            'actual_delivery_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->thirdPartyOrderService->confirmDelivery(
            $order,
            $request->input('actual_delivery_date')
        );

        return $this->success($updated, 'Delivery confirmed.');
    }

    public function cancel(int $id): JsonResponse
    {
        $updated = $this->thirdPartyOrderService->cancel($this->thirdPartyOrderService->orderOf($id));

        return $this->success($updated, 'Third-party order cancelled.');
    }
}
