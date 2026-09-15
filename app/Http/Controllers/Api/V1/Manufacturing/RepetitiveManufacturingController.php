<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\RepetitiveManufacturingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepetitiveManufacturingController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly RepetitiveManufacturingService $service,
    ) {}

    // ── Production Lines ──────────────────────────────────────────────────────

    /**
     * List production lines.
     */
    public function lines(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateLines(
            $request->boolean('active_only', false),
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Create a production line.
     */
    public function storeLine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'              => 'required|string|max:30',
            'name'              => 'required|string|max:255',
            'work_center_id'    => ['nullable', $this->ownedBy('work_centers')],
            'capacity_per_hour' => 'nullable|numeric|min:0',
            'unit_id'           => ['nullable', $this->ownedBy('units_of_measure')],
            'is_active'         => 'nullable|boolean',
        ]);

        return $this->created($this->service->createLine($validated));
    }

    // ── Schedules ─────────────────────────────────────────────────────────────

    /**
     * List repetitive manufacturing schedules.
     */
    public function schedules(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateSchedules(
            $request->only(['status', 'product_id', 'line_id']),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a new repetitive manufacturing schedule.
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'             => ['required', $this->ownedBy('products')],
            'production_version_id'  => ['nullable', $this->ownedBy('production_versions')],
            'production_line_id'     => ['required', $this->ownedBy('production_lines')],
            'schedule_date_from'     => 'required|date',
            'schedule_date_to'       => 'required|date|after_or_equal:schedule_date_from',
            'total_planned_quantity' => 'required|numeric|min:0.0001',
        ]);

        return $this->created($this->service->createSchedule($validated));
    }

    /**
     * Show a schedule with its lines.
     */
    public function showSchedule(int $id): JsonResponse
    {
        $schedule = $this->service->findSchedule($id, ['product', 'productionLine', 'productionVersion', 'lines', 'backflushes']);

        if ($schedule === null) {
            return $this->notFound('Schedule not found.');
        }

        return $this->success($schedule);
    }

    /**
     * Confirm a quantity against a schedule line.
     */
    public function confirmLine(Request $request, int $lineId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $line = $this->service->findScheduleLine($lineId);

        if ($line === null) {
            return $this->notFound('Schedule line not found.');
        }

        $this->service->confirmScheduleLine($line, (float) $validated['quantity']);

        return $this->success($line->fresh(), 'Schedule line confirmed.');
    }

    /**
     * Perform a backflush (production confirmation).
     */
    public function backflush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repetitive_mfg_schedule_id' => ['required', $this->ownedBy('repetitive_mfg_schedules')],
            'backflush_date'             => 'nullable|date',
            'quantity_produced'          => 'required|numeric|min:0.0001',
            'quantity_scrapped'          => 'nullable|numeric|min:0',
            'component_movements'        => 'nullable|array',
            'component_movements.*.product_id'   => ['required', 'integer', $this->ownedBy('products')],
            'component_movements.*.quantity'     => 'required|numeric|min:0.0001',
            'component_movements.*.warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            'labor_time_minutes'         => 'nullable|numeric|min:0',
        ]);

        $backflush = $this->service->performBackflush($validated);

        return $this->created($backflush, 'Backflush recorded successfully.');
    }

    /**
     * Get production progress for a schedule.
     */
    public function progress(int $id): JsonResponse
    {
        if ($this->service->findSchedule($id) === null) {
            return $this->notFound('Schedule not found.');
        }

        return $this->success($this->service->getProductionProgress($id));
    }
}
