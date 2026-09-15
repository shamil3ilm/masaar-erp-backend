<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\BomCoProduct;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderCoProductActual;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Co-products and by-products planned on a BOM and recorded on a work order.
 *
 * Actuals are recorded only against a work order of the caller's organization
 * and carry that work order's organization.
 */
class CoProductService
{
    /**
     * One of the organization's BOM templates.
     */
    public function findBomOrFail(int $bomId): BomTemplate
    {
        return BomTemplate::findOrFail($bomId);
    }

    /**
     * A co-product of the given BOM.
     */
    public function findCoProductOrFail(BomTemplate $bom, int $coProductId): BomCoProduct
    {
        return BomCoProduct::where('bom_template_id', $bom->id)->findOrFail($coProductId);
    }

    /**
     * An actual recorded on the given work order.
     */
    public function findActualOrFail(int $workOrderId, int $actualId): WorkOrderCoProductActual
    {
        return WorkOrderCoProductActual::where('work_order_id', $workOrderId)->findOrFail($actualId);
    }

    public function getForBom(int $bomId): Collection
    {
        return BomCoProduct::with('product')
            ->forBom($bomId)
            ->orderBy('co_product_type')
            ->get();
    }

    public function addCoProduct(BomTemplate $bom, array $data): BomCoProduct
    {
        return BomCoProduct::create([
            'organization_id' => $bom->organization_id,
            'bom_template_id' => $bom->id,
            ...$data,
        ]);
    }

    public function updateCoProduct(BomCoProduct $coProduct, array $data): BomCoProduct
    {
        $coProduct->update($data);

        return $coProduct->fresh();
    }

    public function removeCoProduct(BomCoProduct $coProduct): void
    {
        $coProduct->delete();
    }

    public function getForWorkOrder(int $workOrderId): Collection
    {
        return WorkOrderCoProductActual::with(['product', 'warehouse', 'bomCoProduct'])
            ->where('work_order_id', $workOrderId)
            ->get();
    }

    /**
     * Create or update the co/by-product actuals of one of the organization's
     * work orders, one per product, in a single transaction.
     *
     * @param  array<int, array{product_id: int, co_product_type?: string, actual_quantity: float, planned_quantity?: float, unit_of_measure?: string, warehouse_id?: int, bom_co_product_id?: int}>  $actuals
     * @return array<int, WorkOrderCoProductActual>
     */
    public function postActual(int $workOrderId, array $actuals): array
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        return DB::transaction(function () use ($workOrder, $actuals): array {
            $results = [];

            foreach ($actuals as $actualData) {
                $attributes = [
                    ...$actualData,
                    'organization_id' => $workOrder->organization_id,
                    'work_order_id' => $workOrder->id,
                ];

                $existing = WorkOrderCoProductActual::where('work_order_id', $workOrder->id)
                    ->where('product_id', $actualData['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $existing->update($attributes);
                    $results[] = $existing->fresh();
                } else {
                    $results[] = WorkOrderCoProductActual::create($attributes);
                }
            }

            return $results;
        });
    }

    public function postToStock(WorkOrderCoProductActual $actual): void
    {
        $actual->update(['posted_to_stock' => true]);
    }
}
