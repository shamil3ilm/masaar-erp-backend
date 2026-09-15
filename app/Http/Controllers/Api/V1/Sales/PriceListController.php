<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\PriceList;
use App\Services\Sales\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private PriceListService $priceListService
    ) {}

    /**
     * List price lists with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $priceLists = $this->priceListService->list([
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'currency_code' => $request->has('currency_code') ? $request->input('currency_code') : null,
            'search' => $request->has('search') ? (string) $request->input('search') : null,
            'valid_now' => $request->boolean('valid_now'),
        ], $request->integer('per_page', 15));

        return $this->paginated($priceLists);
    }

    /**
     * Create a new price list.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:30',
            'currency_code' => 'required|string|size:3',
            'valid_from'    => 'required|date',
            'valid_to'      => 'nullable|date|after_or_equal:valid_from',
            'is_default'    => 'boolean',
            'description'   => 'nullable|string|max:2000',
            'is_active'     => 'boolean',
            'items'         => 'nullable|array',
        ], $this->itemRules()));

        $validated['organization_id'] = $this->organizationId($request);

        $priceList = $this->priceListService->createPriceList($validated);

        return $this->success($priceList->load(['items', 'assignments']), 'Price list created.', 201);
    }

    /**
     * Show a price list with its items, assignments and volume breaks.
     */
    public function show(Request $request, PriceList $priceList): JsonResponse
    {
        return $this->success($this->priceListService->details($priceList));
    }

    /**
     * Update a price list.
     */
    public function update(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name'          => 'sometimes|string|max:100',
            'currency_code' => 'sometimes|string|size:3',
            'valid_from'    => 'sometimes|date',
            'valid_to'      => 'nullable|date',
            'is_default'    => 'boolean',
            'description'   => 'nullable|string|max:2000',
            'is_active'     => 'boolean',
            'items'         => 'nullable|array',
        ], $this->itemRules()));

        $priceList = $this->priceListService->updatePriceList($priceList, $validated);

        return $this->success($priceList, 'Price list updated.');
    }

    /**
     * Delete a price list.
     */
    public function destroy(PriceList $priceList): JsonResponse
    {
        $priceList->delete();

        return $this->success(null, 'Price list deleted.');
    }

    /**
     * Assign a price list to a specific contact.
     */
    public function assignToContact(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', $this->ownedBy('contacts')],
        ]);

        $assignment = $this->priceListService->assignToContactId($priceList, (int) $validated['contact_id']);

        return $this->success($assignment, 'Price list assigned to contact.');
    }

    /**
     * Resolve the effective price for a contact + product + quantity combination.
     * Query parameters: contact_id, product_id, quantity, currency
     */
    public function resolvePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', $this->ownedBy('contacts')],
            'product_id' => ['required', 'integer', $this->ownedBy('products')],
            'quantity'   => 'nullable|numeric|min:0',
            'currency'   => 'nullable|string|size:3',
        ]);

        $result = $this->priceListService->resolvePriceFor(
            (int) $validated['contact_id'],
            (int) $validated['product_id'],
            (float) ($validated['quantity'] ?? 1),
            $validated['currency'] ?? null
        );

        if ($result === null) {
            return $this->notFound('No applicable price list found for the given parameters.');
        }

        return $this->success($result);
    }

    /**
     * Bulk-import price list items.
     */
    public function importItems(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'items' => 'required|array|min:1',
        ], $this->itemRules()));

        $count = $this->priceListService->importItems($priceList, $validated['items']);

        return $this->success(['imported' => $count], "{$count} items imported.");
    }

    /**
     * Rules for price list item rows. Products must belong to the caller's
     * organization, and a variant to one of its products.
     *
     * @return array<string, mixed>
     */
    private function itemRules(): array
    {
        return [
            'items.*.product_id'   => ['required', 'integer', $this->ownedBy('products')],
            'items.*.variant_id'   => ['nullable', 'integer', $this->ownedThrough('product_variants', 'product_id', 'products')],
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.min_quantity' => 'nullable|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.notes'        => 'nullable|string|max:200',
        ];
    }
}
