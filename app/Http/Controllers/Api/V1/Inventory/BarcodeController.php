<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductBarcode;
use App\Services\Inventory\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private BarcodeService $barcodeService
    ) {}

    /**
     * List barcodes.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['barcode_type', 'usage']);

        if ($request->has('product_id')) {
            $filters['product_id'] = $request->integer('product_id');
        }

        foreach (['is_primary', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $filters[$flag] = $request->boolean($flag);
            }
        }

        return $this->success($this->barcodeService->list($filters));
    }

    /**
     * List barcodes for a specific product.
     */
    public function listForProduct(Product $product): JsonResponse
    {
        return $this->success($this->barcodeService->listForProduct($product->id));
    }

    /**
     * Store a barcode for a specific product.
     */
    public function storeForProduct(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'barcode_value' => 'required|string|max:100|unique:product_barcodes,barcode_value,NULL,id,organization_id,' . auth()->user()->organization_id,
            'barcode_type' => 'required|string|in:ean13,ean8,upc_a,upc_e,code128,code39,qr,datamatrix,itf14,isbn,issn,gs1_128,custom',
            'usage' => 'nullable|string|in:product,packaging,pallet,internal,shelf,price_tag',
            'is_primary' => 'boolean',
            'gtin' => 'nullable|string|max:14',
            'gs1_company_prefix' => 'nullable|string|max:12',
        ]);

        $barcode = $this->barcodeService->assignToProduct(
            $product->id,
            $validated['barcode_value'],
            $validated['barcode_type'],
            collect($validated)->except(['barcode_value', 'barcode_type'])->toArray()
        );

        return $this->created($barcode, 'Barcode created successfully.');
    }

    /**
     * Create a new barcode.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', $this->ownedBy('products')],
            'variant_id' => ['nullable', 'integer', $this->ownedVariant()],
            'batch_id' => ['nullable', 'integer', $this->ownedBy('inventory_batches')],
            'barcode_value' => 'required|string|max:100',
            'barcode_type' => 'required|string|in:ean13,ean8,upc_a,upc_e,code128,code39,qr,datamatrix,itf14,isbn,issn,gs1_128,custom',
            'usage' => 'nullable|string|in:product,packaging,pallet,internal,shelf,price_tag',
            'is_primary' => 'boolean',
            'gtin' => 'nullable|string|max:14',
            'gs1_company_prefix' => 'nullable|string|max:12',
        ]);

        $barcode = $this->barcodeService->assignToProduct(
            $validated['product_id'],
            $validated['barcode_value'],
            $validated['barcode_type'],
            collect($validated)->except(['product_id', 'barcode_value', 'barcode_type'])->toArray()
        );

        return $this->created($barcode, 'Barcode created successfully.');
    }

    /**
     * Show a barcode.
     */
    public function show(ProductBarcode $barcode): JsonResponse
    {
        $barcode->load(['product', 'variant']);

        return $this->success($barcode);
    }

    /**
     * Update a barcode.
     */
    public function update(Request $request, ProductBarcode $barcode): JsonResponse
    {
        $validated = $request->validate([
            'usage' => 'nullable|string|in:product,packaging,pallet,internal,shelf,price_tag',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'gtin' => 'nullable|string|max:14',
            'gs1_company_prefix' => 'nullable|string|max:12',
        ]);

        return $this->success($this->barcodeService->update($barcode, $validated), 'Barcode updated successfully.');
    }

    /**
     * Delete a barcode.
     */
    public function destroy(ProductBarcode $barcode): JsonResponse
    {
        $barcode->delete();

        return $this->success(null, 'Barcode deleted successfully.');
    }

    /**
     * Generate barcode for a product.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', $this->ownedBy('products')],
            'barcode_type' => 'nullable|string|in:ean13,ean8,code128,code39',
            'usage' => 'nullable|string|in:product,packaging,pallet,internal,shelf,price_tag',
        ]);

        $barcode = $this->barcodeService->generate(
            $validated['product_id'],
            $validated['barcode_type'] ?? 'ean13',
            $validated['usage'] ?? 'product'
        );

        return $this->created($barcode, 'Barcode generated successfully.');
    }

    /**
     * Lookup a product by barcode.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:255',
        ]);

        $result = $this->barcodeService->lookup($validated['barcode']);

        if (!$result) {
            return $this->notFound('No product found for the given barcode.');
        }

        return $this->success($result);
    }

    /**
     * Lookup a product by barcode value (convenience endpoint).
     */
    public function lookupByValue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode_value' => 'required|string|max:255',
        ]);

        $result = $this->barcodeService->lookup($validated['barcode_value']);

        if (!$result) {
            return $this->notFound('No product found for the given barcode.');
        }

        return $this->success($result);
    }

    /**
     * Bulk generate barcodes.
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => ['integer', $this->ownedBy('products')],
            'barcode_type' => 'nullable|string|in:ean13,ean8,code128,code39',
            'usage' => 'nullable|string|in:product,packaging,pallet,internal,shelf,price_tag',
        ]);

        $results = $this->barcodeService->bulkGenerate(
            $validated['product_ids'],
            $validated['barcode_type'] ?? 'ean13',
            $validated['usage'] ?? 'product'
        );

        $successCount = collect($results)->where('success', true)->count();
        $failedCount = collect($results)->where('success', false)->count();

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ], "Bulk generation completed: {$successCount} succeeded, {$failedCount} failed.");
    }

    /**
     * Print barcode labels.
     */
    public function printLabels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode_ids' => 'required|array|min:1|max:50',
            'barcode_ids.*' => ['integer', $this->ownedBy('product_barcodes')],
            'format' => 'nullable|string|in:standard,small,large',
        ]);

        $result = $this->barcodeService->printLabels(
            $validated['barcode_ids'],
            $validated['format'] ?? 'standard'
        );

        return $this->success($result, 'Labels prepared for printing.');
    }
}
