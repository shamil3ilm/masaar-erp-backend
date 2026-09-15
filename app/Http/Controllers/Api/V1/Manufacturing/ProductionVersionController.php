<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\ProductionVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionVersionController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ProductionVersionService $service,
    ) {}

    /**
     * List production versions with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate(
            ['product_id' => $request->product_id, 'active_only' => $request->boolean('active_only', false)],
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a new production version.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'       => ['required', $this->ownedBy('products')],
            'version_code'     => 'required|string|max:20',
            'description'      => 'nullable|string|max:255',
            'bom_id'           => ['nullable', $this->ownedBy('bom_templates')],
            'routing_id'       => ['nullable', $this->ownedBy('routing_headers')],
            'lot_size_from'    => 'nullable|numeric|min:0',
            'lot_size_to'      => 'nullable|numeric|min:0|gte:lot_size_from',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'production_plant' => 'nullable|string|max:50',
            'is_default'       => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        $version = $this->service->create($validated);

        return $this->created($version->load(['product', 'bom', 'routing']));
    }

    /**
     * Show a single production version.
     */
    public function show(int $id): JsonResponse
    {
        $version = $this->service->find($id, ['product', 'bom', 'routing']);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        return $this->success($version);
    }

    /**
     * Update a production version.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $version = $this->service->find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        $validated = $request->validate([
            'version_code'     => 'sometimes|string|max:20',
            'description'      => 'nullable|string|max:255',
            'bom_id'           => ['nullable', $this->ownedBy('bom_templates')],
            'routing_id'       => ['nullable', $this->ownedBy('routing_headers')],
            'lot_size_from'    => 'nullable|numeric|min:0',
            'lot_size_to'      => 'nullable|numeric|min:0',
            'valid_from'       => 'sometimes|date',
            'valid_to'         => 'nullable|date',
            'production_plant' => 'nullable|string|max:50',
            'is_default'       => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        $updated = $this->service->update($version, $validated);

        return $this->success($updated->load(['product', 'bom', 'routing']));
    }

    /**
     * Soft-delete a production version.
     */
    public function destroy(int $id): JsonResponse
    {
        $version = $this->service->find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        $this->service->delete($version);

        return $this->success(null, 'Production version deleted.');
    }

    /**
     * Set a version as the default for its product.
     */
    public function setDefault(int $id): JsonResponse
    {
        $version = $this->service->find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        $this->service->setDefault($version);

        return $this->success($version->fresh(['product', 'bom', 'routing']), 'Default version updated.');
    }

    /**
     * List all versions for a specific product.
     */
    public function forProduct(int $productId): JsonResponse
    {
        return $this->success($this->service->getVersionsForProduct($productId));
    }
}
