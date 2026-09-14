<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\ServiceAcceptance;
use App\Models\Purchase\ServiceEntrySheet;
use App\Models\Purchase\ServicePoLine;
use App\Models\Purchase\ServicePurchaseOrder;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ServiceProcurementService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a new Service Purchase Order with its lines.
     */
    public function createServicePO(array $data): ServicePurchaseOrder
    {
        return DB::transaction(function () use ($data): ServicePurchaseOrder {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (empty($data['po_number'])) {
                $data['po_number'] = $this->numberGenerator->generate('SVC-PO');
            }

            $data['status'] = ServicePurchaseOrder::STATUS_DRAFT;
            $data['created_by'] = auth()->id();

            $po = ServicePurchaseOrder::create($data);

            $totalValue = '0';
            foreach ($lines as $index => $lineData) {
                $lineData['line_number'] = $lineData['line_number'] ?? ($index + 1);
                $lineData['total_price'] = bcmul(
                    (string) $lineData['quantity'],
                    (string) $lineData['unit_price'],
                    4
                );
                $po->lines()->create($lineData);
                $totalValue = bcadd($totalValue, (string) $lineData['total_price'], 4);
            }

            $po->update(['total_value' => $totalValue]);

            return $po->load(['vendor', 'lines', 'creator']);
        });
    }

    /**
     * A page of service entry sheets, newest first, with vendor, order and users loaded.
     *
     * @param  array<string, mixed>  $filters  status, vendor_id, service_purchase_order_id, search, from_date, to_date
     */
    public function listEntrySheets(array $filters, int $perPage): LengthAwarePaginator
    {
        return ServiceEntrySheet::with(['vendor', 'servicePurchaseOrder', 'submitter', 'approver'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($filters['service_purchase_order_id'] ?? null, fn ($q, $id) => $q->where('service_purchase_order_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('ses_number', 'like', "%{$search}%"))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->where('service_period_from', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->where('service_period_to', '<=', $date))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A service entry sheet by UUID, with the given relations loaded.
     *
     * @param  list<string>  $with
     */
    public function findEntrySheet(string $uuid, array $with = []): ServiceEntrySheet
    {
        return ServiceEntrySheet::with($with)->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Create a draft service entry sheet with its lines.
     *
     * Each line records the quantity and price actually delivered against a
     * line of the sheet's service order, in that order line's unit.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraftSES(array $data): ServiceEntrySheet
    {
        return DB::transaction(function () use ($data): ServiceEntrySheet {
            $unitsByOrderLine = ServicePoLine::where('service_purchase_order_id', $data['service_purchase_order_id'])
                ->whereIn('id', array_column($data['lines'], 'service_po_line_id'))
                ->pluck('uom', 'id');

            $sheet = ServiceEntrySheet::create([
                'organization_id'           => $data['organization_id'],
                'ses_number'                => $this->numberGenerator->generate('SES'),
                'service_purchase_order_id' => $data['service_purchase_order_id'],
                'vendor_id'                 => $data['vendor_id'],
                'service_period_from'       => $data['service_period_from'],
                'service_period_to'         => $data['service_period_to'],
                'description'               => $data['description'] ?? '',
                'status'                    => ServiceEntrySheet::STATUS_DRAFT,
            ]);

            foreach ($data['lines'] as $line) {
                $sheet->lines()->create([
                    'service_po_line_id' => $line['service_po_line_id'],
                    'uom'                => $unitsByOrderLine->get($line['service_po_line_id']),
                    'actual_quantity'    => $line['quantity'],
                    'actual_price'       => $line['unit_price'],
                    'remarks'            => $line['description'] ?? null,
                    'total_amount'       => bcmul((string) $line['quantity'], (string) $line['unit_price'], 4),
                ]);
            }

            return $sheet->load(['vendor', 'servicePurchaseOrder', 'lines']);
        });
    }

    /**
     * Update a draft service entry sheet, checked on the locked row.
     */
    public function updateDraftSES(ServiceEntrySheet $ses, array $data): ServiceEntrySheet
    {
        return $ses->lockForTransition(function (ServiceEntrySheet $ses) use ($data): ServiceEntrySheet {
            if (! $ses->isEditable()) {
                throw new \InvalidArgumentException('Only draft service entry sheets can be updated.');
            }

            $ses->update($data);

            return $ses->fresh(['vendor', 'servicePurchaseOrder', 'lines']);
        });
    }

    /**
     * Submit a draft service entry sheet for approval, checked on the locked row.
     */
    public function submitDraftSES(ServiceEntrySheet $ses, int $userId): ServiceEntrySheet
    {
        return $ses->lockForTransition(function (ServiceEntrySheet $ses) use ($userId): ServiceEntrySheet {
            if (! $ses->isEditable()) {
                throw new \InvalidArgumentException('Only draft service entry sheets can be submitted.');
            }

            $ses->update([
                'status'       => ServiceEntrySheet::STATUS_SUBMITTED,
                'submitted_by' => $userId,
            ]);

            return $ses->fresh();
        });
    }

    /**
     * Submit a Service Entry Sheet against a Service PO.
     */
    public function submitSES(array $data): ServiceEntrySheet
    {
        return DB::transaction(function () use ($data): ServiceEntrySheet {
            $po = ServicePurchaseOrder::findOrFail($data['service_purchase_order_id']);

            if (!in_array($po->status, [ServicePurchaseOrder::STATUS_SENT, ServicePurchaseOrder::STATUS_PARTIALLY_ACCEPTED], true)) {
                throw new \InvalidArgumentException(
                    'Service PO must be in sent or partially_accepted status to create an entry sheet.'
                );
            }

            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (empty($data['ses_number'])) {
                $data['ses_number'] = $this->numberGenerator->generate('SES');
            }

            $data['vendor_id'] = $data['vendor_id'] ?? $po->vendor_id;
            $data['status'] = ServiceEntrySheet::STATUS_SUBMITTED;
            $data['submitted_by'] = auth()->id();

            $ses = ServiceEntrySheet::create($data);

            foreach ($lines as $lineData) {
                $poLine = ServicePoLine::where('service_purchase_order_id', $po->id)
                    ->findOrFail($lineData['service_po_line_id']);

                $remaining = $poLine->getRemainingQuantity();
                if (bccomp((string) $lineData['actual_quantity'], $remaining, 4) > 0) {
                    throw new \InvalidArgumentException(
                        "Quantity for line {$poLine->line_number} exceeds remaining quantity ({$remaining})."
                    );
                }

                $lineData['total_amount'] = bcmul(
                    (string) $lineData['actual_quantity'],
                    (string) $lineData['actual_price'],
                    4
                );

                $ses->lines()->create($lineData);
            }

            return $ses->load(['servicePurchaseOrder', 'vendor', 'lines']);
        });
    }

    /**
     * Approve a submitted service entry sheet, checked on the locked row.
     *
     * Approving a stale copy of a sheet rejected meanwhile would otherwise
     * move it back to approved.
     */
    public function approveSES(ServiceEntrySheet $ses): ServiceEntrySheet
    {
        return $ses->lockForTransition(function (ServiceEntrySheet $ses): ServiceEntrySheet {
            if (! $ses->canBeApproved()) {
                throw new \InvalidArgumentException('Only submitted service entry sheets can be reviewed.');
            }

            $ses->update([
                'status' => ServiceEntrySheet::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $ses->fresh();
        });
    }

    /**
     * Accept a Service Entry Sheet (formal service acceptance).
     */
    public function acceptService(ServiceEntrySheet $ses, int $userId): ServiceAcceptance
    {
        return DB::transaction(function () use ($ses, $userId): ServiceAcceptance {
            if ($ses->status !== ServiceEntrySheet::STATUS_APPROVED) {
                throw new \InvalidArgumentException(
                    "SES #{$ses->ses_number} must be approved before acceptance."
                );
            }

            if ($ses->acceptance()->exists()) {
                throw new \InvalidArgumentException(
                    "SES #{$ses->ses_number} has already been accepted."
                );
            }

            $acceptance = ServiceAcceptance::create([
                'organization_id' => $ses->organization_id,
                'service_entry_sheet_id' => $ses->id,
                'accepted_by' => $userId,
                'accepted_at' => now(),
                'status' => ServiceAcceptance::STATUS_ACCEPTED,
            ]);

            // Update SES to posted
            $ses->update([
                'status' => ServiceEntrySheet::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            // Update accepted_quantity on each PO line
            foreach ($ses->lines as $sesLine) {
                $poLine = $sesLine->poLine;
                $newAccepted = bcadd(
                    (string) $poLine->accepted_quantity,
                    (string) $sesLine->actual_quantity,
                    4
                );
                $poLine->update(['accepted_quantity' => $newAccepted]);
            }

            // Update PO status
            $po = $ses->servicePurchaseOrder;
            $allLinesFullyAccepted = $po->lines->every(
                fn(ServicePoLine $line) => bccomp(
                    (string) $line->fresh()->accepted_quantity,
                    (string) $line->quantity,
                    4
                ) >= 0
            );

            $po->update([
                'status' => $allLinesFullyAccepted
                    ? ServicePurchaseOrder::STATUS_ACCEPTED
                    : ServicePurchaseOrder::STATUS_PARTIALLY_ACCEPTED,
            ]);

            return $acceptance->load('entrySheet', 'acceptedBy');
        });
    }

    /**
     * Send a Service PO to the vendor.
     */
    public function sendServicePO(ServicePurchaseOrder $po): ServicePurchaseOrder
    {
        if ($po->status !== ServicePurchaseOrder::STATUS_DRAFT) {
            throw new \InvalidArgumentException(
                "Service PO #{$po->po_number} must be in draft status to send."
            );
        }

        $po->update(['status' => ServicePurchaseOrder::STATUS_SENT]);

        return $po->fresh();
    }

    /**
     * Reject a submitted service entry sheet, checked on the locked row.
     *
     * Entry sheets have no column for a rejection reason, so none is kept.
     */
    public function rejectSES(ServiceEntrySheet $ses): ServiceEntrySheet
    {
        return $ses->lockForTransition(function (ServiceEntrySheet $ses): ServiceEntrySheet {
            if (! $ses->canBeApproved()) {
                throw new \InvalidArgumentException('Only submitted service entry sheets can be reviewed.');
            }

            $ses->update(['status' => ServiceEntrySheet::STATUS_REJECTED]);

            return $ses->fresh();
        });
    }
}
