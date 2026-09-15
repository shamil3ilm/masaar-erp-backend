<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\DetailedSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DetailedSchedulingController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly DetailedSchedulingService $service,
    ) {}

    // ── Boards ────────────────────────────────────────────────────────────────

    /**
     * List scheduling boards.
     */
    public function boards(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateBoards($request->integer('per_page', 20)));
    }

    /**
     * Create a new scheduling board.
     */
    public function storeBoard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'horizon_days'    => 'nullable|integer|min:1|max:365',
            'work_center_ids' => 'nullable|array',
            'work_center_ids.*' => ['integer', $this->ownedBy('work_centers')],
        ]);

        return $this->created($this->service->createBoard($validated));
    }

    /**
     * Return Gantt-ready data for a board in a date window.
     */
    public function boardData(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        if ($this->service->findBoard($id) === null) {
            return $this->notFound('Scheduling board not found.');
        }

        return $this->success($this->service->getBoardData($id, $validated['date_from'], $validated['date_to']));
    }

    // ── Operations ────────────────────────────────────────────────────────────

    /**
     * List scheduling operations with filtering.
     */
    public function operations(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateOperations(
            $request->only(['work_center_id', 'board_id', 'work_order_id', 'date_from', 'date_to']),
            $request->integer('per_page', 25),
        ));
    }

    /**
     * Create a scheduling operation manually.
     */
    public function storeOperation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scheduling_board_id' => ['nullable', $this->ownedBy('scheduling_boards')],
            'work_order_id'       => ['nullable', $this->ownedBy('work_orders')],
            'process_order_id'    => ['nullable', $this->ownedBy('process_orders')],
            'work_center_id'      => ['required', $this->ownedBy('work_centers')],
            'operation_number'    => 'required|integer|min:1',
            'description'         => 'required|string|max:255',
            'planned_start'       => 'required|date',
            'planned_finish'      => 'required|date|after:planned_start',
            'duration_minutes'    => 'required|integer|min:1',
            'setup_minutes'       => 'nullable|integer|min:0',
            'teardown_minutes'    => 'nullable|integer|min:0',
            'priority'            => 'nullable|integer|min:1|max:100',
            'is_pinned'           => 'nullable|boolean',
            'is_fixed'            => 'nullable|boolean',
            'sequence_number'     => 'nullable|integer|min:1',
        ]);

        return $this->created($this->service->createOperation($validated, auth()->user()->organization_id));
    }

    /**
     * Update a scheduling operation.
     */
    public function updateOperation(Request $request, int $id): JsonResponse
    {
        $operation = $this->service->findOperation($id);

        if ($operation === null) {
            return $this->notFound('Operation not found.');
        }

        $validated = $request->validate([
            'scheduling_board_id' => ['nullable', $this->ownedBy('scheduling_boards')],
            'work_center_id'      => ['sometimes', $this->ownedBy('work_centers')],
            'operation_number'    => 'sometimes|integer|min:1',
            'description'         => 'sometimes|string|max:255',
            'planned_start'       => 'sometimes|date',
            'planned_finish'      => 'sometimes|date',
            'duration_minutes'    => 'sometimes|integer|min:1',
            'setup_minutes'       => 'nullable|integer|min:0',
            'teardown_minutes'    => 'nullable|integer|min:0',
            'priority'            => 'nullable|integer|min:1|max:100',
            'is_pinned'           => 'nullable|boolean',
            'is_fixed'            => 'nullable|boolean',
            'sequence_number'     => 'nullable|integer|min:1',
        ]);

        return $this->success($this->service->updateOperation($operation, $validated));
    }

    /**
     * Reschedule an operation to a new start time (cascades to successors).
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $operation = $this->service->findOperation($id);

        if ($operation === null) {
            return $this->notFound('Operation not found.');
        }

        $validated = $request->validate([
            'new_start' => 'required|date',
        ]);

        $this->service->rescheduleOperation($operation, $validated['new_start']);

        return $this->success($operation->fresh(['workCenter']), 'Operation rescheduled.');
    }

    /**
     * Suggest an optimised sequence for a work center on a given date.
     */
    public function optimize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => ['required', $this->ownedBy('work_centers')],
            'date'           => 'required|date',
        ]);

        return $this->success($this->service->optimizeSequence((int) $validated['work_center_id'], $validated['date']));
    }

    /**
     * Detect scheduling conflicts on a work center in a date range.
     */
    public function conflicts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => ['required', $this->ownedBy('work_centers')],
            'date_from'      => 'required|date',
            'date_to'        => 'required|date|after_or_equal:date_from',
        ]);

        $conflicts = $this->service->detectConflicts(
            (int) $validated['work_center_id'],
            $validated['date_from'],
            $validated['date_to']
        );

        return $this->success([
            'work_center_id' => $validated['work_center_id'],
            'conflict_count' => count($conflicts),
            'conflicts'      => $conflicts,
        ]);
    }
}
