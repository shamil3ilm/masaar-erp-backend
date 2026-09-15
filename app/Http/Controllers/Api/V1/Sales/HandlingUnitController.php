<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\HandlingUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HandlingUnitController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(
        private HandlingUnitService $handlingUnitService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $units = $this->handlingUnitService->list(
            $request->only(['shipment_id', 'sales_order_id', 'hu_type', 'is_sealed']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($units);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'shipment_id' => ['nullable', $this->ownedBy('shipments')],
            'sales_order_id' => ['nullable', $this->ownedBy('sales_orders')],
            'hu_type' => 'nullable|in:box,pallet,container,bag,drum,other',
            'hu_number' => 'nullable|string|max:50',
            'sscc_number' => 'nullable|string|max:30',
            'gross_weight' => 'nullable|numeric|min:0',
            'net_weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'items' => 'nullable|array',
        ], $this->itemRules('items.*.')));

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $hu = $this->handlingUnitService->create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($hu);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->handlingUnitService->unitDetails($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hu = $this->handlingUnitService->unitOf($id);

        $validator = Validator::make($request->all(), [
            'hu_type' => 'nullable|in:box,pallet,container,bag,drum,other',
            'sscc_number' => 'nullable|string|max:30',
            'gross_weight' => 'nullable|numeric|min:0',
            'net_weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->handlingUnitService->update($hu, $validator->validated());

        return $this->success($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->handlingUnitService->delete($this->handlingUnitService->unitOf($id));

        return $this->noContent();
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $hu = $this->handlingUnitService->unitOf($id);

        $validator = Validator::make($request->all(), $this->itemRules(''));

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        try {
            $item = $this->handlingUnitService->addItem($hu, $validator->validated());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->created($item);
    }

    public function removeItem(int $id, int $itemId): JsonResponse
    {
        $hu = $this->handlingUnitService->unitOf($id);

        try {
            $this->handlingUnitService->removeItem($hu, $itemId);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->noContent();
    }

    public function seal(int $id): JsonResponse
    {
        $hu = $this->handlingUnitService->unitOf($id);

        try {
            $sealed = $this->handlingUnitService->seal($hu);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($sealed, 'Handling unit sealed.');
    }

    public function packingList(int $shipmentId): JsonResponse
    {
        $packingList = $this->handlingUnitService->getPackingList($shipmentId);

        return $this->success($packingList);
    }

    /**
     * Rules for a handling unit item, under the given key prefix. Products and
     * batches must belong to the caller's organization, and an order line to
     * one of its orders.
     *
     * @return array<string, mixed>
     */
    private function itemRules(string $prefix): array
    {
        return [
            "{$prefix}product_id" => ['nullable', $this->ownedBy('products')],
            "{$prefix}inventory_batch_id" => ['nullable', $this->ownedBy('inventory_batches')],
            "{$prefix}sales_order_line_id" => ['nullable', $this->ownedThrough('sales_order_lines', 'sales_order_id', 'sales_orders')],
            "{$prefix}quantity" => 'required|numeric|min:0.0001',
            "{$prefix}weight" => 'nullable|numeric|min:0',
        ];
    }
}
