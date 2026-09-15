<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenanceOrderTask;
use App\Models\Maintenance\MaintenancePlan;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates maintenance orders and moves them through their lifecycle.
 *
 * Every change to an existing order runs on the locked order and checks its
 * status there, so a repeated or concurrent request cannot start, complete or
 * cancel an order twice. Completion issues the parts the order used from stock
 * in the same transaction: when a part cannot be issued, the order stays in
 * progress and nothing is taken from stock.
 */
class MaintenanceService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Orders by priority, critical first, then newest first. The ranking gives
     * a priority outside the known four the first place, as MySQL's FIELD() did.
     *
     * @param  array{search?: mixed, status?: mixed, priority?: mixed, order_type?: mixed, equipment_id?: mixed, assigned_to?: mixed}  $filters
     */
    public function paginateOrders(array $filters, int $perPage): LengthAwarePaginator
    {
        return MaintenanceOrder::query()
            ->with(['equipment', 'assignee', 'plan'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('order_number', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['order_type'] ?? null, fn ($query, $type) => $query->where('order_type', $type))
            ->when($filters['equipment_id'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['assigned_to'] ?? null, fn ($query, $id) => $query->where('assigned_to', $id))
            ->orderByRaw('CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 0 END', [
                MaintenanceOrder::PRIORITY_CRITICAL,
                MaintenanceOrder::PRIORITY_HIGH,
                MaintenanceOrder::PRIORITY_MEDIUM,
                MaintenanceOrder::PRIORITY_LOW,
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Generate a maintenance order from a plan, with the plan's tasks, and
     * move the equipment's next maintenance date on.
     *
     * @throws InvalidArgumentException when the plan is inactive
     */
    public function generateOrderFromPlan(MaintenancePlan $plan, int $userId): MaintenanceOrder
    {
        if (! $plan->is_active) {
            throw new InvalidArgumentException('Cannot generate an order from an inactive maintenance plan.');
        }

        return DB::transaction(function () use ($plan, $userId): MaintenanceOrder {
            $orgId = $plan->organization_id;

            $order = MaintenanceOrder::create([
                'organization_id' => $orgId,
                'order_number' => $this->numberGenerator->generate(MaintenanceOrder::NUMBER_SEQUENCE, MaintenanceOrder::NUMBER_FORMAT, $orgId),
                'maintenance_plan_id' => $plan->id,
                'equipment_id' => $plan->equipment_id,
                'order_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'priority' => MaintenanceOrder::PRIORITY_MEDIUM,
                'status' => MaintenanceOrder::STATUS_OPEN,
                'description' => $plan->description ?? $plan->name,
                'estimated_cost' => null,
                'created_by' => $userId,
            ]);

            foreach ($plan->tasks ?? [] as $index => $taskData) {
                $order->tasks()->create([
                    'task_description' => is_array($taskData) ? ($taskData['description'] ?? $taskData) : $taskData,
                    'is_safety_critical' => is_array($taskData) ? (bool) ($taskData['is_safety_critical'] ?? false) : false,
                    'sort_order' => $index,
                ]);
            }

            $plan->update(['last_generated_at' => now()]);

            $nextDue = $plan->calculateNextDueDate(new \DateTime(now()->toDateString()));
            $plan->equipment->update(['next_maintenance_date' => $nextDue->format('Y-m-d')]);

            return $order->load(['tasks', 'parts', 'equipment']);
        });
    }

    /**
     * Create a maintenance order with optional tasks and parts.
     *
     * @param  array  $tasks  task definitions: task_description, is_safety_critical, sort_order, notes
     * @param  array  $parts  part lines: product_id, description, quantity_required, unit_cost
     */
    public function createMaintenanceOrder(
        array $data,
        array $tasks,
        array $parts,
        int $userId
    ): MaintenanceOrder {
        return DB::transaction(function () use ($data, $tasks, $parts, $userId): MaintenanceOrder {
            $orgId = $data['organization_id'] ?? auth()->user()->organization_id;

            $order = MaintenanceOrder::create(array_merge($data, [
                'organization_id' => $orgId,
                'order_number' => $this->numberGenerator->generate(MaintenanceOrder::NUMBER_SEQUENCE, MaintenanceOrder::NUMBER_FORMAT, $orgId),
                'created_by' => $userId,
                'status' => $data['status'] ?? MaintenanceOrder::STATUS_OPEN,
            ]));

            foreach ($tasks as $index => $taskData) {
                $order->tasks()->create(array_merge($taskData, [
                    'sort_order' => $taskData['sort_order'] ?? $index,
                ]));
            }

            foreach ($parts as $partData) {
                $order->parts()->create($partData);
            }

            return $order->load(['tasks', 'parts', 'equipment', 'plan']);
        });
    }

    /**
     * @throws BusinessRuleException when the order is completed or cancelled
     */
    public function updateOrder(MaintenanceOrder $order, array $data): MaintenanceOrder
    {
        return $order->lockForTransition(function (MaintenanceOrder $order) use ($data): MaintenanceOrder {
            if (in_array($order->status, [MaintenanceOrder::STATUS_COMPLETED, MaintenanceOrder::STATUS_CANCELLED], true)) {
                throw new BusinessRuleException('Cannot update a completed or cancelled order.', 'ORDER_CLOSED');
            }

            $order->update($data);

            return $order->fresh();
        });
    }

    /**
     * @throws BusinessRuleException when the order is neither open nor cancelled
     */
    public function deleteOrder(MaintenanceOrder $order): void
    {
        $order->lockForTransition(function (MaintenanceOrder $order): void {
            if (! in_array($order->status, [MaintenanceOrder::STATUS_OPEN, MaintenanceOrder::STATUS_CANCELLED], true)) {
                throw new BusinessRuleException('Only open or cancelled orders can be deleted.', 'ORDER_NOT_DELETABLE');
            }

            $order->delete();
        });
    }

    /**
     * Start an open or on-hold order.
     *
     * @throws InvalidArgumentException when the order cannot be started from its status
     */
    public function startOrder(MaintenanceOrder $order, int $userId): MaintenanceOrder
    {
        return $order->lockForTransition(fn (MaintenanceOrder $order): MaintenanceOrder => $order->start($userId));
    }

    /**
     * Mark one of the order's tasks complete. A task of another order is not found.
     *
     * @throws InvalidArgumentException when the task is already completed
     */
    public function completeTask(
        MaintenanceOrder $order,
        int $taskId,
        string $notes,
        int $userId
    ): MaintenanceOrderTask {
        return $order->lockForTransition(function (MaintenanceOrder $order) use ($taskId, $notes, $userId): MaintenanceOrderTask {
            $task = $order->tasks()->findOrFail($taskId);

            if ($task->is_completed) {
                throw new InvalidArgumentException('Task is already completed.');
            }

            $task->update([
                'is_completed' => true,
                'completed_at' => now(),
                'completed_by' => $userId,
                'notes' => $notes,
            ]);

            return $task->fresh();
        });
    }

    /**
     * Complete an in-progress order and issue the parts it used from stock.
     *
     * @throws InvalidArgumentException when the order is not in progress or a used part cannot be issued
     */
    public function completeOrder(MaintenanceOrder $order, array $data, int $userId): MaintenanceOrder
    {
        return $order->lockForTransition(function (MaintenanceOrder $order) use ($data, $userId): MaintenanceOrder {
            $completed = $order->complete($data, $userId);

            $this->issueUsedParts($order, $userId);

            return $completed;
        });
    }

    /**
     * Cancel an order that is neither completed nor cancelled.
     *
     * @throws InvalidArgumentException when the order is already completed or cancelled
     */
    public function cancelOrder(MaintenanceOrder $order, int $userId): MaintenanceOrder
    {
        return $order->lockForTransition(fn (MaintenanceOrder $order): MaintenanceOrder => $order->cancel($userId));
    }

    /**
     * Order counts by type and status for orders created in the period, with
     * the mean time to repair and the downtime of the completed ones.
     *
     * @return array{total_orders: int, by_type: array<string, int>, by_status: array<string, int>, mttr_hours: float, total_downtime_hours: float}
     */
    public function getMaintenanceStats(int $orgId, string $from, string $to): array
    {
        $base = MaintenanceOrder::forOrganization($orgId)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);

        $total = (clone $base)->count();

        $byType = (clone $base)
            ->selectRaw('order_type, COUNT(*) as count')
            ->groupBy('order_type')
            ->pluck('count', 'order_type')
            ->toArray();

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $completedOrders = (clone $base)
            ->where('status', MaintenanceOrder::STATUS_COMPLETED)
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_end')
            ->get(['actual_start', 'actual_end', 'downtime_hours']);

        $mttrHours = 0.0;
        $totalDowntime = 0.0;

        if ($completedOrders->isNotEmpty()) {
            // Minutes counted here: TIMESTAMPDIFF is MySQL only.
            $avgMinutes = $completedOrders->avg(fn ($o) => $o->actual_start->diffInMinutes($o->actual_end));
            $mttrHours = round($avgMinutes / 60, 2);
            $totalDowntime = round((float) $completedOrders->sum('downtime_hours'), 2);
        }

        return [
            'total_orders' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'mttr_hours' => $mttrHours,
            'total_downtime_hours' => $totalDowntime,
        ];
    }

    /**
     * Issue each part the order used, from the organization's warehouse
     * holding the most of that product. StockService locks the stock level and
     * refuses a quantity the warehouse does not hold.
     *
     * @throws InvalidArgumentException when a used part has no stock or too little
     */
    private function issueUsedParts(MaintenanceOrder $order, int $userId): void
    {
        $parts = $order->parts()
            ->whereNotNull('product_id')
            ->where('quantity_used', '>', 0)
            ->get();

        foreach ($parts as $part) {
            $stockLevel = StockLevel::where('organization_id', $order->organization_id)
                ->where('product_id', $part->product_id)
                ->where('quantity', '>', 0)
                ->orderByDesc('quantity')
                ->first();

            if ($stockLevel === null) {
                throw new InvalidArgumentException("No stock of part '{$part->description}' is available to issue to the order.");
            }

            $this->stockService->recordMovement(
                productId: $part->product_id,
                warehouseId: $stockLevel->warehouse_id,
                movementType: StockMovement::TYPE_MATERIAL_ISSUE,
                direction: StockMovement::DIRECTION_OUT,
                quantity: (float) $part->quantity_used,
                unitCost: (float) ($part->unit_cost ?? $stockLevel->average_cost ?? 0),
                referenceType: 'maintenance_order',
                referenceId: $order->id,
                referenceNumber: $order->order_number,
                createdBy: $userId,
            );
        }
    }
}
