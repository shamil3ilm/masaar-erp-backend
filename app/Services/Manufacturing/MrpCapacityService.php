<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\MrpCapacityRequirement;
use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCapacity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Capacity requirements planning for MRP output: checks planned orders against
 * work-center capacity and reports the resulting load.
 */
class MrpCapacityService
{
    /**
     * Run a Capacity Requirements Planning (CRP) check against a list of
     * planned orders, persisting one MrpCapacityRequirement record per order
     * that has a work-center assignment.
     *
     * Steps:
     *   1. For each planned order, derive the work center and hours_per_unit
     *      from its BOM routing (RoutingOperation) when available, otherwise
     *      fall back to 1.0 h/unit.
     *   2. Fetch the active WorkCenterCapacity for the planned start date.
     *   3. Compute available_hours and load_pct; persist the record.
     *   4. Return a structured summary including overloaded work centers.
     *
     * @param  array<int, MrpPlannedOrder>|Collection<int, MrpPlannedOrder>  $plannedOrders
     * @return array{requirements: array<int, mixed>, overloaded_work_centers: array<int, mixed>, feasible: bool}
     */
    public function runCapacityCheck(
        array|Collection $plannedOrders,
        Carbon $planningHorizon
    ): array {
        $plannedOrders = collect($plannedOrders);

        if ($plannedOrders->isEmpty()) {
            return [
                'requirements'           => [],
                'overloaded_work_centers' => [],
                'feasible'               => true,
            ];
        }

        $orgId        = (int) $plannedOrders->first()->organization_id;
        $requirements = [];
        $allFeasible  = true;

        // Pre-load active work centers for this org so we can map BOM routing
        $workCenters = WorkCenter::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use (
            $plannedOrders,
            $orgId,
            $planningHorizon,
            $workCenters,
            &$requirements,
            &$allFeasible,
        ): void {
            foreach ($plannedOrders as $order) {
                if ($order->product_id === null) {
                    continue;
                }

                $requiredDate = $order->planned_start_date instanceof Carbon
                    ? $order->planned_start_date
                    : Carbon::parse((string) $order->planned_start_date);

                // Skip orders beyond the planning horizon
                if ($requiredDate->gt($planningHorizon)) {
                    continue;
                }

                // Try to find a routing operation for this product → work center
                // Use the first (lowest sequence_number) operation on the active routing.
                $routing = \App\Models\Manufacturing\RoutingOperation::withoutGlobalScopes()
                    ->whereHas('routing', function ($q) use ($orgId, $order): void {
                        $q->withoutGlobalScopes()
                          ->where('organization_id', $orgId)
                          ->where('product_id', $order->product_id);
                    })
                    ->with('workCenter')
                    ->orderBy('sequence_number')
                    ->first();

                // Determine work center and hours-per-unit.
                // hours_per_unit = machine_time + labor_time (both already expressed per unit).
                if ($routing !== null && $routing->work_center_id !== null) {
                    $workCenterId = (int) $routing->work_center_id;
                    $hoursPerUnit = (float) $routing->machine_time + (float) $routing->labor_time;
                    $hoursPerUnit = $hoursPerUnit > 0.0 ? $hoursPerUnit : 1.0;
                } else {
                    // No routing: pick the first active work center or skip
                    $firstWc = $workCenters->first();
                    if ($firstWc === null) {
                        continue;
                    }
                    $workCenterId = (int) $firstWc->id;
                    $hoursPerUnit = 1.0;
                }

                $requiredHours = (float) $order->planned_quantity * $hoursPerUnit;

                // Fetch active capacity record for the required date
                $capacity = WorkCenterCapacity::withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where('work_center_id', $workCenterId)
                    ->activeOn($requiredDate->toDateString())
                    ->first();

                $availableHours = $capacity !== null
                    ? $capacity->effectiveHoursPerDay()
                    : 0.0;

                $loadPct = $availableHours > 0.0
                    ? round(($requiredHours / $availableHours) * 100, 2)
                    : ($requiredHours > 0 ? 999.99 : 0.0);

                $status = $loadPct > 100.0
                    ? MrpCapacityRequirement::STATUS_OVERLOADED
                    : MrpCapacityRequirement::STATUS_FEASIBLE;

                if ($status === MrpCapacityRequirement::STATUS_OVERLOADED) {
                    $allFeasible = false;
                }

                $req = MrpCapacityRequirement::create([
                    'organization_id' => $orgId,
                    'mrp_run_id'      => $order->mrp_run_id,
                    'work_center_id'  => $workCenterId,
                    'planned_order_id' => $order->id,
                    'required_date'   => $requiredDate->toDateString(),
                    'required_hours'  => $requiredHours,
                    'available_hours' => $availableHours,
                    'load_pct'        => $loadPct,
                    'status'          => $status,
                ]);

                $requirements[] = [
                    'id'               => $req->id,
                    'uuid'             => $req->uuid,
                    'planned_order_id' => $order->id,
                    'work_center_id'   => $workCenterId,
                    'work_center_name' => $workCenters->get($workCenterId)?->name,
                    'required_date'    => $requiredDate->toDateString(),
                    'required_hours'   => $requiredHours,
                    'available_hours'  => $availableHours,
                    'load_pct'         => $loadPct,
                    'status'           => $status,
                ];
            }
        });

        $overloaded = collect($requirements)
            ->where('status', MrpCapacityRequirement::STATUS_OVERLOADED)
            ->groupBy('work_center_id')
            ->map(function (Collection $items): array {
                return [
                    'work_center_id'   => $items->first()['work_center_id'],
                    'work_center_name' => $items->first()['work_center_name'],
                    'overloaded_count' => $items->count(),
                    'max_load_pct'     => $items->max('load_pct'),
                ];
            })
            ->values()
            ->all();

        return [
            'requirements'            => $requirements,
            'overloaded_work_centers' => $overloaded,
            'feasible'                => $allFeasible,
        ];
    }

    /**
     * Return aggregated capacity load per work center per ISO week for a
     * given date range.
     *
     * Groups persisted MrpCapacityRequirement records by work_center_id and
     * ISO week number, summing required_hours and averaging available_hours.
     *
     * @return array<int, array{work_center_id: int, work_center_name: string|null, weeks: array<int, mixed>}>
     */
    public function getCapacityLoad(int $orgId, Carbon $fromDate, Carbon $toDate): array
    {
        // Aggregate at DB level — GROUP BY work_center + ISO week, no full collection in memory.
        $rows = MrpCapacityRequirement::withoutGlobalScopes()
            ->where('mrp_capacity_requirements.organization_id', $orgId)
            ->whereDate('required_date', '>=', $fromDate->toDateString())
            ->whereDate('required_date', '<=', $toDate->toDateString())
            ->join('work_centers', 'work_centers.id', '=', 'mrp_capacity_requirements.work_center_id')
            ->selectRaw(
                'mrp_capacity_requirements.work_center_id,
                 work_centers.name  AS work_center_name,
                 work_centers.code  AS work_center_code,
                 DATE_FORMAT(required_date, \'%x-%V\') AS year_week,
                 SUM(required_hours)  AS required_hours,
                 SUM(available_hours) AS available_hours,
                 MAX(CASE WHEN required_hours > available_hours THEN 1 ELSE 0 END) AS overloaded'
            )
            ->groupBy('mrp_capacity_requirements.work_center_id', 'work_centers.name', 'work_centers.code', 'year_week')
            ->orderBy('mrp_capacity_requirements.work_center_id')
            ->orderBy('year_week')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $wcId = (int) $row->work_center_id;
            if (!isset($grouped[$wcId])) {
                $grouped[$wcId] = [
                    'work_center_id'   => $wcId,
                    'work_center_name' => $row->work_center_name,
                    'work_center_code' => $row->work_center_code,
                    'weeks'            => [],
                ];
            }

            $req  = (float) $row->required_hours;
            $avail = (float) $row->available_hours;
            $grouped[$wcId]['weeks'][] = [
                'year_week'       => $row->year_week,
                'required_hours'  => $req,
                'available_hours' => $avail,
                'load_pct'        => $avail > 0.0
                    ? round(($req / $avail) * 100, 2)
                    : ($req > 0 ? 999.99 : 0.0),
                'overloaded'      => (bool) $row->overloaded,
            ];
        }

        return array_values($grouped);
    }
}
