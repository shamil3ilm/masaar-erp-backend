<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Sales\PriceOverride;
use App\Models\Sales\PriceOverridePolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PriceOverrideService
{
    private const PENDING = 'pending';

    public function getPolicies(int $organizationId): mixed
    {
        return PriceOverridePolicy::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();
    }

    public function createPolicy(array $data): PriceOverridePolicy
    {
        return PriceOverridePolicy::create($data);
    }

    public function validateOverride(int $userId, int $productId, float $originalPrice, float $overridePrice): array
    {
        if (bccomp((string) $originalPrice, '0', 4) <= 0) {
            return ['allowed' => false, 'requires_approval' => false, 'discount_percent' => '0.0000'];
        }

        if (bccomp((string) $overridePrice, '0', 4) < 0) {
            return ['allowed' => false, 'requires_approval' => false, 'discount_percent' => '0.0000'];
        }

        $discountPercent = bcmul(
            bcdiv(bcsub((string) $originalPrice, (string) $overridePrice, 4), (string) $originalPrice, 6),
            '100',
            4
        );

        $policies = PriceOverridePolicy::where('is_active', true)->get();
        $requiresApproval = false;
        $allowed = true;

        foreach ($policies as $policy) {
            if (bccomp($discountPercent, '0', 4) > 0 && !$policy->allow_discount) {
                $allowed = false;
                break;
            }
            if (bccomp($discountPercent, '0', 4) < 0 && !$policy->allow_markup) {
                $allowed = false;
                break;
            }
            if ($policy->max_discount_percent && bccomp($discountPercent, (string) $policy->max_discount_percent, 4) > 0) {
                $allowed = false;
                break;
            }
            if ($policy->requires_approval && $policy->approval_threshold_percent && bccomp($discountPercent, (string) $policy->approval_threshold_percent, 4) > 0) {
                $requiresApproval = true;
            }
        }

        return [
            'allowed' => $allowed,
            'requires_approval' => $requiresApproval,
            'discount_percent' => $discountPercent,
        ];
    }

    /**
     * Overrides of the current organization with their product and creator,
     * newest first.
     */
    public function list(int $perPage): LengthAwarePaginator
    {
        return PriceOverride::with('product', 'creator')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Record a price override under its policy.
     *
     * The policy refuses a discount or markup it does not allow and a discount
     * above its limit. The difference, discount percentage and total impact are
     * derived from the prices, and the override waits for approval when the
     * policy requires it.
     *
     * @param  array<string, mixed>  $data  validated override fields; policy_id names a policy of the organization
     *
     * @throws BusinessRuleException when the policy refuses the override
     */
    public function record(array $data, int $organizationId, int $userId): PriceOverride
    {
        $policy = PriceOverridePolicy::find($data['policy_id']);

        if ($policy !== null) {
            $this->assertPolicyAllows($policy, (float) $data['original_price'], (float) $data['override_price']);
        }

        $original = (string) $data['original_price'];
        $difference = bcsub($original, (string) $data['override_price'], 4);

        return PriceOverride::create(array_merge($data, [
            'price_difference' => $difference,
            'discount_percent' => bccomp($original, '0', 4) > 0
                ? $this->roundTo2(bcdiv(bcmul($difference, '100', 6), $original, 6))
                : 0,
            'total_impact' => $this->roundTo2(bcmul($difference, (string) $data['quantity'], 6)),
            'organization_id' => $organizationId,
            'created_by' => $userId,
            'document_id' => $data['document_id'] ?? 0,
            'line_item_id' => $data['line_item_id'] ?? 0,
            'approval_status' => $policy?->requires_approval ? self::PENDING : 'auto_approved',
        ]));
    }

    public function loadDetails(PriceOverride $override): PriceOverride
    {
        return $override->load('product', 'creator', 'approver', 'policy');
    }

    /**
     * Approve a pending override. The status is checked on the locked row, so
     * an override decided by a concurrent request is not decided again.
     *
     * @throws BusinessRuleException when the override is no longer pending
     */
    public function approve(PriceOverride $override, int $userId, ?string $notes): PriceOverride
    {
        return $this->decide($override, 'approved', $userId, $notes, 'Only pending overrides can be approved.');
    }

    /**
     * Reject a pending override, checked on the locked row like approve().
     *
     * @throws BusinessRuleException when the override is no longer pending
     */
    public function reject(PriceOverride $override, int $userId, ?string $notes): PriceOverride
    {
        return $this->decide($override, 'rejected', $userId, $notes, 'Only pending overrides can be rejected.');
    }

    public function getOverrideReport(int $organizationId, array $filters = []): array
    {
        $query = PriceOverride::where('organization_id', $organizationId);

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return [
            'total_overrides' => $query->count(),
            'total_impact' => (float) $query->sum('total_impact'),
            'by_type' => $query->select('override_type', DB::raw('count(*) as count'), DB::raw('sum(total_impact) as impact'))
                ->groupBy('override_type')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * @throws BusinessRuleException
     */
    private function assertPolicyAllows(PriceOverridePolicy $policy, float $originalPrice, float $overridePrice): void
    {
        $discountPercent = $originalPrice > 0
            ? (($originalPrice - $overridePrice) / $originalPrice) * 100
            : 0;

        if ($discountPercent > 0 && ! $policy->allow_discount) {
            throw new BusinessRuleException('Price discounts are not allowed by this policy.', 'POLICY_VIOLATION');
        }

        if ($discountPercent < 0 && ! $policy->allow_markup) {
            throw new BusinessRuleException('Price markups are not allowed by this policy.', 'POLICY_VIOLATION');
        }

        if ($policy->max_discount_percent && $discountPercent > $policy->max_discount_percent) {
            throw new BusinessRuleException(
                "Discount of {$discountPercent}% exceeds maximum allowed {$policy->max_discount_percent}%.",
                'EXCEEDS_LIMIT'
            );
        }
    }

    /**
     * @throws BusinessRuleException
     */
    private function decide(PriceOverride $override, string $status, int $userId, ?string $notes, string $refusal): PriceOverride
    {
        return $override->lockForTransition(function (PriceOverride $locked) use ($status, $userId, $notes, $refusal): PriceOverride {
            if ($locked->approval_status !== self::PENDING) {
                throw new BusinessRuleException($refusal, 'INVALID_STATUS');
            }

            $locked->update([
                'approval_status' => $status,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Round a bcmath amount half away from zero to two decimals, as the
     * percentage and impact columns store it.
     */
    private function roundTo2(string $amount): string
    {
        $half = bccomp($amount, '0', 6) < 0 ? '-0.005' : '0.005';

        return bcadd(bcadd($amount, $half, 6), '0', 2);
    }
}
