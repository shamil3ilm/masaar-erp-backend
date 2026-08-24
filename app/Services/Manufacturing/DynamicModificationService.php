<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\DynamicModificationRule;
use App\Models\Manufacturing\InspectionStageLog;
use Illuminate\Support\Facades\DB;

class DynamicModificationService
{
    /**
     * Sample size multipliers per stage.
     */
    private const SAMPLE_MODIFIERS = [
        InspectionStageLog::STAGE_TIGHTENED => 1.5,
        InspectionStageLog::STAGE_NORMAL    => 1.0,
        InspectionStageLog::STAGE_REDUCED   => 0.4,
        InspectionStageLog::STAGE_SKIP      => 0.0,
    ];

    /**
     * Evaluate an inspection result, transition stage if necessary, and
     * return the new stage with the recommended sample size modifier.
     *
     * @return array{current_stage: string, previous_stage: string, recommended_sample_modifier: float, consecutive_pass: int, consecutive_fail: int}
     */
    public function evaluateInspectionResult(
        int $organizationId,
        int $ruleId,
        int $productId,
        ?int $supplierId,
        bool $passed,
    ): array {
        return DB::transaction(function () use ($organizationId, $ruleId, $productId, $supplierId, $passed): array {
            $rule = DynamicModificationRule::where('organization_id', $organizationId)
                ->where('id', $ruleId)
                ->lockForUpdate()
                ->firstOrFail();

            $log = InspectionStageLog::where('organization_id', $organizationId)
                ->where('rule_id', $ruleId)
                ->where('product_id', $productId)
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->firstOrNew([
                    'organization_id' => $organizationId,
                    'rule_id'         => $ruleId,
                    'product_id'      => $productId,
                    'supplier_id'     => $supplierId,
                    'current_stage'   => InspectionStageLog::STAGE_NORMAL,
                    'consecutive_pass' => 0,
                    'consecutive_fail' => 0,
                ]);

            $previousStage = $log->current_stage ?? InspectionStageLog::STAGE_NORMAL;

            if ($passed) {
                $log->consecutive_pass = ($log->consecutive_pass ?? 0) + 1;
                $log->consecutive_fail = 0;
            } else {
                $log->consecutive_fail = ($log->consecutive_fail ?? 0) + 1;
                $log->consecutive_pass = 0;
            }

            $newStage = $this->resolveStageTransition($rule, $log, $previousStage);
            $log->current_stage     = $newStage;
            $log->last_evaluated_at = now();
            $log->save();

            return [
                'current_stage'              => $newStage,
                'previous_stage'             => $previousStage,
                'recommended_sample_modifier' => self::SAMPLE_MODIFIERS[$newStage],
                'consecutive_pass'           => $log->consecutive_pass,
                'consecutive_fail'           => $log->consecutive_fail,
            ];
        });
    }

    public function getCurrentStage(
        int $organizationId,
        int $ruleId,
        int $productId,
        ?int $supplierId,
    ): ?InspectionStageLog {
        return InspectionStageLog::where('organization_id', $organizationId)
            ->where('rule_id', $ruleId)
            ->where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->first();
    }

    public function listRules(int $organizationId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DynamicModificationRule::where('organization_id', $organizationId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function createRule(int $organizationId, array $data, int $userId): DynamicModificationRule
    {
        return DynamicModificationRule::create([
            ...$data,
            'organization_id' => $organizationId,
            'created_by'      => $userId,
        ]);
    }

    public function findRule(int $organizationId, string $uuid): DynamicModificationRule
    {
        return DynamicModificationRule::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveStageTransition(
        DynamicModificationRule $rule,
        InspectionStageLog $log,
        string $currentStage,
    ): string {
        return match ($currentStage) {
            InspectionStageLog::STAGE_NORMAL => $this->transitionFromNormal($rule, $log),
            InspectionStageLog::STAGE_TIGHTENED => $this->transitionFromTightened($rule, $log),
            InspectionStageLog::STAGE_REDUCED => $this->transitionFromReduced($rule, $log),
            // Skip: any failure reinstates normal
            InspectionStageLog::STAGE_SKIP => $log->consecutive_fail > 0
                ? InspectionStageLog::STAGE_NORMAL
                : InspectionStageLog::STAGE_SKIP,
            default => $currentStage,
        };
    }

    private function transitionFromNormal(DynamicModificationRule $rule, InspectionStageLog $log): string
    {
        if ($log->consecutive_fail >= $rule->tighten_consecutive_fails) {
            return InspectionStageLog::STAGE_TIGHTENED;
        }

        if ($log->consecutive_pass >= $rule->reduce_after_consecutive_pass) {
            return InspectionStageLog::STAGE_REDUCED;
        }

        return InspectionStageLog::STAGE_NORMAL;
    }

    private function transitionFromTightened(DynamicModificationRule $rule, InspectionStageLog $log): string
    {
        // Reinstate to normal after enough consecutive passes while tightened
        if ($log->consecutive_pass >= $rule->reinstate_after_tightened_fail) {
            return InspectionStageLog::STAGE_NORMAL;
        }

        return InspectionStageLog::STAGE_TIGHTENED;
    }

    private function transitionFromReduced(DynamicModificationRule $rule, InspectionStageLog $log): string
    {
        // Any failure pushes back to normal
        if ($log->consecutive_fail > 0) {
            return InspectionStageLog::STAGE_NORMAL;
        }

        if ($log->consecutive_pass >= $rule->skip_after_reduced_pass) {
            return InspectionStageLog::STAGE_SKIP;
        }

        return InspectionStageLog::STAGE_REDUCED;
    }
}
