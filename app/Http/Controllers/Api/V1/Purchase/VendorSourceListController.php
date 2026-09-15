<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\VendorSourceListResource;
use App\Http\Resources\Sales\ContactResource;
use App\Services\Purchase\SourceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSourceListController extends Controller
{
    use ValidatesOwnedRows;

    /** Relations a source list entry is returned with after a change. */
    private const WITH = ['vendor', 'product:id,name,sku'];

    public function __construct(
        private SourceListService $sourceListService
    ) {}

    /**
     * List all vendor source-list entries for the organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $entries = $this->sourceListService->listSourceListEntries(
            $request->only(['product_id', 'vendor_id', 'active_only']),
            $request->integer('per_page', 20),
        );

        return $this->paginated($entries, VendorSourceListResource::class);
    }

    /**
     * Store a new vendor source-list entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'               => ['required', $this->ownedBy('products')],
            'vendor_id'                => ['required', $this->ownedBy('contacts')],
            'vendor_product_pricing_id' => ['nullable', $this->ownedBy('vendor_product_pricing')],
            'plant_code'               => 'nullable|string|max:50',
            'valid_from'               => 'nullable|date',
            'valid_to'                 => 'nullable|date|after_or_equal:valid_from',
            'is_fixed_vendor'          => 'nullable|boolean',
            'is_blocked'               => 'nullable|boolean',
            'priority'                 => 'nullable|integer|min:1',
            'quota_percentage'         => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $entry = $this->sourceListService->createSourceListEntry($validated);

        return $this->created(
            new VendorSourceListResource($entry->load(self::WITH)),
            'Vendor source list entry created.'
        );
    }

    /**
     * Show a single vendor source-list entry.
     */
    public function show(int $id): JsonResponse
    {
        $entry = $this->sourceListService->findSourceListEntry($id, [...self::WITH, 'pricingRecord']);

        if (!$entry) {
            return $this->notFound('Vendor source list entry not found.');
        }

        return $this->success(new VendorSourceListResource($entry));
    }

    /**
     * Update a vendor source-list entry.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entry = $this->sourceListService->findSourceListEntry($id);

        if (!$entry) {
            return $this->notFound('Vendor source list entry not found.');
        }

        $validated = $request->validate([
            'vendor_product_pricing_id' => ['nullable', $this->ownedBy('vendor_product_pricing')],
            'plant_code'                => 'nullable|string|max:50',
            'valid_from'                => 'nullable|date',
            'valid_to'                  => 'nullable|date|after_or_equal:valid_from',
            'is_fixed_vendor'           => 'nullable|boolean',
            'is_blocked'                => 'nullable|boolean',
            'priority'                  => 'nullable|integer|min:1',
            'quota_percentage'          => 'nullable|numeric|min:0|max:100',
        ]);

        $entry = $this->sourceListService->updateSourceListEntry($entry, $validated);

        return $this->success(
            new VendorSourceListResource($entry->load(self::WITH)),
            'Vendor source list entry updated.'
        );
    }

    /**
     * Delete a vendor source-list entry.
     */
    public function destroy(int $id): JsonResponse
    {
        $entry = $this->sourceListService->findSourceListEntry($id);

        if (!$entry) {
            return $this->notFound('Vendor source list entry not found.');
        }

        $this->sourceListService->deleteSourceListEntry($entry);

        return $this->success(null, 'Vendor source list entry deleted.');
    }

    /**
     * Return ordered approved vendors for a given product.
     */
    public function vendorsForProduct(int $productId): JsonResponse
    {
        return $this->success(
            ContactResource::collection($this->sourceListService->getVendorsForProduct($productId)),
            'Approved vendors for product retrieved.'
        );
    }
}
