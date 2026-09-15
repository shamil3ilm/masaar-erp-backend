<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\CpqService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class CpqController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(
        private readonly CpqService $cpqService
    ) {}

    // -------------------------------------------------------------------------
    // Configurable Products
    // -------------------------------------------------------------------------

    public function products(Request $request): JsonResponse
    {
        return $this->paginated($this->cpqService->listProducts(
            $this->organizationId($request),
            $request->boolean('active_only', true),
            $request->integer('per_page', 15)
        ));
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'                   => ['required', 'integer', $this->ownedBy('products')],
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string',
            'base_price'                   => 'required|numeric|min:0',
            'currency_code'                => 'required|string|size:3',
            'configuration_validity_days'  => 'nullable|integer|min:1|max:3650',
            'is_active'                    => 'nullable|boolean',
        ]);

        return $this->created($this->cpqService->createProduct($this->organizationId($request), $validated));
    }

    public function showProduct(int $id): JsonResponse
    {
        return $this->success($this->cpqService->productDetails($id));
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product   = $this->cpqService->productOf($id);
        $validated = $request->validate([
            'name'                         => 'sometimes|string|max:255',
            'description'                  => 'nullable|string',
            'base_price'                   => 'sometimes|numeric|min:0',
            'currency_code'                => 'sometimes|string|size:3',
            'configuration_validity_days'  => 'nullable|integer|min:1|max:3650',
            'is_active'                    => 'nullable|boolean',
        ]);

        return $this->success($this->cpqService->updateProduct($product, $validated));
    }

    // -------------------------------------------------------------------------
    // Option Groups & Options
    // -------------------------------------------------------------------------

    public function optionGroups(int $productId): JsonResponse
    {
        return $this->success($this->cpqService->optionGroups($productId));
    }

    public function storeOptionGroup(Request $request, int $productId): JsonResponse
    {
        $product = $this->cpqService->productOf($productId);

        $validated = $request->validate([
            'group_code'     => 'required|string|max:30',
            'name'           => 'required|string|max:255',
            'selection_type' => 'nullable|in:single,multi',
            'is_required'    => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);

        return $this->created($this->cpqService->addOptionGroup($product, $validated));
    }

    public function storeOption(Request $request, int $groupId): JsonResponse
    {
        $group = $this->cpqService->optionGroupOf($groupId);

        $validated = $request->validate([
            'option_code'          => 'required|string|max:30',
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'price_modifier_type'  => 'nullable|in:fixed,percentage,none',
            'price_modifier_value' => 'nullable|numeric',
            'is_default'           => 'nullable|boolean',
            'is_active'            => 'nullable|boolean',
            'sort_order'           => 'nullable|integer',
            'linked_product_id'    => ['nullable', 'integer', $this->ownedBy('products')],
        ]);

        return $this->created($this->cpqService->addOption($group, $validated));
    }

    // -------------------------------------------------------------------------
    // Pricing Rules
    // -------------------------------------------------------------------------

    public function pricingRules(int $productId): JsonResponse
    {
        return $this->success($this->cpqService->pricingRules($productId));
    }

    public function storePricingRule(Request $request, int $productId): JsonResponse
    {
        $product = $this->cpqService->productOf($productId);

        $validated = $request->validate([
            'rule_name'      => 'required|string|max:255',
            'condition_json' => 'nullable|array',
            'discount_type'  => 'required|in:percentage,fixed,price_override',
            'discount_value' => 'required|numeric|min:0',
            'priority'       => 'nullable|integer|min:0|max:9999',
            'valid_from'     => 'nullable|date',
            'valid_to'       => 'nullable|date|after_or_equal:valid_from',
            'is_active'      => 'nullable|boolean',
        ]);

        return $this->created($this->cpqService->addPricingRule($product, $validated));
    }

    // -------------------------------------------------------------------------
    // Constraint Rules
    // -------------------------------------------------------------------------

    public function constraintRules(int $productId): JsonResponse
    {
        return $this->success($this->cpqService->constraintRules($productId));
    }

    public function storeConstraintRule(Request $request, int $productId): JsonResponse
    {
        $product = $this->cpqService->productOf($productId);

        $validated = $request->validate([
            'rule_type'      => 'required|in:requires,excludes,includes',
            'if_option_id'   => ['nullable', 'integer', $this->optionOf($product->id)],
            'then_option_id' => ['nullable', 'integer', $this->optionOf($product->id)],
            'error_message'  => 'nullable|string|max:200',
            'is_active'      => 'nullable|boolean',
        ]);

        return $this->created($this->cpqService->addConstraintRule($product, $validated));
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function configure(Request $request): JsonResponse
    {
        $productId = $request->integer('product_id');

        $validated = $request->validate([
            'product_id'         => ['required', 'integer', $this->ownedBy('cpq_configurable_products')],
            'selected_options'   => 'required|array|min:1',
            'selected_options.*' => ['integer', $this->optionOf($productId)],
        ]);

        return $this->success($this->cpqService->configure(
            (int) $validated['product_id'],
            array_map('intval', $validated['selected_options'])
        ));
    }

    public function configurations(Request $request): JsonResponse
    {
        return $this->paginated($this->cpqService->listConfigurations(
            $this->organizationId($request),
            $request->integer('per_page', 15)
        ));
    }

    public function saveConfiguration(Request $request): JsonResponse
    {
        $productId = $request->integer('cpq_configurable_product_id');

        $validated = $request->validate([
            'cpq_configurable_product_id'        => ['required', 'integer', $this->ownedBy('cpq_configurable_products')],
            'contact_id'                         => ['nullable', 'integer', $this->ownedBy('contacts')],
            'currency_code'                      => 'nullable|string|size:3',
            'selected_options'                   => 'required|array|min:1',
            'selected_options.*.option_id'       => ['required', 'integer', $this->optionOf($productId)],
            'selected_options.*.option_group_id' => [
                'required', 'integer', Rule::exists('cpq_option_groups', 'id')->where('cpq_configurable_product_id', $productId),
            ],
            'selected_options.*.quantity'        => 'nullable|numeric|min:0.0001',
        ]);

        $config = $this->cpqService->saveConfiguration(array_merge(
            $validated,
            [
                'organization_id' => $this->organizationId($request),
                'created_by'      => auth()->id(),
            ]
        ));

        return $this->created($config);
    }

    public function showConfiguration(int $id): JsonResponse
    {
        return $this->success($this->cpqService->configurationDetails($id));
    }

    public function convertToQuotation(Request $request, int $id): JsonResponse
    {
        $config    = $this->cpqService->configurationOf($id);
        $validated = $request->validate([
            'salesperson_id'       => ['nullable', 'integer', $this->ownedBy('users')],
            'notes'                => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
        ]);

        try {
            $quotation = $this->cpqService->convertToQuotation($config, $validated, $request->user());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->created($quotation);
    }

    /**
     * An exists rule for an option of one of the product's option groups. The
     * product itself is checked against the organization separately.
     */
    private function optionOf(int $productId): Exists
    {
        return Rule::exists('cpq_options', 'id')->where(
            fn (Builder $query) => $query->whereIn(
                'cpq_option_group_id',
                fn (Builder $groups) => $groups->select('id')->from('cpq_option_groups')->where('cpq_configurable_product_id', $productId)
            )
        );
    }
}
