<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\DeliverySplitRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Delivery split rules: which customers' deliveries are split, by what
 * criterion, and which rules apply to a given customer.
 */
class DeliverySplitService
{
    /**
     * Rules of the current organization, latest first, narrowed to active ones
     * and to a split criterion when those filters are set.
     */
    public function list(bool $activeOnly, ?string $splitCriteria, int $perPage): LengthAwarePaginator
    {
        return DeliverySplitRule::latest()
            ->when($activeOnly, fn ($q) => $q->active())
            ->when($splitCriteria !== null, fn ($q) => $q->where('split_criteria', $splitCriteria))
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  validated rule fields
     */
    public function create(int $organizationId, array $data): DeliverySplitRule
    {
        return DeliverySplitRule::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    /**
     * @param  array<string, mixed>  $data  validated rule fields
     */
    public function update(DeliverySplitRule $rule, array $data): DeliverySplitRule
    {
        $rule->update($data);

        return $rule->fresh();
    }

    public function delete(DeliverySplitRule $rule): void
    {
        $rule->delete();
    }

    /**
     * The organization's active rules that apply to the customer, directly,
     * through its customer group or to all customers, in the shape the apply
     * endpoint returns.
     *
     * @return list<array{rule_id: int, rule_name: string, split_criteria: string, allow_partial_delivery: bool, minimum_delivery_quantity_pct: float}>
     */
    public function applicableRules(int $organizationId, int $customerId, ?int $customerGroupId): array
    {
        return DeliverySplitRule::active()
            ->where('organization_id', $organizationId)
            ->get()
            ->filter(fn (DeliverySplitRule $rule) => $rule->appliesTo($customerId, $customerGroupId))
            ->map(fn (DeliverySplitRule $rule) => [
                'rule_id'                       => $rule->id,
                'rule_name'                     => $rule->rule_name,
                'split_criteria'                => $rule->split_criteria,
                'allow_partial_delivery'        => $rule->allow_partial_delivery,
                'minimum_delivery_quantity_pct' => (float) $rule->minimum_delivery_quantity_pct,
            ])
            ->values()
            ->all();
    }
}
