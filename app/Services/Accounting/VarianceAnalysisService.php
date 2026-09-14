<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\VarianceAnalysisItem;
use App\Models\Accounting\VarianceAnalysisRun;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class VarianceAnalysisService
{
    // ----------------------------------------------------------------
    // Run management
    // ----------------------------------------------------------------

    /**
     * Runs of the current organization, newest first.
     *
     * @param  array{period?: ?int, fiscal_year?: ?int, status?: mixed}  $filters
     *         A filter applies only when it is set and not null.
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = VarianceAnalysisRun::orderBy('id', 'desc');

        foreach (['period', 'fiscal_year', 'status'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * A run with the user who started it; 404 when it is not visible.
     */
    public function findRun(int $id): VarianceAnalysisRun
    {
        return VarianceAnalysisRun::with('runBy:id,name')->findOrFail($id);
    }

    /**
     * Trigger a variance analysis run for the given period/year/type.
     * For production_order run type, iterates completed work orders in the period.
     */
    public function runAnalysis(
        int $period,
        int $year,
        string $runType,
        int $orgId,
        int $userId
    ): VarianceAnalysisRun {
        $run = VarianceAnalysisRun::create([
            'organization_id' => $orgId,
            'period'          => $period,
            'fiscal_year'     => $year,
            'run_type'        => $runType,
            'status'          => VarianceAnalysisRun::STATUS_RUNNING,
            'run_by'          => $userId,
        ]);

        try {
            DB::transaction(function () use ($run, $period, $year, $runType, $orgId): void {
                match ($runType) {
                    VarianceAnalysisRun::RUN_TYPE_PRODUCTION_ORDER => $this->analyzeProductionOrders($run, $period, $year, $orgId),
                    default => null,
                };
            });

            $run->update([
                'status'       => VarianceAnalysisRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status'        => VarianceAnalysisRun::STATUS_FAILED,
                'completed_at'  => now(),
                'error_message' => $e->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    public function getResults(int $runId): Collection
    {
        return VarianceAnalysisItem::with('costElement')
            ->where('variance_analysis_run_id', $runId)
            ->orderBy('variance_category')
            ->get();
    }

    /**
     * Items of a run in the current organization; 404 when the run is not visible.
     */
    public function getResultsForRun(int $runId): Collection
    {
        VarianceAnalysisRun::findOrFail($runId);

        return $this->getResults($runId);
    }

    /**
     * Summary grouped by variance_category for the completed runs of one organization in the period.
     *
     * The query builder bypasses the organization global scope, so both the
     * runs and their items are filtered to the organization here.
     *
     * @return array<string, array{category: string, total_standard: float, total_actual: float, total_variance: float}>
     */
    public function getSummaryByCategory(int $period, int $year, int $organizationId): array
    {
        $rows = DB::table('variance_analysis_items as vai')
            ->join('variance_analysis_runs as var', 'var.id', '=', 'vai.variance_analysis_run_id')
            ->where('var.organization_id', $organizationId)
            ->where('vai.organization_id', $organizationId)
            ->where('var.period', $period)
            ->where('var.fiscal_year', $year)
            ->where('var.status', VarianceAnalysisRun::STATUS_COMPLETED)
            ->select(
                'vai.variance_category',
                DB::raw('SUM(vai.standard_cost)   AS total_standard'),
                DB::raw('SUM(vai.actual_cost)     AS total_actual'),
                DB::raw('SUM(vai.variance_amount) AS total_variance')
            )
            ->groupBy('vai.variance_category')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $summary[$row->variance_category] = [
                'category'       => $row->variance_category,
                'total_standard' => (float) $row->total_standard,
                'total_actual'   => (float) $row->total_actual,
                'total_variance' => (float) $row->total_variance,
            ];
        }

        return $summary;
    }

    // ----------------------------------------------------------------
    // Private: production order analysis
    // ----------------------------------------------------------------

    private function analyzeProductionOrders(
        VarianceAnalysisRun $run,
        int $period,
        int $year,
        int $orgId
    ): void {
        // Fetch completed work orders for the fiscal year / period
        // We approximate period by month of actual_end_datetime
        $workOrders = WorkOrder::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->whereNotNull('actual_end_datetime')
            ->whereMonth('actual_end_datetime', $period)
            ->whereYear('actual_end_datetime', $year)
            ->get();

        foreach ($workOrders as $wo) {
            $this->createVarianceItems($run, $wo);
        }
    }

    private function createVarianceItems(VarianceAnalysisRun $run, WorkOrder $wo): void
    {
        $standardMaterial = (float) ($wo->estimated_material_cost ?? 0);
        $actualMaterial   = (float) ($wo->actual_material_cost ?? 0);
        $standardLabor    = (float) ($wo->estimated_labor_cost ?? 0);
        $actualLabor      = (float) ($wo->actual_labor_cost ?? 0);
        $standardOverhead = (float) ($wo->estimated_overhead_cost ?? 0);
        $actualOverhead   = (float) ($wo->actual_overhead_cost ?? 0);

        $items = [
            [
                'variance_category' => VarianceAnalysisItem::CATEGORY_PRICE_VARIANCE,
                'standard_cost'     => $standardMaterial,
                'actual_cost'       => $actualMaterial,
            ],
            [
                'variance_category' => VarianceAnalysisItem::CATEGORY_EFFICIENCY_VARIANCE,
                'standard_cost'     => $standardLabor,
                'actual_cost'       => $actualLabor,
            ],
            [
                'variance_category' => VarianceAnalysisItem::CATEGORY_SPENDING_VARIANCE,
                'standard_cost'     => $standardOverhead,
                'actual_cost'       => $actualOverhead,
            ],
        ];

        foreach ($items as $itemData) {
            $varianceAmount  = round($itemData['actual_cost'] - $itemData['standard_cost'], 4);
            $variancePercent = $itemData['standard_cost'] != 0
                ? round(($varianceAmount / $itemData['standard_cost']) * 100, 4)
                : 0.0;

            VarianceAnalysisItem::create([
                'organization_id'        => $run->organization_id,
                'variance_analysis_run_id' => $run->id,
                'reference_type'         => 'work_order',
                'reference_id'           => $wo->id,
                'cost_element_id'        => null,
                'variance_category'      => $itemData['variance_category'],
                'standard_cost'          => $itemData['standard_cost'],
                'actual_cost'            => $itemData['actual_cost'],
                'variance_amount'        => $varianceAmount,
                'variance_percent'       => $variancePercent,
            ]);
        }
    }
}
