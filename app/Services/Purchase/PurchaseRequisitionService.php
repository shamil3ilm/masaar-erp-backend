<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\PurchaseRequisitionLine;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Purchase requisitions and their conversion to purchase orders.
 *
 * Every change of a requisition's status runs on the locked requisition and
 * re-checks its guard there, so a stale copy cannot approve, convert or edit
 * a requisition another request has moved on.
 */
class PurchaseRequisitionService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Paginated list with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        return PurchaseRequisition::with(['lines.product', 'requester', 'approver'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['requested_by']), fn($q) => $q->where('requested_by', $filters['requested_by']))
            ->when(isset($filters['requisition_type']), fn($q) => $q->where('requisition_type', $filters['requisition_type']))
            ->when(isset($filters['start_date']), fn($q) => $q->where('requisition_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn($q) => $q->where('requisition_date', '<=', $filters['end_date']))
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where('requisition_number', 'like', "%{$filters['search']}%");
            })
            ->orderByDesc('requisition_date')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a requisition with its lines.
     */
    public function store(array $data): PurchaseRequisition
    {
        return DB::transaction(function () use ($data): PurchaseRequisition {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (empty($data['requisition_number'])) {
                $data['requisition_number'] = $this->numberGenerator->generate('PR');
            }

            $data['organization_id'] = auth()->user()->organization_id;
            $data['requested_by'] = $data['requested_by'] ?? auth()->id();
            $data['status'] = PurchaseRequisition::STATUS_DRAFT;

            $requisition = PurchaseRequisition::create($data);

            foreach ($lines as $lineData) {
                $requisition->lines()->create($lineData);
            }

            return $requisition->load(['lines.product', 'requester']);
        });
    }

    /**
     * Update the header of a draft requisition.
     */
    public function update(PurchaseRequisition $requisition, array $data): PurchaseRequisition
    {
        return $requisition->lockForTransition(function (PurchaseRequisition $requisition) use ($data): PurchaseRequisition {
            if (! $requisition->isDraft()) {
                throw new \InvalidArgumentException('Only draft requisitions can be updated.');
            }

            $requisition->update($data);

            return $requisition->fresh(['lines.product', 'requester']);
        });
    }

    /**
     * Delete (soft-delete) a draft requisition with its lines.
     */
    public function delete(PurchaseRequisition $requisition): void
    {
        $requisition->lockForTransition(function (PurchaseRequisition $requisition): void {
            if (! $requisition->isDraft()) {
                throw new \InvalidArgumentException('Only draft requisitions can be deleted.');
            }

            $requisition->lines()->delete();
            $requisition->delete();
        });
    }

    /**
     * Transition a draft requisition to pending_approval.
     */
    public function submit(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return $requisition->lockForTransition(function (PurchaseRequisition $requisition): PurchaseRequisition {
            if (! $requisition->canBeSubmitted()) {
                throw new \InvalidArgumentException('Only draft requisitions can be submitted for approval.');
            }

            if ($requisition->lines()->count() === 0) {
                throw new \InvalidArgumentException('Cannot submit a requisition with no lines.');
            }

            $requisition->update(['status' => PurchaseRequisition::STATUS_PENDING_APPROVAL]);

            return $requisition->fresh(['lines.product', 'requester']);
        });
    }

    /**
     * Approve a pending requisition.
     */
    public function approve(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return $requisition->lockForTransition(function (PurchaseRequisition $requisition): PurchaseRequisition {
            if (! $requisition->canBeApproved()) {
                throw new \InvalidArgumentException('Only pending-approval requisitions can be approved.');
            }

            $requisition->update([
                'status' => PurchaseRequisition::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $requisition->fresh(['lines.product', 'requester', 'approver']);
        });
    }

    /**
     * Convert an approved requisition to a purchase order.
     *
     * Creates one PO per preferred vendor (or one combined PO when no preferred vendor is set).
     * Lines are grouped by preferred_vendor_id; lines without a vendor go into a "no-vendor" PO.
     * The requisition stays locked until its lines are marked converted, so two
     * requests cannot both create orders from the same open lines.
     *
     * @return PurchaseOrder[]
     */
    public function convertToPurchaseOrder(PurchaseRequisition $requisition): array
    {
        return $requisition->lockForTransition(function (PurchaseRequisition $requisition): array {
            if (! $requisition->canBeConverted()) {
                throw new \InvalidArgumentException('Only approved requisitions with open lines can be converted to a purchase order.');
            }

            $convertibleLines = $requisition->lines()->convertible()->with('product')->get();

            // Group by preferred vendor
            $grouped = $convertibleLines->groupBy(fn(PurchaseRequisitionLine $l) => $l->preferred_vendor_id ?? 'no_vendor');

            $orders = [];

            // The requisition's own organization supplies the fallback currency.
            $baseCurrency = $requisition->organization()->value('base_currency') ?? 'USD';

            foreach ($grouped as $vendorKey => $lines) {
                $vendorId = $vendorKey === 'no_vendor' ? null : (int) $vendorKey;

                // Orders have no requisition number column; the notes name the requisition.
                $poData = [
                    'organization_id' => $requisition->organization_id,
                    'order_date' => now()->toDateString(),
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'supplier_id' => $vendorId,
                    'notes' => "Created from PR: {$requisition->requisition_number}",
                    'requested_by' => $requisition->requested_by,
                ];

                if (!$vendorId) {
                    // Cannot create PO without supplier — skip
                    continue;
                }

                $poNumber = $this->numberGenerator->generate('PO');
                $poData['order_number'] = $poNumber;

                // The vendor is looked up in the requisition's organization, so a
                // line naming another organization's contact is refused rather
                // than ordered from.
                $supplier = \App\Models\Sales\Contact::where('organization_id', $requisition->organization_id)
                    ->find($vendorId);

                if ($supplier === null) {
                    throw new \InvalidArgumentException('A preferred vendor of this requisition was not found.');
                }

                // Use the supplier's own currency when set; otherwise fall back
                // to the organization's base currency.
                $poData['supplier_name']  = $supplier->getDisplayName();
                $poData['supplier_email'] = $supplier->email;
                $poData['currency_code']  = $supplier->currency_code ?? $baseCurrency;

                $poData['exchange_rate'] = 1;
                $poData['subtotal'] = 0;
                $poData['tax_amount'] = 0;
                $poData['total'] = 0;

                $order = PurchaseOrder::create($poData);

                foreach ($lines as $index => $line) {
                    $order->lines()->create([
                        'product_id'  => $line->product_id,
                        'variant_id'  => $line->variant_id,
                        'description' => $line->product?->name ?? '',
                        'quantity'    => $line->quantity,
                        'unit_id'     => $line->uom_id,
                        'unit_price'  => $line->estimated_unit_price ?? 0,
                        'warehouse_id' => $line->warehouse_id,
                        // Tax is intentionally zero here: a purchase requisition is a
                        // non-taxed internal request document. Tax will be calculated
                        // when the PO is confirmed and the bill is generated.
                        'tax_rate'    => 0,
                        'tax_amount'  => 0,
                        'subtotal'    => bcmul((string) $line->quantity, (string) ($line->estimated_unit_price ?? 0), 4),
                        'line_order'  => $index,
                    ]);

                    $line->update(['status' => 'converted']);
                }

                $order->recalculateTotals();
                $orders[] = $order->fresh(['lines', 'supplier']);
            }

            // Update requisition status if all convertible lines are now converted
            $stillOpen = $requisition->lines()->whereIn('status', ['open', 'partially_converted'])->count();
            if ($stillOpen === 0) {
                $requisition->update(['status' => PurchaseRequisition::STATUS_CONVERTED_TO_PO]);
            }

            return $orders;
        });
    }

    /**
     * Cancel a requisition.
     */
    public function cancel(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return $requisition->lockForTransition(function (PurchaseRequisition $requisition): PurchaseRequisition {
            if (! $requisition->canBeCancelled()) {
                throw new \InvalidArgumentException('This requisition cannot be cancelled in its current status.');
            }

            $requisition->lines()->whereIn('status', ['open', 'partially_converted'])
                ->update(['status' => 'cancelled']);

            $requisition->update(['status' => PurchaseRequisition::STATUS_CANCELLED]);

            return $requisition->fresh(['lines.product', 'requester']);
        });
    }
}
