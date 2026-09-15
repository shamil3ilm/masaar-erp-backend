<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProcessOrder;
use App\Models\Manufacturing\ProcessOrderPhase;
use App\Models\Manufacturing\Recipe;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Process manufacturing: recipes, process orders and their phases.
 *
 * Release, completion and phase changes run on the locked process order and
 * re-check the status there. Phases carry no organization column, so they are
 * found and locked only through an order of the caller's organization.
 */
class ProcessOrderService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * @param  array{product_id?: mixed, active_only?: bool}  $filters
     */
    public function paginateRecipes(array $filters, int $perPage): LengthAwarePaginator
    {
        return Recipe::with(['product', 'baseUnit'])
            ->withCount(['phases', 'resources'])
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->forProduct((int) $v))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->orderBy('recipe_code')
            ->paginate($perPage);
    }

    public function createRecipe(array $data): Recipe
    {
        return Recipe::create($data)->load(['product', 'baseUnit']);
    }

    /**
     * One of the organization's recipes with its phases and resources, or null.
     */
    public function findRecipe(int $id): ?Recipe
    {
        return Recipe::with(['product', 'baseUnit', 'phases.resources', 'resources.material', 'resources.unit'])->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateOrders(array $filters, int $perPage): LengthAwarePaginator
    {
        return ProcessOrder::with(['recipe', 'product', 'unit', 'productionVersion'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->forProduct((int) $v))
            ->when($filters['recipe_id'] ?? null, fn ($q, $v) => $q->where('recipe_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('order_number', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * One of the organization's process orders, or null.
     *
     * @param  list<string>  $with
     */
    public function findOrder(int $id, array $with = []): ?ProcessOrder
    {
        return ProcessOrder::with($with)->find($id);
    }

    /**
     * A phase of one of the organization's process orders, or null.
     */
    public function findPhase(int $id): ?ProcessOrderPhase
    {
        return ProcessOrderPhase::whereHas('processOrder')->find($id);
    }

    /**
     * Create a process order by exploding a recipe into phases and resources.
     */
    public function createFromRecipe(int $recipeId, array $data): ProcessOrder
    {
        $recipe = Recipe::with(['phases', 'resources'])->findOrFail($recipeId);

        return DB::transaction(function () use ($recipe, $data): ProcessOrder {
            $scaleFactor = (float) $data['planned_quantity'] / (float) $recipe->base_quantity;

            $order = ProcessOrder::create([
                'organization_id'       => auth()->user()->organization_id,
                'recipe_id'             => $recipe->id,
                'product_id'            => $recipe->product_id,
                'order_number'          => $this->numberGenerator->generate('PRO'),
                'planned_quantity'      => $data['planned_quantity'],
                'unit_id'               => $data['unit_id'] ?? $recipe->base_unit_id,
                'batch_number'          => $data['batch_number'] ?? null,
                'planned_start'         => $data['planned_start'],
                'planned_finish'        => $data['planned_finish'],
                'status'                => ProcessOrder::STATUS_CREATED,
                'production_version_id' => $data['production_version_id'] ?? null,
                'created_by'            => auth()->id(),
            ]);

            foreach ($recipe->phases as $recipePhase) {
                $order->phases()->create([
                    'recipe_phase_id' => $recipePhase->id,
                    'phase_number'    => $recipePhase->phase_number,
                    'name'            => $recipePhase->name,
                    'status'          => ProcessOrderPhase::STATUS_PENDING,
                ]);
            }

            // Resource quantities scale from the recipe's base quantity to the planned quantity.
            foreach ($recipe->resources as $recipeResource) {
                $order->resources()->create([
                    'recipe_resource_id' => $recipeResource->id,
                    'material_id'        => $recipeResource->material_id,
                    'planned_quantity'   => round((float) $recipeResource->quantity * $scaleFactor, 4),
                    'unit_id'            => $recipeResource->unit_id,
                ]);
            }

            return $order->load(['phases', 'resources', 'recipe', 'product']);
        });
    }

    /**
     * Release a created process order for production.
     */
    public function release(ProcessOrder $order): void
    {
        $order->lockForTransition(function (ProcessOrder $order): void {
            if (!$order->canBeReleased()) {
                throw new \LogicException("Process order {$order->order_number} cannot be released in its current status.");
            }

            $order->update(['status' => ProcessOrder::STATUS_RELEASED]);
        });
    }

    /**
     * Start a pending phase; the first phase started puts a released order in progress.
     *
     * @param array<string, mixed> $parameters
     */
    public function startPhase(ProcessOrderPhase $phase, array $parameters = []): void
    {
        $this->orderOf($phase)->lockForTransition(function (ProcessOrder $order) use ($phase): void {
            $phase = $this->lockPhase($order, $phase->id);

            if (!$phase->isPending()) {
                throw new \LogicException("Phase {$phase->phase_number} is not in pending status.");
            }

            $phase->update([
                'status'     => ProcessOrderPhase::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            if ($order->isReleased()) {
                $order->update(['status' => ProcessOrder::STATUS_IN_PROGRESS, 'actual_start' => now()]);
            }
        });
    }

    /**
     * Complete a phase in progress, recording actual measurement values.
     *
     * @param array<string, mixed> $actuals
     */
    public function completePhase(ProcessOrderPhase $phase, array $actuals): void
    {
        $this->orderOf($phase)->lockForTransition(function (ProcessOrder $order) use ($phase, $actuals): void {
            $phase = $this->lockPhase($order, $phase->id);

            if (!$phase->isInProgress()) {
                throw new \LogicException("Phase {$phase->phase_number} is not in progress.");
            }

            $actualDurationMinutes = $phase->started_at
                ? (int) $phase->started_at->diffInMinutes(now())
                : null;

            $phase->update([
                'status'                  => ProcessOrderPhase::STATUS_COMPLETED,
                'completed_at'            => now(),
                'actual_temperature'      => $actuals['actual_temperature'] ?? null,
                'actual_pressure'         => $actuals['actual_pressure'] ?? null,
                'actual_duration_minutes' => $actuals['actual_duration_minutes'] ?? $actualDurationMinutes,
                'operator_notes'          => $actuals['operator_notes'] ?? null,
            ]);
        });
    }

    /**
     * Complete a released or in-progress process order with its produced quantity.
     */
    public function complete(ProcessOrder $order, float $actualQuantity): void
    {
        $order->lockForTransition(function (ProcessOrder $order) use ($actualQuantity): void {
            if (!$order->canBeCompleted()) {
                throw new \LogicException("Process order {$order->order_number} cannot be completed in its current status.");
            }

            $order->update([
                'status'          => ProcessOrder::STATUS_COMPLETED,
                'actual_quantity' => $actualQuantity,
                'actual_finish'   => now(),
            ]);
        });
    }

    /**
     * Return per-phase progress for an order.
     *
     * @return array{
     *   order_id: int,
     *   status: string,
     *   total_phases: int,
     *   completed_phases: int,
     *   progress_percent: float,
     *   phases: list<array<string, mixed>>
     * }
     */
    public function getPhaseProgress(int $orderId): array
    {
        $order = ProcessOrder::with('phases')->findOrFail($orderId);

        $total     = $order->phases->count();
        $completed = $order->phases->where('status', ProcessOrderPhase::STATUS_COMPLETED)->count();

        return [
            'order_id'        => $order->id,
            'status'          => $order->status,
            'total_phases'    => $total,
            'completed_phases' => $completed,
            'progress_percent' => $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
            'phases'          => $order->phases->map(fn($p) => [
                'id'           => $p->id,
                'phase_number' => $p->phase_number,
                'name'         => $p->name,
                'status'       => $p->status,
                'started_at'   => $p->started_at?->toIso8601String(),
                'completed_at' => $p->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * The organization's order the phase belongs to.
     */
    private function orderOf(ProcessOrderPhase $phase): ProcessOrder
    {
        return ProcessOrder::findOrFail($phase->process_order_id);
    }

    /**
     * The order's phase, locked until the transaction ends.
     */
    private function lockPhase(ProcessOrder $order, int $phaseId): ProcessOrderPhase
    {
        return ProcessOrderPhase::whereKey($phaseId)
            ->where('process_order_id', $order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
