<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionResourceTool;
use App\Models\Manufacturing\ToolOperationAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Production resources and tools and their assignment to work orders and
 * routing operations.
 *
 * Assigning and releasing run on the locked tool, so units in use are counted
 * from the current row and an assignment gives its units back once.
 */
class ProductionResourceToolService
{
    public function list(array $filters = []): Collection
    {
        return ProductionResourceTool::query()
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['prt_type']), fn($q) => $q->forType($filters['prt_type']))
            ->when(isset($filters['search']), function ($q) use ($filters): void {
                $q->where(function ($query) use ($filters): void {
                    $query->where('prt_number', 'like', "%{$filters['search']}%")
                        ->orWhere('prt_name', 'like', "%{$filters['search']}%");
                });
            })
            ->orderBy('prt_number')
            ->get();
    }

    /**
     * One of the organization's tools.
     *
     * @param  list<string>  $with
     */
    public function findOrFail(int $id, array $with = []): ProductionResourceTool
    {
        return ProductionResourceTool::with($with)->findOrFail($id);
    }

    /**
     * An assignment of the given tool.
     */
    public function findAssignmentOrFail(ProductionResourceTool $prt, int $assignmentId): ToolOperationAssignment
    {
        return ToolOperationAssignment::where('production_resource_tool_id', $prt->id)->findOrFail($assignmentId);
    }

    public function create(array $data): ProductionResourceTool
    {
        return ProductionResourceTool::create($data);
    }

    public function update(ProductionResourceTool $prt, array $data): ProductionResourceTool
    {
        $prt->update($data);

        return $prt->fresh();
    }

    public function delete(ProductionResourceTool $prt): void
    {
        $prt->delete();
    }

    /**
     * Assign units of an available tool, counting units in use on the locked tool.
     */
    public function assign(ProductionResourceTool $prt, array $data): ToolOperationAssignment
    {
        return $prt->lockForTransition(function (ProductionResourceTool $prt) use ($data): ToolOperationAssignment {
            $quantityRequired = (int) ($data['quantity_required'] ?? 1);

            if (!$prt->isAvailable()) {
                throw ValidationException::withMessages([
                    'prt' => 'This tool/resource is not currently available for assignment.',
                ]);
            }

            $availableUnits = $prt->quantity_available - $prt->quantity_in_use;

            if ($availableUnits < $quantityRequired) {
                throw ValidationException::withMessages([
                    'quantity_required' => "Only {$availableUnits} unit(s) available, but {$quantityRequired} requested.",
                ]);
            }

            $assignment = ToolOperationAssignment::create([
                'organization_id' => $prt->organization_id,
                'production_resource_tool_id' => $prt->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                ...$data,
            ]);

            $prt->assignTo($data['work_order_id'] ?? 0, $quantityRequired);

            return $assignment;
        });
    }

    /**
     * Release an assignment and give its units back to the tool; an assignment
     * already released is refused.
     */
    public function release(ToolOperationAssignment $assignment): void
    {
        $prt = ProductionResourceTool::findOrFail($assignment->production_resource_tool_id);

        $prt->lockForTransition(function (ProductionResourceTool $prt) use ($assignment): void {
            $assignment = ToolOperationAssignment::whereKey($assignment->id)
                ->where('production_resource_tool_id', $prt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status === 'released') {
                throw ValidationException::withMessages([
                    'status' => 'This tool assignment has already been released.',
                ]);
            }

            $assignment->update([
                'status' => 'released',
                'released_at' => now(),
            ]);

            $prt->release($assignment->quantity_required);
        });
    }

    public function getForWorkOrder(int $workOrderId): Collection
    {
        return ToolOperationAssignment::with('productionResourceTool')
            ->where('work_order_id', $workOrderId)
            ->get();
    }

    public function getAvailable(?string $type = null): Collection
    {
        return ProductionResourceTool::available()
            ->when($type !== null, fn($q) => $q->forType($type))
            ->get();
    }
}
