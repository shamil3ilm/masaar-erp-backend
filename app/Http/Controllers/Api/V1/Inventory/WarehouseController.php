<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use App\Services\Inventory\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class WarehouseController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private StockService $stockService,
        private WarehouseService $warehouseService
    ) {
    }

    /**
     * List warehouses.
     */
    public function index(Request $request): JsonResponse
    {
        $warehouses = $this->warehouseService->list([
            ...$request->only(['branch_id']),
            'active_only' => $request->boolean('active_only'),
        ]);

        return $this->success(WarehouseResource::collection($warehouses));
    }

    /**
     * Create a new warehouse.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', $this->ownedBy('branches')],
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', 'max:20', $this->uniqueCode()],
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'manager_id' => ['nullable', 'integer', $this->ownedBy('users')],
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'allow_negative_stock' => 'boolean',
        ]);

        $warehouse = $this->warehouseService->create($validated);

        return $this->created(new WarehouseResource($warehouse), 'Warehouse created successfully.');
    }

    /**
     * Show a warehouse.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load(['branch', 'manager', 'locations']);

        return $this->success(new WarehouseResource($warehouse));
    }

    /**
     * Update a warehouse.
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
            'name' => 'sometimes|required|string|max:100',
            'code' => ['sometimes', 'required', 'string', 'max:20', $this->uniqueCode()->ignore($warehouse->id)],
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'manager_id' => ['nullable', 'integer', $this->ownedBy('users')],
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'allow_negative_stock' => 'boolean',
        ]);

        $warehouse = $this->warehouseService->update($warehouse, $validated);

        return $this->success(new WarehouseResource($warehouse), 'Warehouse updated successfully.');
    }

    /**
     * Delete a warehouse.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        try {
            $this->warehouseService->delete($warehouse);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(null, 'Warehouse deleted successfully.');
    }

    /**
     * Get stock valuation for a warehouse.
     */
    public function stockValuation(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $result    = $this->stockService->getStockValuation(
                $warehouse->id,
                $request->integer('per_page', 25),
            );
            $paginator = $result['items'];

            return $this->success([
                'items'  => $paginator->items(),
                'totals' => $result['totals'],
                'meta'   => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Get low stock items in a warehouse.
     */
    public function lowStock(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $items = $this->stockService->getLowStockProducts(
                $warehouse->id,
                $request->integer('per_page', 25),
            );

            return $this->paginated($items);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Set warehouse as default.
     */
    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseService->setDefault($warehouse);

        return $this->success(new WarehouseResource($warehouse), 'Default warehouse updated.');
    }

    /**
     * Warehouse codes are unique within an organization, as the database
     * index is; another organization's codes neither block nor reveal.
     */
    private function uniqueCode(): Unique
    {
        return Rule::unique('warehouses', 'code')->where('organization_id', auth()->user()->organization_id);
    }
}
