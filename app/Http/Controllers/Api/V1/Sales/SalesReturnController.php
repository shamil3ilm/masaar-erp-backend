<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesReturn;
use App\Services\Sales\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SalesReturnController extends Controller
{

    public function __construct(
        protected SalesReturnService $salesReturnService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $returns = $this->salesReturnService->list(
            $request->user()->organization_id,
            $request->only(['status', 'customer_id', 'return_type', 'from_date', 'to_date']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($returns);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        // Normalize items: accept both 'quantity' and 'quantity_returned'
        $data = $request->all();
        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (isset($item['quantity']) && !isset($item['quantity_returned'])) {
                    $item['quantity_returned'] = $item['quantity'];
                }
            }
            unset($item);
        }

        $validator = Validator::make($data, [
            'customer_id' => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('organization_id', $orgId)],
            'return_date' => 'required|date',
            'return_type' => 'required|in:refund,exchange,credit_note,replacement',
            'return_reason_id' => ['nullable', Rule::exists('return_reasons', 'id')->where('organization_id', $orgId)],
            'reason_notes' => 'nullable|string|max:1000',
            'currency_code' => 'nullable|string|size:3',
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'restock_items' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'items.*.description' => 'nullable|string|max:500',
            'items.*.quantity_returned' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:500',
            'items.*.condition' => 'nullable|in:new,like_new,used,damaged,defective',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $salesReturn = $this->salesReturnService->create(
                array_merge($data, ['organization_id' => $orgId]),
                $request->user()->id
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($salesReturn, 'Sales return created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->salesReturnService->findWithDetails($request->user()->organization_id, $id));
    }

    /**
     * Approve or reject a sales return.
     * POST /returns/{id}/review  {"action": "approve"|"reject", "reason": "..."}
     */
    public function review(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:500',
        ]);

        try {
            if ($validated['action'] === 'approve') {
                $salesReturn = $this->salesReturnService->approve($salesReturn, $request->user()->id);
                return $this->success($salesReturn, 'Sales return approved.');
            }

            $salesReturn = $this->salesReturnService->reject($salesReturn, $request->user()->id, $validated['reason']);
            return $this->success($salesReturn, 'Sales return rejected.');
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }
    }

    public function receiveItems(Request $request, int $id): JsonResponse
    {
        $salesReturn = $this->salesReturnService->findWithItems($request->user()->organization_id, $id);

        // If no items provided, auto-receive all items from the return
        $items = $request->items;
        if (empty($items)) {
            $items = $this->salesReturnService->fullReceipt($salesReturn);
        }

        // If the return has no items, just mark as received directly
        if (empty($items)) {
            try {
                return $this->success(
                    $this->salesReturnService->markReceivedWithoutItems($salesReturn),
                    'Items received successfully.'
                );
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
            } catch (\App\Exceptions\ApiException $e) {
                return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
            } catch (\Exception $e) {
                report($e);
                return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
            }
        }

        $validator = Validator::make(['items' => $items], [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:sales_return_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.quantity_damaged' => 'nullable|numeric|min:0',
            'items.*.condition' => 'nullable|in:new,like_new,used,damaged,defective',
            'items.*.condition_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $salesReturn = $this->salesReturnService->receiveItems($salesReturn, $items);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($salesReturn, 'Items received successfully.');
    }

    public function inspect(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        // Accept both 'notes' and 'inspection_notes'
        $data = $request->all();
        if (isset($data['inspection_notes']) && !isset($data['notes'])) {
            $data['notes'] = $data['inspection_notes'];
        }

        $validator = Validator::make($data, [
            'inspection_status' => 'required|in:passed,failed,partial',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $salesReturn = $this->salesReturnService->inspect($salesReturn, $data['inspection_status'], $data['notes'] ?? null);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($salesReturn, 'Inspection recorded.');
    }

    public function resolve(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $request->validate([
            'resolution_type' => 'required|in:full_refund,partial_refund,exchange,credit_note,replacement,rejected',
            'restock_items' => 'nullable|boolean',
        ]);

        try {
            $salesReturn = $this->salesReturnService->resolve(
                $salesReturn,
                $request->resolution_type,
                $request->user()->id,
                $request->has('restock_items') ? $request->boolean('restock_items') : null,
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($salesReturn, 'Return resolved successfully.');
    }

    public function exchange(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        // Accept both 'items' and 'exchange_items' keys
        $data = $request->all();
        if (isset($data['exchange_items']) && !isset($data['items'])) {
            $data['items'] = $data['exchange_items'];
        }

        // Normalize simplified item format to full format
        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                // If simple format (description, quantity, unit_price), convert to exchange format
                if (isset($item['quantity']) && !isset($item['replacement_quantity'])) {
                    $item['replacement_quantity'] = $item['quantity'];
                    $item['original_quantity'] = $item['quantity'];
                }
                if (isset($item['unit_price']) && !isset($item['replacement_unit_price'])) {
                    $item['replacement_unit_price'] = $item['unit_price'];
                    $item['original_unit_price'] = $item['unit_price'];
                }
            }
            unset($item);
        }

        $validator = Validator::make($data, [
            'items' => 'required|array|min:1',
            'items.*.original_product_id' => ['nullable', Rule::exists('products', 'id')->where('organization_id', $request->user()->organization_id)],
            'items.*.replacement_product_id' => ['nullable', Rule::exists('products', 'id')->where('organization_id', $request->user()->organization_id)],
            'items.*.description' => 'nullable|string|max:500',
            'items.*.original_quantity' => 'nullable|numeric|min:0.01',
            'items.*.replacement_quantity' => 'required|numeric|min:0.01',
            'items.*.original_unit_price' => 'nullable|numeric|min:0',
            'items.*.replacement_unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $exchangeOrder = $this->salesReturnService->createExchange(
                $salesReturn,
                $data['items'],
                $request->user()->id
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($exchangeOrder, 'Exchange order created.');
    }
}
