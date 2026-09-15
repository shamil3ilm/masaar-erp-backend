<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\LongTermPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermPlanningController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly LongTermPlanningService $service,
    ) {}

    /**
     * List LTP simulations.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate(
            $request->only(['status', 'search']),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a new LTP simulation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'planning_horizon_from' => 'required|date',
            'planning_horizon_to'   => 'required|date|after:planning_horizon_from',
            'mrp_run_id'            => ['nullable', $this->ownedBy('mrp_runs')],
        ]);

        return $this->created($this->service->create($validated));
    }

    /**
     * Show a simulation.
     */
    public function show(int $id): JsonResponse
    {
        $simulation = $this->service->find($id, ['createdBy', 'mrpRun'], ['plannedOrders', 'capacityRequirements']);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        return $this->success($simulation);
    }

    /**
     * Update a simulation (only draft simulations).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $simulation = $this->service->find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        if (!$simulation->isDraft()) {
            return $this->error('Only draft simulations can be updated.', 'INVALID_STATUS', 422, []);
        }

        $validated = $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'description'           => 'nullable|string',
            'planning_horizon_from' => 'sometimes|date',
            'planning_horizon_to'   => 'sometimes|date',
            'mrp_run_id'            => ['nullable', $this->ownedBy('mrp_runs')],
        ]);

        try {
            $updated = $this->service->update($simulation, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422, []);
        }

        return $this->success($updated, 'Simulation updated.');
    }

    /**
     * Delete a simulation.
     */
    public function destroy(int $id): JsonResponse
    {
        $simulation = $this->service->find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $this->service->delete($simulation);

        return $this->success(null, 'Simulation deleted.');
    }

    /**
     * Run the simulation (generate LTP planned orders and capacity requirements).
     */
    public function run(int $id): JsonResponse
    {
        $simulation = $this->service->find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $this->service->runSimulation($simulation);

        return $this->success($simulation->fresh(), 'Simulation run completed.');
    }

    /**
     * Get capacity requirements overview for a simulation.
     */
    public function capacity(int $id): JsonResponse
    {
        if ($this->service->find($id) === null) {
            return $this->notFound('Simulation not found.');
        }

        return $this->success($this->service->getCapacityOverview($id));
    }

    /**
     * Get the LTP planned orders for a simulation.
     */
    public function plannedOrders(Request $request, int $id): JsonResponse
    {
        $simulation = $this->service->find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        return $this->paginated($this->service->paginatePlannedOrders(
            $simulation,
            $request->only(['product_id', 'order_type']),
            $request->integer('per_page', 25),
        ));
    }

    /**
     * Compare the simulation against the operative MRP plan.
     */
    public function compare(int $id): JsonResponse
    {
        if ($this->service->find($id) === null) {
            return $this->notFound('Simulation not found.');
        }

        return $this->success($this->service->compareWithOperativePlan($id));
    }
}
