<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\TransferPrice;
use App\Services\Accounting\TransferPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransferPricingController extends Controller
{
    public function __construct(
        private readonly TransferPricingService $service
    ) {}

    // ================================================================
    // Transfer Prices
    // ================================================================

    /**
     * List transfer prices with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'active_only'           => $request->boolean('active_only'),
            'product_id'            => $request->filled('product_id') ? $request->integer('product_id') : null,
            'from_profit_center_id' => $request->filled('from_profit_center_id') ? $request->integer('from_profit_center_id') : null,
            'to_profit_center_id'   => $request->filled('to_profit_center_id') ? $request->integer('to_profit_center_id') : null,
            'method'                => $request->filled('method') ? $request->method : null,
        ];

        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->paginatePrices($filters, $perPage));
    }

    /**
     * Create a new transfer price.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'from_profit_center_id'  => ['nullable', 'integer', 'exists:profit_centers,id'],
            'to_profit_center_id'    => ['nullable', 'integer', 'exists:profit_centers,id'],
            'from_cost_center_id'    => ['nullable', 'integer', 'exists:cost_centers,id'],
            'to_cost_center_id'      => ['nullable', 'integer', 'exists:cost_centers,id'],
            'product_id'             => ['nullable', 'integer', 'exists:products,id'],
            'cost_element_id'        => ['nullable', 'integer', 'exists:cost_elements,id'],
            'transfer_price_method'  => ['required', Rule::in(TransferPrice::METHODS)],
            'base_price'             => ['required', 'numeric', 'min:0'],
            'markup_percentage'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'effective_from'         => ['required', 'date'],
            'effective_to'           => ['nullable', 'date', 'after_or_equal:effective_from'],
            'currency_code'          => ['required', 'string', 'size:3'],
            'is_active'              => ['nullable', 'boolean'],
        ]);

        $tp = $this->service->create(
            array_merge($validated, ['organization_id' => $orgId])
        );

        return $this->created(
            $tp->load(['fromProfitCenter:id,code,name', 'toProfitCenter:id,code,name', 'product:id,name,code'])
        );
    }

    /**
     * Show a single transfer price with conditions and recent history.
     */
    public function show(int $id): JsonResponse
    {
        $tp = $this->service->findPriceWithDetails($id);

        return $this->success($tp);
    }

    /**
     * Update a transfer price.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tp = $this->service->findPrice($id);

        $validated = $request->validate([
            'from_profit_center_id'  => ['nullable', 'integer', 'exists:profit_centers,id'],
            'to_profit_center_id'    => ['nullable', 'integer', 'exists:profit_centers,id'],
            'from_cost_center_id'    => ['nullable', 'integer', 'exists:cost_centers,id'],
            'to_cost_center_id'      => ['nullable', 'integer', 'exists:cost_centers,id'],
            'product_id'             => ['nullable', 'integer', 'exists:products,id'],
            'cost_element_id'        => ['nullable', 'integer', 'exists:cost_elements,id'],
            'transfer_price_method'  => ['sometimes', 'required', Rule::in(TransferPrice::METHODS)],
            'base_price'             => ['sometimes', 'required', 'numeric', 'min:0'],
            'markup_percentage'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'effective_from'         => ['sometimes', 'required', 'date'],
            'effective_to'           => ['nullable', 'date', 'after_or_equal:effective_from'],
            'currency_code'          => ['sometimes', 'required', 'string', 'size:3'],
            'is_active'              => ['nullable', 'boolean'],
            'change_reason'          => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->service->update($tp, $validated);

        return $this->success($updated->load(['fromProfitCenter:id,code,name', 'toProfitCenter:id,code,name']));
    }

    /**
     * Soft-delete a transfer price.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->deletePrice($this->service->findPrice($id));

        return $this->success(['message' => 'Transfer price deleted.']);
    }

    // ================================================================
    // Versions
    // ================================================================

    /**
     * List versions for the current organisation.
     */
    public function versions(Request $request): JsonResponse
    {
        $versions = $this->service->paginateVersions(
            $request->filled('status') ? $request->status : null,
            $request->filled('fiscal_year') ? $request->integer('fiscal_year') : null,
            $request->integer('per_page', 20),
        );

        return $this->paginated($versions);
    }

    /**
     * Create a new transfer price version (always draft).
     */
    public function storeVersion(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'version_name' => ['required', 'string', 'max:255'],
            'fiscal_year'  => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $version = $this->service->createVersion(
            array_merge($validated, [
                'organization_id' => $orgId,
                'created_by'      => $request->user()->id,
            ])
        );

        return $this->created($version->load('createdBy:id,name'));
    }

    /**
     * Activate a draft version.
     */
    public function activateVersion(int $id): JsonResponse
    {
        $version = $this->service->findVersion($id);

        $this->service->activateVersion($version);

        return $this->success($version->fresh());
    }

    // ================================================================
    // Calculate
    // ================================================================

    /**
     * Calculate the transfer amount for a given product/PC pair and quantity.
     *
     * POST /transfer-pricing/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'              => ['required', 'integer', 'exists:products,id'],
            'from_profit_center_id'   => ['required', 'integer', 'exists:profit_centers,id'],
            'to_profit_center_id'     => ['required', 'integer', 'exists:profit_centers,id'],
            'quantity'                => ['required', 'numeric', 'min:0.0001'],
            'date'                    => ['required', 'date'],
        ]);

        $tp = $this->service->getTransferPrice(
            $validated['product_id'],
            $validated['from_profit_center_id'],
            $validated['to_profit_center_id'],
            $validated['date']
        );

        if ($tp === null) {
            return $this->notFound('No active transfer price found for the given parameters.');
        }

        $result = $this->service->calculateTransferAmount($tp, (float) $validated['quantity']);

        return $this->success([
            'transfer_price' => $tp->only(['id', 'uuid', 'transfer_price_method', 'base_price', 'currency_code']),
            'quantity'       => $validated['quantity'],
            'calculation'    => $result,
        ]);
    }
}
