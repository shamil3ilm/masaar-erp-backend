<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\CoProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoProductController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly CoProductService $service
    ) {}

    public function indexForBom(int $bomId): JsonResponse
    {
        $this->service->findBomOrFail($bomId);

        return $this->success($this->service->getForBom($bomId), 'Co/by-products retrieved successfully.');
    }

    public function addToBom(int $bomId, Request $request): JsonResponse
    {
        $bom = $this->service->findBomOrFail($bomId);

        $validated = $request->validate([
            'product_id' => ['required', $this->ownedBy('products')],
            'co_product_type' => 'nullable|in:co_product,by_product,scrap',
            'quantity_per_base' => 'required|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'cost_allocation_percent' => 'nullable|numeric|min:0|max:100',
            'is_valuated' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $coProduct = $this->service->addCoProduct($bom, $validated);

        return $this->created($coProduct->load('product'), 'Co/by-product added to BOM successfully.');
    }

    public function updateCoProduct(int $bomId, int $id, Request $request): JsonResponse
    {
        $coProduct = $this->service->findCoProductOrFail($this->service->findBomOrFail($bomId), $id);

        $validated = $request->validate([
            'product_id' => ['sometimes', $this->ownedBy('products')],
            'co_product_type' => 'nullable|in:co_product,by_product,scrap',
            'quantity_per_base' => 'sometimes|required|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'cost_allocation_percent' => 'nullable|numeric|min:0|max:100',
            'is_valuated' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $updated = $this->service->updateCoProduct($coProduct, $validated);

        return $this->success($updated->load('product'), 'Co/by-product updated successfully.');
    }

    public function removeFromBom(int $bomId, int $id): JsonResponse
    {
        $this->service->removeCoProduct(
            $this->service->findCoProductOrFail($this->service->findBomOrFail($bomId), $id)
        );

        return $this->noContent();
    }

    public function indexForWorkOrder(int $workOrderId): JsonResponse
    {
        $actuals = $this->service->getForWorkOrder($workOrderId);

        return $this->success($actuals, 'Work order co/by-product actuals retrieved.');
    }

    public function postActuals(int $workOrderId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actuals' => 'required|array|min:1',
            'actuals.*.product_id' => ['required', $this->ownedBy('products')],
            'actuals.*.bom_co_product_id' => ['nullable', $this->ownedBy('bom_co_products')],
            'actuals.*.co_product_type' => 'nullable|in:co_product,by_product,scrap',
            'actuals.*.planned_quantity' => 'nullable|numeric|min:0',
            'actuals.*.actual_quantity' => 'required|numeric|min:0',
            'actuals.*.unit_of_measure' => 'nullable|string|max:20',
            'actuals.*.warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
        ]);

        $results = $this->service->postActual($workOrderId, $validated['actuals']);

        return $this->success($results, 'Co/by-product actuals posted successfully.');
    }

    public function postToStock(int $workOrderId, int $actualId): JsonResponse
    {
        $this->service->postToStock($this->service->findActualOrFail($workOrderId, $actualId));

        return $this->success(null, 'Co/by-product actual posted to stock.');
    }
}
