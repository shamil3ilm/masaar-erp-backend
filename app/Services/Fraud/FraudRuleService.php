<?php

declare(strict_types=1);

namespace App\Services\Fraud;

use App\Models\Fraud\FraudRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The fraud rules an organization maintains for FraudRuleEngine to evaluate.
 */
final class FraudRuleService
{
    /**
     * The organization's rules by name.
     *
     * @param  array{rule_type?: string, entity_type?: string, active_only?: bool}  $filters
     */
    public function paginateRules(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return FraudRule::where('organization_id', $organizationId)
            ->orderBy('name')
            ->when(isset($filters['rule_type']), fn ($q) => $q->where('rule_type', $filters['rule_type']))
            ->when(isset($filters['entity_type']), fn ($q) => $q->where('entity_type', $filters['entity_type']))
            ->when(! empty($filters['active_only']), fn ($q) => $q->active())
            ->paginate($perPage);
    }

    public function create(array $data, int $organizationId, int $userId): FraudRule
    {
        return FraudRule::create(array_merge($data, [
            'organization_id' => $organizationId,
            'created_by' => $userId,
        ]));
    }

    /**
     * Switches the rule on or off. The flip reads the locked row, so two
     * toggles at once end where they started instead of both writing the
     * same value.
     */
    public function toggle(int $organizationId, int $id): FraudRule
    {
        return DB::transaction(function () use ($organizationId, $id): FraudRule {
            $rule = FraudRule::where('organization_id', $organizationId)
                ->lockForUpdate()
                ->findOrFail($id);

            $rule->update(['is_active' => ! $rule->is_active]);

            return $rule->fresh();
        });
    }

    /**
     * Adds each default rule the organization does not already have by name.
     *
     * @return int how many rules were added
     */
    public function seedDefaults(int $organizationId, int $userId): int
    {
        return DB::transaction(function () use ($organizationId, $userId): int {
            $existing = FraudRule::where('organization_id', $organizationId)->pluck('name')->all();
            $created = 0;

            foreach (FraudRuleTemplates::defaults() as $template) {
                if (in_array($template['name'], $existing, true)) {
                    continue;
                }

                $this->create($template, $organizationId, $userId);
                $existing[] = $template['name'];
                $created++;
            }

            return $created;
        });
    }
}
