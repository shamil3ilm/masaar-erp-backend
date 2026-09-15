<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Sales\Contact;
use App\Models\Sales\CpqConfigurableProduct;
use App\Models\Sales\CpqConfiguration;
use App\Models\Sales\CpqConfigurationItem;
use App\Models\Sales\CpqConstraintRule;
use App\Models\Sales\CpqOption;
use App\Models\Sales\CpqOptionGroup;
use App\Models\Sales\CpqPricingRule;
use App\Models\Sales\Quotation;
use App\Models\User;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Configure-price-quote: configurable products with their option groups,
 * options and rules, pricing a selection and saving it as a configuration.
 *
 * Option groups, options and rules carry no organization column. They are
 * always reached through a configurable product, which is scoped to the
 * current organization, so another organization's rows are never read or
 * extended.
 */
class CpqService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly QuotationService $quotationService,
    ) {}

    // -------------------------------------------------------------------------
    // Configurable products
    // -------------------------------------------------------------------------

    public function listProducts(int $organizationId, bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return CpqConfigurableProduct::where('organization_id', $organizationId)
            ->when($activeOnly, fn ($q) => $q->active())
            ->with(['product', 'optionGroups.options'])
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  validated product fields
     */
    public function createProduct(int $organizationId, array $data): CpqConfigurableProduct
    {
        return CpqConfigurableProduct::create(array_merge($data, ['organization_id' => $organizationId]))
            ->load('product');
    }

    /**
     * A configurable product of the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function productOf(int $id): CpqConfigurableProduct
    {
        return CpqConfigurableProduct::findOrFail($id);
    }

    /**
     * A configurable product with its options and rules.
     *
     * @throws ModelNotFoundException
     */
    public function productDetails(int $id): CpqConfigurableProduct
    {
        return CpqConfigurableProduct::with([
            'product',
            'optionGroups.options',
            'pricingRules',
            'constraintRules.ifOption',
            'constraintRules.thenOption',
        ])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data  validated product fields
     */
    public function updateProduct(CpqConfigurableProduct $product, array $data): CpqConfigurableProduct
    {
        $product->update($data);

        return $product->fresh('product');
    }

    // -------------------------------------------------------------------------
    // Option groups and options
    // -------------------------------------------------------------------------

    /**
     * The product's option groups with their options, in display order.
     *
     * @throws ModelNotFoundException when the product is not the organization's
     */
    public function optionGroups(int $productId): Collection
    {
        return $this->productOf($productId)->optionGroups()->with('options')->get();
    }

    /**
     * @param  array<string, mixed>  $data  validated group fields
     */
    public function addOptionGroup(CpqConfigurableProduct $product, array $data): CpqOptionGroup
    {
        return CpqOptionGroup::create(array_merge($data, ['cpq_configurable_product_id' => $product->id]));
    }

    /**
     * An option group whose product belongs to the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function optionGroupOf(int $groupId): CpqOptionGroup
    {
        return CpqOptionGroup::whereHas('configurableProduct')->findOrFail($groupId);
    }

    /**
     * @param  array<string, mixed>  $data  validated option fields
     */
    public function addOption(CpqOptionGroup $group, array $data): CpqOption
    {
        return CpqOption::create(array_merge($data, ['cpq_option_group_id' => $group->id]));
    }

    // -------------------------------------------------------------------------
    // Pricing and constraint rules
    // -------------------------------------------------------------------------

    /**
     * @throws ModelNotFoundException when the product is not the organization's
     */
    public function pricingRules(int $productId): Collection
    {
        return $this->productOf($productId)->pricingRules()->get();
    }

    /**
     * Add a pricing rule. A rule without a condition applies to every
     * selection, stored as an empty condition.
     *
     * @param  array<string, mixed>  $data  validated rule fields
     */
    public function addPricingRule(CpqConfigurableProduct $product, array $data): CpqPricingRule
    {
        return CpqPricingRule::create(array_merge($data, [
            'condition_json' => $data['condition_json'] ?? [],
            'cpq_configurable_product_id' => $product->id,
        ]));
    }

    /**
     * @throws ModelNotFoundException when the product is not the organization's
     */
    public function constraintRules(int $productId): Collection
    {
        return $this->productOf($productId)->constraintRules()->with(['ifOption', 'thenOption'])->get();
    }

    /**
     * @param  array<string, mixed>  $data  validated rule fields; the options belong to the product
     */
    public function addConstraintRule(CpqConfigurableProduct $product, array $data): CpqConstraintRule
    {
        return CpqConstraintRule::create(array_merge($data, ['cpq_configurable_product_id' => $product->id]))
            ->load(['ifOption', 'thenOption']);
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Validate constraints and return full price breakdown for a configuration.
     *
     * @param  int[] $selectedOptionIds
     * @return array{errors: string[], price_breakdown: array, product: array}
     */
    public function configure(int $productId, array $selectedOptionIds): array
    {
        $product = $this->productOf($productId);
        $errors  = $this->validateConstraints($productId, $selectedOptionIds);

        return [
            'errors'          => $errors,
            'price_breakdown' => $this->calculatePrice($productId, $selectedOptionIds),
            'product'         => [
                'id'         => $product->id,
                'name'       => $product->name,
                'base_price' => (float) $product->base_price,
                'currency'   => $product->currency_code,
            ],
        ];
    }

    /**
     * Validate CPQ constraint rules against selected options.
     *
     * @param  int[] $selectedOptionIds
     * @return string[]  Validation error messages (empty = valid)
     */
    public function validateConstraints(int $productId, array $selectedOptionIds): array
    {
        $rules  = CpqConstraintRule::where('cpq_configurable_product_id', $productId)
            ->active()
            ->with(['ifOption', 'thenOption'])
            ->get();

        $errors = [];

        foreach ($rules as $rule) {
            $ifSelected   = in_array($rule->if_option_id, $selectedOptionIds, true);
            $thenSelected = in_array($rule->then_option_id, $selectedOptionIds, true);

            $violated = match ($rule->rule_type) {
                CpqConstraintRule::TYPE_REQUIRES => $ifSelected && ! $thenSelected,
                CpqConstraintRule::TYPE_EXCLUDES => $ifSelected && $thenSelected,
                CpqConstraintRule::TYPE_INCLUDES => ! $ifSelected && $thenSelected,
                default                          => false,
            };

            if ($violated) {
                $errors[] = $rule->error_message
                    ?? "Constraint violation: {$rule->rule_type} between option #{$rule->if_option_id} and #{$rule->then_option_id}";
            }
        }

        return $errors;
    }

    /**
     * Calculate price for a product + selected options, applying active pricing rules.
     * Only options of the product's own groups count.
     *
     * @param  int[] $selectedOptionIds
     * @return array{base_price: float, option_modifiers: array, rule_discounts: array, total_price: float}
     */
    public function calculatePrice(int $productId, array $selectedOptionIds): array
    {
        $product = $this->productOf($productId);
        $basePrice = (float) $product->base_price;

        $options         = $this->optionsOfProduct($productId, $selectedOptionIds)->filter->is_active;
        $optionModifiers = [];
        $modifierTotal   = 0.0;

        foreach ($options as $option) {
            $amount = $option->applyModifier($basePrice);
            $optionModifiers[] = [
                'option_id'   => $option->id,
                'option_name' => $option->name,
                'type'        => $option->price_modifier_type,
                'amount'      => $amount,
            ];
            $modifierTotal += $amount;
        }

        $priceBeforeRules = $basePrice + $modifierTotal;

        // Apply active pricing rules in priority order
        $rules         = CpqPricingRule::where('cpq_configurable_product_id', $productId)
            ->active()
            ->orderBy('priority')
            ->get();

        $ruleDiscounts = [];
        $totalDiscount = 0.0;
        $finalPrice    = $priceBeforeRules;

        foreach ($rules as $rule) {
            if (! $rule->isCurrentlyValid()) {
                continue;
            }

            if (! $this->pricingRuleConditionMet($rule, $selectedOptionIds)) {
                continue;
            }

            $discount = match ($rule->discount_type) {
                CpqPricingRule::DISCOUNT_PERCENTAGE     => $priceBeforeRules * ((float) $rule->discount_value / 100),
                CpqPricingRule::DISCOUNT_FIXED          => (float) $rule->discount_value,
                CpqPricingRule::DISCOUNT_PRICE_OVERRIDE => $priceBeforeRules - (float) $rule->discount_value,
                default                                 => 0.0,
            };

            $ruleDiscounts[] = [
                'rule_id'       => $rule->id,
                'rule_name'     => $rule->rule_name,
                'discount_type' => $rule->discount_type,
                'discount'      => $discount,
            ];

            $totalDiscount += $discount;

            // Price override rules replace the final price
            if ($rule->discount_type === CpqPricingRule::DISCOUNT_PRICE_OVERRIDE) {
                $finalPrice    = (float) $rule->discount_value;
                $totalDiscount = $priceBeforeRules - $finalPrice;
                break; // Only first matching override applies
            }
        }

        if ($totalDiscount > 0 && $ruleDiscounts !== []) {
            $finalPrice = max(0.0, $priceBeforeRules - $totalDiscount);
        }

        return [
            'base_price'       => $basePrice,
            'option_modifiers' => $optionModifiers,
            'modifier_total'   => $modifierTotal,
            'price_before_rules' => $priceBeforeRules,
            'rule_discounts'   => $ruleDiscounts,
            'total_discount'   => $totalDiscount,
            'total_price'      => round($finalPrice, 4),
        ];
    }

    /**
     * Configurations of the organization, newest first, with the reference
     * columns of their contact.
     */
    public function listConfigurations(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return CpqConfiguration::where('organization_id', $organizationId)
            ->with(['configurableProduct', 'contact:'.implode(',', Contact::REFERENCE_COLUMNS), 'items.option'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A configuration of the current organization with its product, items,
     * quotation and the reference columns of its contact.
     *
     * @throws ModelNotFoundException
     */
    public function configurationDetails(int $id): CpqConfiguration
    {
        return CpqConfiguration::with([
            'configurableProduct.product',
            'contact:'.implode(',', Contact::REFERENCE_COLUMNS),
            'items.option',
            'items.optionGroup',
            'quotation',
        ])->findOrFail($id);
    }

    /**
     * A configuration of the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function configurationOf(int $id): CpqConfiguration
    {
        return CpqConfiguration::findOrFail($id);
    }

    /**
     * Persist a CPQ configuration.
     *
     * @param  array{
     *     organization_id: int,
     *     cpq_configurable_product_id: int,
     *     contact_id?: int,
     *     selected_options: array<array{option_id: int, option_group_id: int, quantity?: float}>,
     *     currency_code?: string,
     *     created_by?: int
     * } $data
     */
    public function saveConfiguration(array $data): CpqConfiguration
    {
        return DB::transaction(function () use ($data): CpqConfiguration {
            $product = $this->productOf($data['cpq_configurable_product_id']);

            $selectedOptionIds = array_column($data['selected_options'], 'option_id');
            $priceBreakdown    = $this->calculatePrice($product->id, $selectedOptionIds);
            $options           = $this->optionsOfProduct($product->id, $selectedOptionIds)->keyBy('id');

            $validUntil = now()->addDays($product->configuration_validity_days)->toDateString();
            $code       = $this->numberGenerator->generate('CPQ');

            $configuration = CpqConfiguration::create([
                'organization_id'            => $data['organization_id'],
                'cpq_configurable_product_id' => $product->id,
                'contact_id'                 => $data['contact_id'] ?? null,
                'configuration_code'         => $code,
                'status'                     => CpqConfiguration::STATUS_VALID,
                'total_price'               => $priceBreakdown['total_price'],
                'currency_code'              => $data['currency_code'] ?? $product->currency_code,
                'valid_until'               => $validUntil,
                'created_by'                => $data['created_by'] ?? null,
            ]);

            foreach ($data['selected_options'] as $selected) {
                $option    = $options->get($selected['option_id'])
                    ?? throw (new ModelNotFoundException())->setModel(CpqOption::class, [$selected['option_id']]);
                $quantity  = (float) ($selected['quantity'] ?? 1);
                $unitPrice = $option->applyModifier((float) $product->base_price);
                $lineTotal = $unitPrice * $quantity;

                CpqConfigurationItem::create([
                    'cpq_configuration_id' => $configuration->id,
                    'cpq_option_group_id'  => $selected['option_group_id'],
                    'cpq_option_id'        => $option->id,
                    'quantity'             => $quantity,
                    'unit_price'           => $unitPrice,
                    'line_total'           => $lineTotal,
                ]);
            }

            return $configuration->load(['items.option', 'items.optionGroup']);
        });
    }

    /**
     * Convert a CPQ configuration to a draft quotation for its contact, with
     * one line for the configured product at the configuration's price.
     *
     * The quotation is created by QuotationService, so it is numbered, named
     * after its customer and totalled like any other quotation. The
     * configuration is re-read under a lock and checked there, so two requests
     * cannot both convert it; the quotation and the configuration's new status
     * are written in one transaction.
     *
     * @param  array{
     *     salesperson_id?: int,
     *     notes?: string,
     *     terms_and_conditions?: string
     * } $quotationData
     *
     * @throws BusinessRuleException when the configuration can no longer be converted or has no contact
     */
    public function convertToQuotation(CpqConfiguration $config, array $quotationData, User $user): Quotation
    {
        return DB::transaction(function () use ($config, $quotationData, $user): Quotation {
            $config = CpqConfiguration::query()->lockForUpdate()->findOrFail($config->id);

            if (! $config->canConvert()) {
                throw new BusinessRuleException(
                    "Configuration {$config->configuration_code} cannot be converted.",
                    'INVALID_STATUS'
                );
            }

            if ($config->contact_id === null) {
                throw new BusinessRuleException(
                    'A configuration needs a contact before it can become a quotation.',
                    'CONTACT_REQUIRED'
                );
            }

            $product = $this->productOf($config->cpq_configurable_product_id);
            $today   = now()->toDateString();

            $quotation = $this->quotationService->create([
                'customer_id'          => $config->contact_id,
                'quotation_date'       => $today,
                'valid_until'          => $config->valid_until?->toDateString() ?? $today,
                'currency_code'        => $config->currency_code,
                'salesperson_id'       => $quotationData['salesperson_id'] ?? null,
                'notes'                => $quotationData['notes'] ?? null,
                'terms_and_conditions' => $quotationData['terms_and_conditions'] ?? null,
                'lines'                => [[
                    'product_id'  => $product->product_id,
                    'description' => "Configured: {$product->name} ({$config->configuration_code})",
                    'quantity'    => 1,
                    'unit_price'  => $config->total_price,
                ]],
            ], $user, null);

            $config->update([
                'status'       => CpqConfiguration::STATUS_CONVERTED,
                'quotation_id' => $quotation->id,
            ]);

            return $quotation;
        });
    }

    /**
     * Get all active configurable products for an organisation.
     */
    public function getConfigurableProducts(int $organizationId): Collection
    {
        return CpqConfigurableProduct::where('organization_id', $organizationId)
            ->active()
            ->with(['product', 'optionGroups.options'])
            ->get();
    }

    /**
     * The given options that belong to one of the product's option groups.
     *
     * @param  int[]  $optionIds
     */
    private function optionsOfProduct(int $productId, array $optionIds): Collection
    {
        return CpqOption::whereIn('id', $optionIds)
            ->whereHas('group', fn ($q) => $q->where('cpq_configurable_product_id', $productId))
            ->get();
    }

    /**
     * Check whether a pricing rule's condition JSON is satisfied by the selected options.
     * The condition JSON supports: {"required_options": [1, 2], "min_options": 3}
     *
     * @param  int[] $selectedOptionIds
     */
    private function pricingRuleConditionMet(CpqPricingRule $rule, array $selectedOptionIds): bool
    {
        $condition = $rule->condition_json;

        if (empty($condition)) {
            return true;
        }

        // required_options: all listed option IDs must be selected
        if (isset($condition['required_options']) && is_array($condition['required_options'])) {
            foreach ($condition['required_options'] as $requiredId) {
                if (! in_array((int) $requiredId, $selectedOptionIds, true)) {
                    return false;
                }
            }
        }

        // any_of_options: at least one of the listed option IDs must be selected
        if (isset($condition['any_of_options']) && is_array($condition['any_of_options'])) {
            $found = false;
            foreach ($condition['any_of_options'] as $anyId) {
                if (in_array((int) $anyId, $selectedOptionIds, true)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        // min_options: minimum number of selected options
        if (isset($condition['min_options']) && count($selectedOptionIds) < (int) $condition['min_options']) {
            return false;
        }

        return true;
    }
}
