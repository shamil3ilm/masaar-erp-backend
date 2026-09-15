<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Inventory\StockMovement;
use App\Models\Manufacturing\ProductionLine;
use App\Models\Manufacturing\RepetitiveMfgBackflush;
use App\Models\Manufacturing\RepetitiveMfgSchedule;
use App\Models\Manufacturing\RepetitiveMfgScheduleLine;
use App\Services\Inventory\StockService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Repetitive manufacturing: production lines, rate schedules, confirmations
 * and backflushes.
 *
 * Schedule lines carry no organization column; they are found only through a
 * schedule of the caller's organization. A confirmation adds to the locked line
 * and the locked schedule, so concurrent confirmations both count. A backflush
 * issues its components through the stock service in the same transaction.
 */
class RepetitiveManufacturingService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function paginateLines(bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return ProductionLine::with(['workCenter', 'unit'])
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('code')
            ->paginate($perPage);
    }

    public function createLine(array $data): ProductionLine
    {
        return ProductionLine::create($data)->load(['workCenter', 'unit']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateSchedules(array $filters, int $perPage): LengthAwarePaginator
    {
        return RepetitiveMfgSchedule::with(['product', 'productionLine', 'productionVersion'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['line_id'] ?? null, fn ($q, $v) => $q->where('production_line_id', $v))
            ->orderByDesc('schedule_date_from')
            ->paginate($perPage);
    }

    /**
     * One of the organization's schedules, or null.
     *
     * @param  list<string>  $with
     */
    public function findSchedule(int $id, array $with = []): ?RepetitiveMfgSchedule
    {
        return RepetitiveMfgSchedule::with($with)->find($id);
    }

    /**
     * A line of one of the organization's schedules, or null.
     */
    public function findScheduleLine(int $id): ?RepetitiveMfgScheduleLine
    {
        return RepetitiveMfgScheduleLine::whereHas('schedule')->find($id);
    }

    /**
     * Create a new repetitive manufacturing schedule along with
     * one schedule line per calendar day in the planned range.
     */
    public function createSchedule(array $data): RepetitiveMfgSchedule
    {
        return DB::transaction(function () use ($data): RepetitiveMfgSchedule {
            $schedule = RepetitiveMfgSchedule::create([
                'organization_id'          => auth()->user()->organization_id,
                'product_id'               => $data['product_id'],
                'production_version_id'    => $data['production_version_id'] ?? null,
                'production_line_id'       => $data['production_line_id'],
                'schedule_date_from'       => $data['schedule_date_from'],
                'schedule_date_to'         => $data['schedule_date_to'],
                'total_planned_quantity'   => $data['total_planned_quantity'],
                'total_confirmed_quantity' => 0,
                'status'                   => RepetitiveMfgSchedule::STATUS_PLANNED,
                'created_by'               => auth()->id(),
            ]);

            $this->generateScheduleLines($schedule, (float) $data['total_planned_quantity']);

            return $schedule->load(['lines', 'productionLine', 'product']);
        });
    }

    /**
     * Confirm a quantity against a schedule line, adding it to the line and the schedule totals.
     */
    public function confirmScheduleLine(RepetitiveMfgScheduleLine $line, float $quantity): void
    {
        $schedule = RepetitiveMfgSchedule::findOrFail($line->repetitive_mfg_schedule_id);

        $schedule->lockForTransition(function (RepetitiveMfgSchedule $schedule) use ($line, $quantity): void {
            $line = RepetitiveMfgScheduleLine::whereKey($line->id)
                ->where('repetitive_mfg_schedule_id', $schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newConfirmed = (float) bcadd((string) $line->confirmed_quantity, (string) $quantity, 4);

            $status = match (true) {
                $newConfirmed >= (float) $line->planned_quantity => RepetitiveMfgScheduleLine::STATUS_CONFIRMED,
                $newConfirmed > 0                                => RepetitiveMfgScheduleLine::STATUS_PARTIAL,
                default                                          => RepetitiveMfgScheduleLine::STATUS_PLANNED,
            };

            $line->update([
                'confirmed_quantity' => $newConfirmed,
                'status'             => $status,
            ]);

            $schedule->update([
                'total_confirmed_quantity' => bcadd((string) $schedule->total_confirmed_quantity, (string) $quantity, 4),
            ]);

            $this->updateScheduleStatus($schedule);
        });
    }

    /**
     * Record a backflush (production confirmation) against a schedule and issue
     * each consumed component from its warehouse.
     */
    public function performBackflush(array $data): RepetitiveMfgBackflush
    {
        return DB::transaction(function () use ($data): RepetitiveMfgBackflush {
            $schedule = RepetitiveMfgSchedule::lockForUpdate()->findOrFail($data['repetitive_mfg_schedule_id']);

            $backflush = RepetitiveMfgBackflush::create([
                'organization_id'             => auth()->user()->organization_id,
                'repetitive_mfg_schedule_id'  => $schedule->id,
                'backflush_date'              => $data['backflush_date'] ?? now(),
                'quantity_produced'           => $data['quantity_produced'],
                'quantity_scrapped'           => $data['quantity_scrapped'] ?? 0,
                'component_movements'         => $data['component_movements'] ?? null,
                'labor_time_minutes'          => $data['labor_time_minutes'] ?? null,
                'created_by'                  => auth()->id(),
            ]);

            foreach ($data['component_movements'] ?? [] as $movement) {
                $this->stockService->recordMovement(
                    productId: (int) $movement['product_id'],
                    warehouseId: (int) $movement['warehouse_id'],
                    movementType: StockMovement::TYPE_MATERIAL_ISSUE,
                    direction: StockMovement::DIRECTION_OUT,
                    quantity: (float) $movement['quantity'],
                    referenceType: RepetitiveMfgBackflush::class,
                    referenceId: $backflush->id,
                    notes: "Backflush for repetitive schedule {$schedule->id}",
                );
            }

            return $backflush;
        });
    }

    /**
     * Return production progress summary for a schedule.
     *
     * @return array{
     *   total_planned: float,
     *   total_confirmed: float,
     *   total_produced: float,
     *   total_scrapped: float,
     *   progress_percent: float,
     *   lines: array<int, array<string, mixed>>
     * }
     */
    public function getProductionProgress(int $scheduleId): array
    {
        $schedule = RepetitiveMfgSchedule::with(['lines', 'backflushes'])->findOrFail($scheduleId);

        $totalProduced = $schedule->backflushes->sum('quantity_produced');
        $totalScrapped = $schedule->backflushes->sum('quantity_scrapped');
        $planned       = (float) $schedule->total_planned_quantity;

        return [
            'total_planned'    => $planned,
            'total_confirmed'  => (float) $schedule->total_confirmed_quantity,
            'total_produced'   => (float) $totalProduced,
            'total_scrapped'   => (float) $totalScrapped,
            'progress_percent' => $planned > 0 ? round(($totalProduced / $planned) * 100, 2) : 0.0,
            'lines'            => $schedule->lines->map(fn($l) => [
                'id'                 => $l->id,
                'schedule_date'      => $l->schedule_date->toDateString(),
                'planned_quantity'   => (float) $l->planned_quantity,
                'confirmed_quantity' => (float) $l->confirmed_quantity,
                'status'             => $l->status,
            ])->all(),
        ];
    }

    /**
     * Calculate utilization for a production line within a date range.
     *
     * @return array{
     *   line_id: int,
     *   date_from: string,
     *   date_to: string,
     *   total_planned: float,
     *   total_confirmed: float,
     *   utilization_percent: float
     * }
     */
    public function getLineUtilization(int $lineId, string $dateFrom, string $dateTo): array
    {
        $schedules = RepetitiveMfgSchedule::where('production_line_id', $lineId)
            ->where('schedule_date_from', '<=', $dateTo)
            ->where('schedule_date_to', '>=', $dateFrom)
            ->get();

        $totalPlanned   = $schedules->sum('total_planned_quantity');
        $totalConfirmed = $schedules->sum('total_confirmed_quantity');

        return [
            'line_id'             => $lineId,
            'date_from'           => $dateFrom,
            'date_to'             => $dateTo,
            'total_planned'       => (float) $totalPlanned,
            'total_confirmed'     => (float) $totalConfirmed,
            'utilization_percent' => $totalPlanned > 0
                ? round(($totalConfirmed / $totalPlanned) * 100, 2)
                : 0.0,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generateScheduleLines(RepetitiveMfgSchedule $schedule, float $totalQty): void
    {
        $from    = Carbon::parse($schedule->schedule_date_from);
        $to      = Carbon::parse($schedule->schedule_date_to);
        $days    = max(1, $from->diffInDays($to) + 1);
        $perDay  = round($totalQty / $days, 4);

        $current = $from->copy();
        while ($current->lte($to)) {
            RepetitiveMfgScheduleLine::create([
                'repetitive_mfg_schedule_id' => $schedule->id,
                'schedule_date'              => $current->toDateString(),
                'planned_quantity'           => $perDay,
                'confirmed_quantity'         => 0,
                'status'                     => RepetitiveMfgScheduleLine::STATUS_PLANNED,
            ]);
            $current->addDay();
        }
    }

    private function updateScheduleStatus(RepetitiveMfgSchedule $schedule): void
    {
        $status = match (true) {
            (float) $schedule->total_confirmed_quantity >= (float) $schedule->total_planned_quantity
                => RepetitiveMfgSchedule::STATUS_COMPLETED,
            (float) $schedule->total_confirmed_quantity > 0
                => RepetitiveMfgSchedule::STATUS_IN_PROGRESS,
            default => RepetitiveMfgSchedule::STATUS_PLANNED,
        };

        $schedule->update(['status' => $status]);
    }
}
