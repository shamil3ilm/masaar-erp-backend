<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\MrpRun;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\PurchaseRequisitionLine;
use App\Services\Purchase\SourceListService;
use Illuminate\Support\Facades\DB;

/**
 * Turns purchase-type planned orders from an MRP run into purchase
 * requisitions.
 */
class MrpProcurementService
{
    public function __construct(
        private readonly SourceListService $sourceListService,
    ) {}

    /**
     * Convert purchase-type planned orders from an MRP run into a single
     * PurchaseRequisition with one line per planned order.
     *
     * @param  int[]|null  $plannedOrderIds  Restrict conversion to these IDs;
     *                                       null converts all eligible orders.
     * @return array{requisition: PurchaseRequisition, converted_count: int, skipped_count: int}
     */
    public function convertPlannedOrdersToPR(MrpRun $run, ?array $plannedOrderIds, int $userId): array
    {
        return DB::transaction(function () use ($run, $plannedOrderIds, $userId): array {
            $query = MrpPlannedOrder::where('mrp_run_id', $run->id)
                ->where('organization_id', $run->organization_id)
                ->where('order_type', MrpPlannedOrder::TYPE_PURCHASE)
                ->whereIn('status', [MrpPlannedOrder::STATUS_PLANNED, MrpPlannedOrder::STATUS_FIRMED])
                ->whereNull('purchase_requisition_id')
                ->with('product');

            if ($plannedOrderIds !== null) {
                $query->whereIn('id', $plannedOrderIds);
            }

            if (!$query->exists()) {
                throw new \InvalidArgumentException(
                    'No eligible purchase planned orders found for this MRP run.'
                );
            }

            // Pre-fetch all unique product IDs cheaply (IDs only, no model hydration).
            $productIds = (clone $query)->distinct()->pluck('product_id')
                ->filter()->values()->toArray();

            // Auto-select preferred vendors for all unique products in one pass.
            $vendorsByProduct = $this->sourceListService->autoSelectVendors($productIds);

            // Create one PR header for the entire run.
            $runDate  = $run->run_date instanceof \DateTimeInterface
                ? $run->run_date->format('Y-m-d')
                : now()->toDateString();

            $requisition = PurchaseRequisition::create([
                'organization_id'   => $run->organization_id,
                'requisition_date'  => now()->toDateString(),
                'requisition_type'  => 'purchase',
                'status'            => PurchaseRequisition::STATUS_DRAFT,
                'requested_by'      => $userId,
                'notes'             => "MRP Auto-PR - Run #{$run->id} - {$runDate}",
            ]);

            $convertedCount = 0;
            $skippedCount   = 0;

            // Process in chunks to avoid loading all planned orders into memory at once.
            $query->chunkById(100, function ($orders) use ($requisition, $vendorsByProduct, &$convertedCount, &$skippedCount) {
                foreach ($orders as $order) {
                    if ($order->product_id === null) {
                        $skippedCount++;
                        continue;
                    }

                    $preferredVendorId = $vendorsByProduct[$order->product_id] ?? null;

                    PurchaseRequisitionLine::create([
                        'requisition_id'       => $requisition->id,
                        'product_id'           => $order->product_id,
                        'quantity'             => $order->planned_quantity,
                        'required_by_date'     => $order->planned_end_date?->toDateString(),
                        'preferred_vendor_id'  => $preferredVendorId,
                        'status'               => 'open',
                        'notes'                => "MRP planned order #{$order->uuid}",
                    ]);

                    $order->update([
                        'status'                  => MrpPlannedOrder::STATUS_CONVERTED,
                        'converted_at'            => now(),
                        'converted_to_type'       => PurchaseRequisition::class,
                        'converted_to_id'         => $requisition->id,
                        'purchase_requisition_id' => $requisition->id,
                    ]);

                    $convertedCount++;
                }
            });

            return [
                'requisition'     => $requisition->load('lines.product'),
                'converted_count' => $convertedCount,
                'skipped_count'   => $skippedCount,
            ];
        });
    }
}
