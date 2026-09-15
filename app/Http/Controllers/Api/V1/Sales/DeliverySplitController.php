<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\DeliverySplitRule;
use App\Services\Sales\DeliverySplitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliverySplitController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly DeliverySplitService $deliverySplitService
    ) {}

    /**
     * GET /api/v1/sales/delivery-split-rules
     * List all delivery split rules.
     */
    public function index(Request $request): JsonResponse
    {
        $rules = $this->deliverySplitService->list(
            $request->boolean('active_only'),
            $request->has('split_criteria') ? (string) $request->string('split_criteria') : null,
            $request->integer('per_page', 15)
        );

        return $this->paginated($rules);
    }

    /**
     * POST /api/v1/sales/delivery-split-rules
     * Create a new delivery split rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_name'                      => 'required|string|max:100',
            'split_criteria'                 => 'required|in:warehouse,delivery_date,route,weight,volume',
            'applies_to'                     => 'required|in:all_customers,customer_group,specific_customer',
            'applies_to_id'                  => 'nullable|integer',
            'allow_partial_delivery'         => 'nullable|boolean',
            'minimum_delivery_quantity_pct'  => 'nullable|numeric|min:0|max:100',
            'is_active'                      => 'nullable|boolean',
        ]);

        $rule = $this->deliverySplitService->create((int) $this->organizationId($request), $validated);

        return $this->success($rule, 'Delivery split rule created.', 201);
    }

    /**
     * GET /api/v1/sales/delivery-split-rules/{deliverySplitRule}
     */
    public function show(DeliverySplitRule $deliverySplitRule): JsonResponse
    {
        return $this->success($deliverySplitRule);
    }

    /**
     * PUT /api/v1/sales/delivery-split-rules/{deliverySplitRule}
     */
    public function update(Request $request, DeliverySplitRule $deliverySplitRule): JsonResponse
    {
        $validated = $request->validate([
            'rule_name'                      => 'sometimes|string|max:100',
            'split_criteria'                 => 'sometimes|in:warehouse,delivery_date,route,weight,volume',
            'applies_to'                     => 'sometimes|in:all_customers,customer_group,specific_customer',
            'applies_to_id'                  => 'nullable|integer',
            'allow_partial_delivery'         => 'nullable|boolean',
            'minimum_delivery_quantity_pct'  => 'nullable|numeric|min:0|max:100',
            'is_active'                      => 'nullable|boolean',
        ]);

        return $this->success(
            $this->deliverySplitService->update($deliverySplitRule, $validated),
            'Delivery split rule updated.'
        );
    }

    /**
     * DELETE /api/v1/sales/delivery-split-rules/{deliverySplitRule}
     */
    public function destroy(DeliverySplitRule $deliverySplitRule): JsonResponse
    {
        $this->deliverySplitService->delete($deliverySplitRule);

        return $this->success(null, 'Delivery split rule deleted.');
    }

    /**
     * POST /api/v1/sales/delivery-split-rules/apply
     * Apply active delivery split rules to a sales order or shipment.
     *
     * Returns a breakdown of how lines should be split for delivery.
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type'       => 'required|in:sales_order,shipment',
            'source_id'         => 'required|integer',
            'customer_id'       => ['required', 'integer', $this->ownedBy('contacts')],
            'customer_group_id' => 'nullable|integer',
        ]);

        $rules = $this->deliverySplitService->applicableRules(
            (int) $this->organizationId($request),
            (int) $validated['customer_id'],
            isset($validated['customer_group_id']) ? (int) $validated['customer_group_id'] : null
        );

        if ($rules === []) {
            return $this->success([
                'source_type'      => $validated['source_type'],
                'source_id'        => $validated['source_id'],
                'applicable_rules' => [],
                'split_required'   => false,
                'message'          => 'No applicable delivery split rules found.',
            ]);
        }

        return $this->success([
            'source_type'      => $validated['source_type'],
            'source_id'        => $validated['source_id'],
            'applicable_rules' => $rules,
            'split_required'   => true,
        ], 'Delivery split rules applied.');
    }
}
