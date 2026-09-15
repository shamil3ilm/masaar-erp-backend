<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\CustomerMaterialInfo;
use App\Services\Sales\CustomerMaterialInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMaterialInfoController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly CustomerMaterialInfoService $service
    ) {}

    /**
     * GET /api/v1/sales/customer-material-infos
     * List customer material infos with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list([
            'contact_id'  => $request->has('contact_id') ? $request->integer('contact_id') : null,
            'product_id'  => $request->has('product_id') ? $request->integer('product_id') : null,
            'active_only' => $request->boolean('active_only'),
            'search'      => $request->has('search') ? (string) $request->string('search') : null,
        ], $request->integer('per_page', 15));

        return $this->paginated($items);
    }

    /**
     * POST /api/v1/sales/customer-material-infos
     * Create a new customer material info record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'                    => ['required', 'integer', $this->ownedBy('contacts')],
            'product_id'                    => ['required', 'integer', $this->ownedBy('products')],
            'customer_material_number'      => 'nullable|string|max:100',
            'customer_material_description' => 'nullable|string|max:255',
            'delivery_lead_time_days'       => 'nullable|integer|min:0',
            'minimum_order_quantity'        => 'nullable|numeric|min:0',
            'unit_of_measure'               => 'nullable|string|max:20',
            'notes'                         => 'nullable|string|max:5000',
            'is_active'                     => 'nullable|boolean',
        ]);

        $info = $this->service->create((int) $this->organizationId($request), $validated);

        return $this->success($info, 'Customer material info created.', 201);
    }

    /**
     * GET /api/v1/sales/customer-material-infos/{customerMaterialInfo}
     */
    public function show(CustomerMaterialInfo $customerMaterialInfo): JsonResponse
    {
        return $this->success($this->service->details($customerMaterialInfo));
    }

    /**
     * PUT /api/v1/sales/customer-material-infos/{customerMaterialInfo}
     */
    public function update(Request $request, CustomerMaterialInfo $customerMaterialInfo): JsonResponse
    {
        $validated = $request->validate([
            'customer_material_number'      => 'nullable|string|max:100',
            'customer_material_description' => 'nullable|string|max:255',
            'delivery_lead_time_days'       => 'nullable|integer|min:0',
            'minimum_order_quantity'        => 'nullable|numeric|min:0',
            'unit_of_measure'               => 'nullable|string|max:20',
            'notes'                         => 'nullable|string|max:5000',
            'is_active'                     => 'nullable|boolean',
        ]);

        return $this->success(
            $this->service->update($customerMaterialInfo, $validated),
            'Customer material info updated.'
        );
    }

    /**
     * DELETE /api/v1/sales/customer-material-infos/{customerMaterialInfo}
     */
    public function destroy(CustomerMaterialInfo $customerMaterialInfo): JsonResponse
    {
        $this->service->delete($customerMaterialInfo);

        return $this->success(null, 'Customer material info deleted.');
    }

    /**
     * GET /api/v1/sales/customer-material-infos/lookup?contact_id=&product_id=
     * Cross-reference lookup — find internal product by customer material number or product_id.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'               => ['required', 'integer', $this->ownedBy('contacts')],
            'product_id'               => ['nullable', 'integer', $this->ownedBy('products')],
            'customer_material_number' => 'nullable|string|max:100',
        ]);

        $result = $this->service->lookup(
            (int) $validated['contact_id'],
            ! empty($validated['product_id']) ? (int) $validated['product_id'] : null,
            ! empty($validated['customer_material_number']) ? $validated['customer_material_number'] : null
        );

        if ($result === null) {
            return $this->notFound('No customer material info found for the given criteria.');
        }

        return $this->success($result);
    }
}
