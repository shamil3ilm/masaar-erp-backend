<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\VendorProductPricingResource;
use App\Services\Purchase\SourceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPricingController extends Controller
{
    use ValidatesOwnedRows;

    /** Relations a pricing record is returned with. */
    private const WITH = ['vendor', 'product:id,name,sku'];

    public function __construct(
        private SourceListService $sourceListService
    ) {}

    /**
     * List all vendor pricing records for the organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $records = $this->sourceListService->listPricingRecords(
            $request->only(['product_id', 'vendor_id', 'preferred_only', 'valid_only']),
            $request->integer('per_page', 20),
        );

        return $this->paginated($records, VendorProductPricingResource::class);
    }

    /**
     * Store a new vendor pricing record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'                 => ['required', $this->ownedBy('products')],
            'vendor_id'                  => ['required', $this->ownedBy('contacts')],
            'vendor_product_code'        => 'nullable|string|max:100',
            'vendor_product_description' => 'nullable|string|max:500',
            'unit_price'                 => 'required|numeric|min:0',
            'currency_code'              => 'nullable|string|size:3',
            'lead_time_days'             => 'nullable|integer|min:0',
            'minimum_order_quantity'     => 'nullable|numeric|min:0',
            'order_quantity_multiple'    => 'nullable|numeric|min:0',
            'valid_from'                 => 'nullable|date',
            'valid_to'                   => 'nullable|date|after_or_equal:valid_from',
            'is_preferred_vendor'        => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $record = $this->sourceListService->createPricingRecord($validated);

        return $this->created(
            new VendorProductPricingResource($record->load(self::WITH)),
            'Vendor pricing record created.'
        );
    }

    /**
     * Show a single vendor pricing record.
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->sourceListService->findPricingRecord($id, [...self::WITH, 'vendorSourceListEntries']);

        if (!$record) {
            return $this->notFound('Vendor pricing record not found.');
        }

        return $this->success(new VendorProductPricingResource($record));
    }

    /**
     * Update a vendor pricing record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->sourceListService->findPricingRecord($id);

        if (!$record) {
            return $this->notFound('Vendor pricing record not found.');
        }

        $validated = $request->validate([
            'vendor_product_code'        => 'nullable|string|max:100',
            'vendor_product_description' => 'nullable|string|max:500',
            'unit_price'                 => 'sometimes|numeric|min:0',
            'currency_code'              => 'nullable|string|size:3',
            'lead_time_days'             => 'nullable|integer|min:0',
            'minimum_order_quantity'     => 'nullable|numeric|min:0',
            'order_quantity_multiple'    => 'nullable|numeric|min:0',
            'valid_from'                 => 'nullable|date',
            'valid_to'                   => 'nullable|date|after_or_equal:valid_from',
            'is_preferred_vendor'        => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $record = $this->sourceListService->updatePricingRecord($record, $validated);

        return $this->success(
            new VendorProductPricingResource($record->load(self::WITH)),
            'Vendor pricing record updated.'
        );
    }

    /**
     * Delete a vendor pricing record.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = $this->sourceListService->findPricingRecord($id);

        if (!$record) {
            return $this->notFound('Vendor pricing record not found.');
        }

        $this->sourceListService->deletePricingRecord($record);

        return $this->success(null, 'Vendor pricing record deleted.');
    }

    /**
     * List all valid pricing records for a specific product, ordered by
     * preferred status then unit price ascending.
     */
    public function forProduct(int $productId): JsonResponse
    {
        return $this->success(
            VendorProductPricingResource::collection($this->sourceListService->validPricingRecordsForProduct($productId)),
            'Vendor pricing records retrieved.'
        );
    }
}
