<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Events\Manufacturing\WorkOrderStarted;
use App\Models\Accounting\Account;
use App\Models\Inventory\StockMovement;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\MaterialTransaction;
use App\Models\Manufacturing\ProductionLog;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderMaterial;
use App\Models\Manufacturing\WorkOrderOperation;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Work order lifecycle, material movements and production output.
 *
 * Every change runs on the locked work order and re-checks its status there,
 * so two requests holding the same order cannot both release, complete or
 * issue against it. The stock movement, material records and journal entry of
 * a change commit together or not at all.
 */
class WorkOrderService
{
    /** Request sort keys and the work order columns they order by. */
    public const SORT_COLUMNS = [
        'order_number' => 'work_order_number',
        'status' => 'status',
        'start_date' => 'planned_start_date',
        'end_date' => 'planned_end_date',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
        private BomService $bomService,
        private JournalService $journalService,
        private AccountResolver $accountResolver,
    ) {}

    /**
     * The organization's work orders, filtered and sorted for the list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return WorkOrder::with(['product', 'bomTemplate', 'assignedTo', 'sourceWarehouse', 'targetWarehouse'])
            ->withCount(['materials', 'operations', 'productionLogs'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->forProduct($id))
            ->when($filters['assigned_to'] ?? null, fn ($q, $id) => $q->assignedTo($id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when(($filters['overdue'] ?? null) === 'true', fn ($q) => $q->overdue())
            ->when(($filters['active'] ?? null) === 'true', fn ($q) => $q->active())
            ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->where('planned_start_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->where('planned_end_date', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('work_order_number', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy(self::SORT_COLUMNS[$sortBy] ?? 'created_at', $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a work order from one of the organization's BOM templates.
     */
    public function createFromTemplate(int $bomTemplateId, array $data, int $userId): WorkOrder
    {
        return $this->create(BomTemplate::findOrFail($bomTemplateId), $data, $userId);
    }

    /**
     * Create a work order from a BOM template.
     */
    public function create(BomTemplate $bom, array $data, int $userId): WorkOrder
    {
        $bom->loadMissing(['lines.product', 'operations']);

        if (! $bom->isActive()) {
            throw new \InvalidArgumentException('BOM template must be active to create a work order.');
        }

        return DB::transaction(function () use ($bom, $data, $userId) {
            $quantity = (float) $data['planned_quantity'];

            $costs = $bom->calculateTotalCost($quantity);

            $workOrder = WorkOrder::create([
                'organization_id' => auth()->user()->organization_id,
                'branch_id' => $data['branch_id'] ?? null,
                'work_order_number' => $this->numberGenerator->generate('WO'),
                'bom_template_id' => $bom->id,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'sales_order_line_id' => $data['sales_order_line_id'] ?? null,
                'product_id' => $bom->product_id,
                'variant_id' => $bom->variant_id,
                'planned_quantity' => $quantity,
                'produced_quantity' => 0,
                'rejected_quantity' => 0,
                'unit_id' => $bom->output_unit_id,
                'planned_start_date' => $data['planned_start_date'],
                'planned_end_date' => $data['planned_end_date'],
                'source_warehouse_id' => $data['source_warehouse_id'] ?? $bom->default_warehouse_id,
                'target_warehouse_id' => $data['target_warehouse_id'] ?? $bom->default_warehouse_id,
                'estimated_material_cost' => $costs['material_cost'],
                'estimated_labor_cost' => $costs['labor_cost'],
                'estimated_overhead_cost' => $costs['overhead_cost'],
                'status' => WorkOrder::STATUS_DRAFT,
                'priority' => $data['priority'] ?? WorkOrder::PRIORITY_NORMAL,
                'assigned_to' => $data['assigned_to'] ?? null,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->createMaterialsFromBom($workOrder, $bom, $quantity);
            $this->createOperationsFromBom($workOrder, $bom);

            return $workOrder->fresh(['materials.product', 'operations', 'bomTemplate']);
        });
    }

    /**
     * Create work order materials from BOM lines.
     */
    protected function createMaterialsFromBom(WorkOrder $workOrder, BomTemplate $bom, float $quantity): void
    {
        $multiplier = (float) bcdiv((string) $quantity, (string) $bom->output_quantity, 6);

        foreach ($bom->lines as $line) {
            WorkOrderMaterial::create([
                'work_order_id' => $workOrder->id,
                'bom_line_id' => $line->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'description' => $line->description,
                'required_quantity' => $line->getAdjustedQuantity($multiplier),
                'issued_quantity' => 0,
                'consumed_quantity' => 0,
                'returned_quantity' => 0,
                'wastage_quantity' => 0,
                'unit_id' => $line->unit_id,
                'unit_cost' => $line->unit_cost ?? $line->product->purchase_price ?? 0,
                'total_cost' => 0,
                'warehouse_id' => $line->warehouse_id ?? $workOrder->source_warehouse_id,
                'line_order' => $line->line_order,
            ]);
        }
    }

    /**
     * Create work order operations from BOM operations.
     */
    protected function createOperationsFromBom(WorkOrder $workOrder, BomTemplate $bom): void
    {
        foreach ($bom->operations as $operation) {
            WorkOrderOperation::create([
                'work_order_id' => $workOrder->id,
                'bom_operation_id' => $operation->id,
                'name' => $operation->name,
                'instructions' => $operation->instructions,
                'sequence' => $operation->sequence,
                'estimated_minutes' => $operation->estimated_minutes,
                'actual_minutes' => 0,
                'status' => WorkOrderOperation::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Update a work order that has not started.
     */
    public function update(WorkOrder $workOrder, array $data): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($data): WorkOrder {
            if (! $workOrder->canBeEdited()) {
                throw new \InvalidArgumentException('Work order cannot be edited in its current status.');
            }

            $workOrder->update($data);

            return $workOrder->fresh();
        });
    }

    /**
     * Delete a draft work order with its materials and operations.
     */
    public function delete(WorkOrder $workOrder): void
    {
        $workOrder->lockForTransition(function (WorkOrder $workOrder): void {
            if (! $workOrder->isDraft()) {
                throw new \InvalidArgumentException('Only draft work orders can be deleted.');
            }

            $workOrder->materials()->delete();
            $workOrder->operations()->delete();
            $workOrder->delete();
        });
    }

    /**
     * Release a draft work order for production once its critical materials are available.
     */
    public function release(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder): WorkOrder {
            if (! $workOrder->isDraft()) {
                throw new \InvalidArgumentException('Only draft work orders can be released.');
            }

            $workOrder->load(['bomTemplate.lines.product', 'bomTemplate.operations']);

            $availability = $this->bomService->checkAvailability(
                $workOrder->bomTemplate,
                (float) $workOrder->planned_quantity,
                $workOrder->source_warehouse_id
            );

            if ($availability['critical_shortage']) {
                throw new \InvalidArgumentException('Cannot release work order. Critical materials are not available.');
            }

            $workOrder->transitionTo(WorkOrder::STATUS_RELEASED);

            return $workOrder->fresh();
        });
    }

    /**
     * Schedule a work order that has not started.
     */
    public function schedule(WorkOrder $workOrder, array $data): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($data): WorkOrder {
            if (! in_array($workOrder->status, [WorkOrder::STATUS_DRAFT, WorkOrder::STATUS_RELEASED], true)) {
                throw new \InvalidArgumentException('Only a work order that has not started can be scheduled.');
            }

            $workOrder->update([
                'planned_start_date' => $data['planned_start_date'] ?? $workOrder->planned_start_date,
                'planned_end_date' => $data['planned_end_date'] ?? $workOrder->planned_end_date,
                'assigned_to' => $data['assigned_to'] ?? $workOrder->assigned_to,
            ]);

            return $workOrder->fresh();
        });
    }

    /**
     * Start a work order. The started event is dispatched once the start has committed.
     */
    public function start(WorkOrder $workOrder): WorkOrder
    {
        $started = $workOrder->lockForTransition(function (WorkOrder $workOrder): WorkOrder {
            if (! $workOrder->canBeStarted()) {
                throw new \InvalidArgumentException('Work order cannot be started in its current status.');
            }

            $workOrder->start();

            return $workOrder->fresh();
        });

        WorkOrderStarted::dispatch($started);

        return $started;
    }

    /**
     * Issue materials from stock to an in-progress work order. A material is
     * never issued beyond its required quantity, counting what was issued before.
     */
    public function issueMaterials(WorkOrder $workOrder, array $issues, int $userId): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($issues, $userId): WorkOrder {
            if (! $workOrder->isInProgress()) {
                throw new \InvalidArgumentException('Materials can only be issued to in-progress work orders.');
            }

            $materials = $this->lockMaterials($workOrder, array_column($issues, 'work_order_material_id'));

            foreach ($issues as $issue) {
                $material = $this->materialIn($materials, (int) $issue['work_order_material_id']);
                $quantity = (float) $issue['quantity'];

                $issuedAfter = bcadd((string) $material->issued_quantity, (string) $quantity, 4);
                if (bccomp($issuedAfter, (string) $material->required_quantity, 4) > 0) {
                    throw new \InvalidArgumentException('Cannot issue more material than required.');
                }

                $warehouseId = $issue['warehouse_id'] ?? $material->warehouse_id;

                if ($warehouseId) {
                    $this->stockService->recordMovement(
                        productId: $material->product_id,
                        warehouseId: $warehouseId,
                        movementType: StockMovement::TYPE_MATERIAL_ISSUE,
                        direction: StockMovement::DIRECTION_OUT,
                        quantity: $quantity,
                        unitCost: (float) $material->unit_cost,
                        referenceType: WorkOrder::class,
                        referenceId: $workOrder->id,
                        notes: "Material issue for WO: {$workOrder->work_order_number}",
                    );
                }

                $this->recordMaterialTransaction($workOrder, $material, MaterialTransaction::TYPE_ISSUE, $quantity, $warehouseId, $issue, $userId);

                $material->recordIssue($quantity);
            }

            $this->recalculateActualMaterialCost($workOrder);

            return $workOrder->fresh(['materials.product']);
        });
    }

    /**
     * Return issued materials from a work order to stock.
     */
    public function returnMaterials(WorkOrder $workOrder, array $returns, int $userId = 0): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($returns, $userId): WorkOrder {
            $this->returnOnLockedOrder($workOrder, $returns, $userId);

            return $workOrder->fresh(['materials.product']);
        });
    }

    /**
     * Record material consumption and wastage against issued quantities.
     */
    public function consumeMaterials(WorkOrder $workOrder, array $consumptions, int $userId): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($consumptions, $userId): WorkOrder {
            $materials = $this->lockMaterials($workOrder, array_column($consumptions, 'work_order_material_id'));

            foreach ($consumptions as $consumption) {
                $material = $this->materialIn($materials, (int) $consumption['work_order_material_id']);
                $quantity = (float) $consumption['quantity'];
                $wastageQuantity = (float) ($consumption['wastage_quantity'] ?? 0);

                if (($quantity + $wastageQuantity) > $material->getAvailableQuantity()) {
                    throw new \InvalidArgumentException("Insufficient issued quantity for {$material->product->name}.");
                }

                $material->recordConsumption($quantity);

                if ($wastageQuantity > 0) {
                    $material->recordWastage($wastageQuantity);

                    $this->recordMaterialTransaction(
                        $workOrder,
                        $material,
                        MaterialTransaction::TYPE_WASTAGE,
                        $wastageQuantity,
                        $material->warehouse_id,
                        ['notes' => $consumption['wastage_reason'] ?? 'Material wastage'],
                        $userId,
                    );
                }
            }

            $this->recalculateActualMaterialCost($workOrder);

            return $workOrder->fresh(['materials.product']);
        });
    }

    /**
     * Record production output and receive the good quantity into the target warehouse.
     */
    public function recordProduction(WorkOrder $workOrder, array $data, int $userId): ProductionLog
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($data, $userId): ProductionLog {
            if (! $workOrder->isInProgress()) {
                throw new \InvalidArgumentException('Production can only be recorded for in-progress work orders.');
            }

            $quantityProduced = (float) $data['quantity_produced'];
            $quantityRejected = (float) ($data['quantity_rejected'] ?? 0);
            $goodQuantity = $quantityProduced - $quantityRejected;

            if ($goodQuantity > 0 && $workOrder->target_warehouse_id) {
                $this->stockService->recordMovement(
                    productId: $workOrder->product_id,
                    warehouseId: $workOrder->target_warehouse_id,
                    movementType: StockMovement::TYPE_PRODUCTION_IN,
                    direction: StockMovement::DIRECTION_IN,
                    quantity: $goodQuantity,
                    unitCost: $workOrder->getUnitCost(),
                    referenceType: WorkOrder::class,
                    referenceId: $workOrder->id,
                    notes: "Production from WO: {$workOrder->work_order_number}",
                );
            }

            $log = ProductionLog::create([
                'organization_id' => $workOrder->organization_id,
                'work_order_id' => $workOrder->id,
                'logged_at' => $data['logged_at'] ?? now(),
                'quantity_produced' => $quantityProduced,
                'quantity_rejected' => $quantityRejected,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'is_quality_checked' => false,
                'batch_number' => $data['batch_number'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'logged_by' => $userId,
            ]);

            $workOrder->update([
                'produced_quantity' => bcadd((string) $workOrder->produced_quantity, (string) $quantityProduced, 4),
                'rejected_quantity' => bcadd((string) $workOrder->rejected_quantity, (string) $quantityRejected, 4),
            ]);

            return $log;
        });
    }

    /**
     * Complete a work order: return unused materials, settle actual costs, skip
     * pending operations and post the finished-goods journal.
     */
    public function complete(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder): WorkOrder {
            if (! $workOrder->canBeCompleted()) {
                throw new \InvalidArgumentException('Work order cannot be completed in its current status.');
            }

            $this->returnOnLockedOrder($workOrder, $this->unusedMaterialReturns($workOrder, 'Auto-return on work order completion'));

            $this->recalculateActualCosts($workOrder);

            foreach ($workOrder->operations()->pending()->get() as $operation) {
                $operation->skip('Auto-skipped on work order completion');
            }

            $workOrder->complete();

            $this->postCompletionJournal($workOrder->fresh());

            return $workOrder->fresh();
        });
    }

    /**
     * Cancel a work order and return its unused materials to stock.
     */
    public function cancel(WorkOrder $workOrder, string $reason): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($reason): WorkOrder {
            if (! $workOrder->canBeCancelled()) {
                throw new \InvalidArgumentException('Work order cannot be cancelled in its current status.');
            }

            $this->returnOnLockedOrder($workOrder, $this->unusedMaterialReturns($workOrder, 'Material return due to work order cancellation'));

            $workOrder->cancel($reason);

            return $workOrder->fresh();
        });
    }

    /**
     * Start one of the work order's operations.
     */
    public function startOperation(WorkOrder $workOrder, int $operationId): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($operationId): WorkOrder {
            $operation = $this->lockOperation($workOrder, $operationId);

            if (! $operation->canBeStarted()) {
                throw new \InvalidArgumentException('Operation cannot be started in its current status.');
            }

            $operation->start();

            return $workOrder->fresh(['operations']);
        });
    }

    /**
     * Complete one of the work order's operations.
     */
    public function completeOperation(WorkOrder $workOrder, int $operationId, ?int $actualMinutes, ?string $notes): WorkOrder
    {
        return $workOrder->lockForTransition(function (WorkOrder $workOrder) use ($operationId, $actualMinutes, $notes): WorkOrder {
            $operation = $this->lockOperation($workOrder, $operationId);

            if (! $operation->canBeCompleted()) {
                throw new \InvalidArgumentException('Operation cannot be completed in its current status.');
            }

            $operation->complete($actualMinutes, $notes);

            return $workOrder->fresh(['operations']);
        });
    }

    /**
     * Return materials to stock; the caller holds the lock on the work order.
     */
    private function returnOnLockedOrder(WorkOrder $workOrder, array $returns, int $userId = 0): void
    {
        if ($returns === []) {
            return;
        }

        $materials = $this->lockMaterials($workOrder, array_column($returns, 'work_order_material_id'));

        foreach ($returns as $return) {
            $material = $this->materialIn($materials, (int) $return['work_order_material_id']);
            $quantity = (float) $return['quantity'];
            $warehouseId = $return['warehouse_id'] ?? $material->warehouse_id;

            if ($quantity > $material->getAvailableQuantity()) {
                throw new \InvalidArgumentException("Cannot return more than available quantity for {$material->product->name}.");
            }

            if ($warehouseId) {
                $this->stockService->recordMovement(
                    productId: $material->product_id,
                    warehouseId: $warehouseId,
                    movementType: StockMovement::TYPE_MATERIAL_RETURN,
                    direction: StockMovement::DIRECTION_IN,
                    quantity: $quantity,
                    unitCost: (float) $material->unit_cost,
                    referenceType: WorkOrder::class,
                    referenceId: $workOrder->id,
                    notes: "Material return from WO: {$workOrder->work_order_number}",
                );
            }

            $this->recordMaterialTransaction($workOrder, $material, MaterialTransaction::TYPE_RETURN, $quantity, $warehouseId, $return, $userId ?: null);

            $material->recordReturn($quantity);
        }

        $this->recalculateActualMaterialCost($workOrder);
    }

    /**
     * A return line for every material that still holds an issued, unused quantity.
     *
     * @return list<array<string, mixed>>
     */
    private function unusedMaterialReturns(WorkOrder $workOrder, string $note): array
    {
        return $workOrder->materials()->get()
            ->filter(fn (WorkOrderMaterial $material) => $material->getAvailableQuantity() > 0)
            ->map(fn (WorkOrderMaterial $material) => [
                'work_order_material_id' => $material->id,
                'quantity' => $material->getAvailableQuantity(),
                'notes' => $note,
            ])
            ->values()
            ->all();
    }

    /**
     * The work order's materials with the given ids, locked until the transaction ends.
     *
     * @return Collection<int, WorkOrderMaterial>
     */
    private function lockMaterials(WorkOrder $workOrder, array $materialIds): Collection
    {
        return WorkOrderMaterial::with('product')
            ->whereIn('id', $materialIds)
            ->where('work_order_id', $workOrder->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function materialIn(Collection $materials, int $materialId): WorkOrderMaterial
    {
        return $materials->get($materialId)
            ?? throw new \InvalidArgumentException('Material does not belong to this work order.');
    }

    private function lockOperation(WorkOrder $workOrder, int $operationId): WorkOrderOperation
    {
        return WorkOrderOperation::whereKey($operationId)
            ->where('work_order_id', $workOrder->id)
            ->lockForUpdate()
            ->first()
            ?? throw new \InvalidArgumentException('Operation does not belong to this work order.');
    }

    private function recordMaterialTransaction(
        WorkOrder $workOrder,
        WorkOrderMaterial $material,
        string $type,
        float $quantity,
        ?int $warehouseId,
        array $details,
        ?int $userId,
    ): void {
        MaterialTransaction::create([
            'organization_id' => $workOrder->organization_id,
            'work_order_id' => $workOrder->id,
            'work_order_material_id' => $material->id,
            'transaction_type' => $type,
            'transaction_datetime' => now(),
            'quantity' => $quantity,
            'unit_cost' => $material->unit_cost,
            'warehouse_id' => $warehouseId,
            'reference' => $details['reference'] ?? null,
            'notes' => $details['notes'] ?? null,
            'processed_by' => $userId,
        ]);
    }

    /**
     * Recalculate actual material cost.
     */
    protected function recalculateActualMaterialCost(WorkOrder $workOrder): void
    {
        $workOrder->update(['actual_material_cost' => $workOrder->materials()->sum('total_cost')]);
    }

    /**
     * Recalculate material, labor and overhead actual costs.
     */
    protected function recalculateActualCosts(WorkOrder $workOrder): void
    {
        $materialCost = $workOrder->materials()->sum('total_cost');

        $laborCost = 0;
        foreach ($workOrder->operations()->with('bomOperation')->get() as $operation) {
            if ($operation->bomOperation && $operation->actual_minutes > 0) {
                $hours = $operation->actual_minutes / 60;
                $laborCost = bcadd(
                    (string) $laborCost,
                    bcmul((string) $hours, (string) ($operation->bomOperation->labor_cost_per_hour ?? 0), 4),
                    4
                );
            }
        }

        // Overhead is absorbed in proportion to the quantity produced.
        $plannedQty = (string) $workOrder->planned_quantity;
        if (bccomp($plannedQty, '0', 4) <= 0) {
            $overheadCost = '0.0000';
        } else {
            $multiplier = bcdiv((string) $workOrder->produced_quantity, $plannedQty, 4);
            $overheadCost = bcmul((string) $workOrder->estimated_overhead_cost, $multiplier, 4);
        }

        $workOrder->update([
            'actual_material_cost' => $materialCost,
            'actual_labor_cost' => $laborCost,
            'actual_overhead_cost' => $overheadCost,
        ]);
    }

    /**
     * Journal the completed work order: debit finished goods, credit WIP.
     *
     * Both accounts are the work order's organization's own. Finished goods is
     * the account mapped as fg_inventory_account_id, or the organization's
     * inventory account; WIP is the account mapped as wip_account_id. Without
     * both, or with one account for both roles, nothing is posted.
     */
    protected function postCompletionJournal(WorkOrder $workOrder): void
    {
        $organizationId = (int) $workOrder->organization_id;

        $finishedGoods = $this->accountResolver->mapped($organizationId, 'fg_inventory_account_id')
            ?? $this->accountResolver->bySubType($organizationId, Account::SUBTYPE_INVENTORY);
        $wip = $this->accountResolver->mapped($organizationId, 'wip_account_id');

        if ($finishedGoods === null || $wip === null || $finishedGoods->is($wip)) {
            return;
        }

        $totalCost = bcadd(
            bcadd((string) $workOrder->actual_material_cost, (string) $workOrder->actual_labor_cost, 4),
            (string) $workOrder->actual_overhead_cost,
            4
        );

        if (bccomp($totalCost, '0', 4) <= 0) {
            return;
        }

        $this->journalService->createEntry([
            'organization_id' => $organizationId,
            'entry_date' => now(),
            'reference' => $workOrder->work_order_number,
            'description' => "Work Order Completion: {$workOrder->work_order_number}",
            'source_type' => WorkOrder::class,
            'source_id' => $workOrder->id,
        ], [
            [
                'account_id' => $finishedGoods->id,
                'description' => "FG Receipt - {$workOrder->work_order_number}",
                'debit' => $totalCost,
                'credit' => 0,
            ],
            [
                'account_id' => $wip->id,
                'description' => "WIP Clearance - {$workOrder->work_order_number}",
                'debit' => 0,
                'credit' => $totalCost,
            ],
        ]);
    }

    /**
     * Get work order statistics.
     */
    public function getStatistics(?array $filters = []): array
    {
        $query = WorkOrder::query()
            ->where('organization_id', auth()->user()->organization_id);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->startingBetween($filters['start_date'], $filters['end_date']);
        }

        $total = $query->count();
        $draft = (clone $query)->draft()->count();
        $released = (clone $query)->released()->count();
        $inProgress = (clone $query)->inProgress()->count();
        $completed = (clone $query)->completed()->count();
        $cancelled = (clone $query)->cancelled()->count();
        $overdue = (clone $query)->overdue()->count();

        $totalPlanned = (clone $query)->sum('planned_quantity');
        $totalProduced = (clone $query)->sum('produced_quantity');
        $totalRejected = (clone $query)->sum('rejected_quantity');

        $avgCompletionRate = bccomp((string) $totalPlanned, '0', 4) > 0
            ? bcmul(bcdiv((string) $totalProduced, (string) $totalPlanned, 6), '100', 2)
            : '0.00';

        $avgRejectionRate = bccomp((string) $totalProduced, '0', 4) > 0
            ? bcmul(bcdiv((string) $totalRejected, (string) $totalProduced, 6), '100', 2)
            : '0.00';

        return [
            'total' => $total,
            'draft' => $draft,
            'released' => $released,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'overdue' => $overdue,
            'total_planned_quantity' => (float) $totalPlanned,
            'total_produced_quantity' => (float) $totalProduced,
            'total_rejected_quantity' => (float) $totalRejected,
            'avg_completion_rate' => $avgCompletionRate,
            'avg_rejection_rate' => $avgRejectionRate,
        ];
    }

    /**
     * Get production schedule for date range (capped at 90 days).
     */
    public function getProductionSchedule($startDate, $endDate): array
    {
        // Cap to 90 days to prevent unbounded loads.
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate)->min($start->copy()->addDays(90));

        $workOrders = WorkOrder::active()
            ->startingBetween($start->toDateString(), $end->toDateString())
            ->with(['product:id,name,sku', 'bomTemplate:id,name', 'assignedTo:id,name'])
            ->orderBy('planned_start_date')
            ->orderBy('priority', 'desc')
            ->limit(500)
            ->get();

        return $workOrders->groupBy(fn ($wo) => $wo->planned_start_date->format('Y-m-d'))
            ->map(fn ($group) => $group->values())
            ->toArray();
    }
}
