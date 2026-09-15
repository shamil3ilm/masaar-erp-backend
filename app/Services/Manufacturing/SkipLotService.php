<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\SkipLotDecision;
use App\Models\Manufacturing\SkipLotSamplingPlan;
use App\Models\Sales\Contact;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SkipLotService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = SkipLotSamplingPlan::where('organization_id', $orgId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['plan_type'])) {
            $query->where('plan_type', $filters['plan_type']);
        }

        return $query->orderBy('plan_code')->paginate($filters['per_page'] ?? 20);
    }

    public function createPlan(int $orgId, array $data): SkipLotSamplingPlan
    {
        return SkipLotSamplingPlan::create(array_merge($data, ['organization_id' => $orgId]));
    }

    /**
     * One of the organization's sampling plans; a missing id is a 404.
     *
     * @param  array<int, string>  $with
     */
    public function findPlan(int $orgId, int $id, array $with = []): SkipLotSamplingPlan
    {
        return SkipLotSamplingPlan::where('organization_id', $orgId)
            ->with($with)
            ->findOrFail($id);
    }

    public function deletePlan(SkipLotSamplingPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * One of the organization's skip lot decisions; a missing id is a 404.
     */
    public function findDecision(int $orgId, int $id): SkipLotDecision
    {
        return SkipLotDecision::where('organization_id', $orgId)->findOrFail($id);
    }

    public function updatePlan(SkipLotSamplingPlan $plan, array $data): SkipLotSamplingPlan
    {
        $plan->update($data);
        return $plan->fresh();
    }

    public function getOrCreateDecision(int $vendorId, int $productId, int $planId, int $orgId): SkipLotDecision
    {
        return SkipLotDecision::firstOrCreate(
            [
                'organization_id'            => $orgId,
                'vendor_id'                  => $vendorId,
                'product_id'                 => $productId,
                'skip_lot_sampling_plan_id'  => $planId,
            ],
            [
                'current_level'          => SkipLotDecision::LEVEL_NORMAL,
                'lots_inspected_at_level' => 0,
                'consecutive_accepted'   => 0,
                'consecutive_rejected'   => 0,
            ]
        );
    }

    public function shouldInspect(SkipLotDecision $decision): bool
    {
        $plan = $decision->plan;

        if ($decision->current_level === SkipLotDecision::LEVEL_REJECTED) {
            return true;
        }

        if ($decision->current_level === SkipLotDecision::LEVEL_NORMAL) {
            return true;
        }

        if ($decision->current_level === SkipLotDecision::LEVEL_TIGHTENED) {
            return true;
        }

        if ($decision->current_level === SkipLotDecision::LEVEL_SKIP_LOT) {
            $frequency = $plan->inspection_frequency;
            if ($frequency <= 1) {
                return true;
            }
            // Inspect every Nth lot
            return ($decision->lots_inspected_at_level % $frequency) === 0;
        }

        if ($decision->current_level === SkipLotDecision::LEVEL_REDUCED) {
            return true;
        }

        return true;
    }

    /**
     * Count an inspection result and re-evaluate the decision's level.
     *
     * The counters are read and written on the locked decision, so a result
     * recorded through a stale copy is added to the current counts instead of
     * overwriting them.
     */
    public function recordResult(SkipLotDecision $decision, bool $accepted, int $inspectionLotId): void
    {
        $decision->lockForTransition(function (SkipLotDecision $decision) use ($accepted, $inspectionLotId): void {
            $this->applyResult($decision, $accepted, $inspectionLotId);
        });
    }

    private function applyResult(SkipLotDecision $decision, bool $accepted, int $inspectionLotId): void
    {
        $plan = $decision->plan()->firstOrFail();

        $newConsecutiveAccepted = $accepted ? $decision->consecutive_accepted + 1 : 0;
        $newConsecutiveRejected = $accepted ? 0 : $decision->consecutive_rejected + 1;
        $newLotsInspected = $decision->lots_inspected_at_level + 1;
        $newLevel = $decision->current_level;

        // Level promotion (downgrade strictness)
        if ($accepted) {
            $newLevel = $this->evaluateLevelPromotion($decision->current_level, $plan, $newConsecutiveAccepted);
        }

        // Level demotion (increase strictness)
        if (!$accepted) {
            $newLevel = $this->evaluateLevelDemotion($decision->current_level, $plan, $newConsecutiveRejected);
        }

        // Reset counters if level changed
        if ($newLevel !== $decision->current_level) {
            $newLotsInspected = 0;
            $newConsecutiveAccepted = 0;
            $newConsecutiveRejected = 0;
        }

        $decision->update([
            'current_level'           => $newLevel,
            'lots_inspected_at_level' => $newLotsInspected,
            'consecutive_accepted'    => $newConsecutiveAccepted,
            'consecutive_rejected'    => $newConsecutiveRejected,
            'last_inspection_lot_id'  => $inspectionLotId,
            'last_evaluated_at'       => Carbon::now(),
        ]);
    }

    public function getDecisions(int $orgId, array $filters = []): LengthAwarePaginator
    {
        // The vendor is shown by reference, so its contact and tax details are never embedded.
        $query = SkipLotDecision::with(['plan', 'vendor:'.implode(',', Contact::REFERENCE_COLUMNS), 'product'])
            ->where('organization_id', $orgId);

        if (isset($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['current_level'])) {
            $query->where('current_level', $filters['current_level']);
        }

        return $query->orderByDesc('last_evaluated_at')->paginate($filters['per_page'] ?? 20);
    }

    private function evaluateLevelPromotion(string $currentLevel, SkipLotSamplingPlan $plan, int $consecutiveAccepted): string
    {
        return match ($currentLevel) {
            SkipLotDecision::LEVEL_TIGHTENED => $plan->switch_rule_tightened_to_rejected !== null
                ? $currentLevel
                : ($consecutiveAccepted >= 5 ? SkipLotDecision::LEVEL_NORMAL : $currentLevel),
            SkipLotDecision::LEVEL_NORMAL => $consecutiveAccepted >= 10
                ? SkipLotDecision::LEVEL_REDUCED
                : $currentLevel,
            SkipLotDecision::LEVEL_REDUCED => $consecutiveAccepted >= 20
                ? SkipLotDecision::LEVEL_SKIP_LOT
                : $currentLevel,
            default => $currentLevel,
        };
    }

    private function evaluateLevelDemotion(string $currentLevel, SkipLotSamplingPlan $plan, int $consecutiveRejected): string
    {
        return match ($currentLevel) {
            SkipLotDecision::LEVEL_SKIP_LOT => $consecutiveRejected >= 1
                ? SkipLotDecision::LEVEL_NORMAL
                : $currentLevel,
            SkipLotDecision::LEVEL_REDUCED => $plan->switch_rule_reduced_to_normal !== null && $consecutiveRejected >= $plan->switch_rule_reduced_to_normal
                ? SkipLotDecision::LEVEL_NORMAL
                : $currentLevel,
            SkipLotDecision::LEVEL_NORMAL => $plan->switch_rule_normal_to_tightened !== null && $consecutiveRejected >= $plan->switch_rule_normal_to_tightened
                ? SkipLotDecision::LEVEL_TIGHTENED
                : $currentLevel,
            SkipLotDecision::LEVEL_TIGHTENED => $plan->switch_rule_tightened_to_rejected !== null && $consecutiveRejected >= $plan->switch_rule_tightened_to_rejected
                ? SkipLotDecision::LEVEL_REJECTED
                : $currentLevel,
            default => $currentLevel,
        };
    }
}
