<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\RfqHeader;
use App\Models\Purchase\RfqQuote;
use App\Models\Purchase\RfqVendor;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RfqService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * A page of RFQs matching the filters, with creator, vendors and items loaded.
     *
     * The sort column and direction are expected already checked against an
     * allowlist by the caller.
     *
     * @param  array<string, mixed>  $filters  status, search, start_date, end_date
     */
    public function list(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return RfqHeader::with(['creator', 'vendors', 'items'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('rfq_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->where('submission_deadline', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->where('submission_deadline', '<=', $date))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a new RFQ with line items.
     */
    public function createRfq(array $data): RfqHeader
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['rfq_number'])) {
                $data['rfq_number'] = $this->numberGenerator->generate('RFQ');
            }

            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['status'] = RfqHeader::STATUS_DRAFT;

            $rfq = RfqHeader::create($data);

            foreach ($items as $index => $itemData) {
                $itemData['sort_order'] = $itemData['sort_order'] ?? $index;
                $rfq->items()->create($itemData);
            }

            return $rfq->load(['items']);
        });
    }

    /**
     * Update a draft RFQ, checked on the locked row so an RFQ sent meanwhile is not changed.
     */
    public function update(RfqHeader $rfq, array $data): RfqHeader
    {
        return $rfq->lockForTransition(function (RfqHeader $rfq) use ($data): RfqHeader {
            if (! $rfq->isEditable()) {
                throw new \InvalidArgumentException('Only draft RFQs can be updated.');
            }

            $rfq->update($data);

            return $rfq->fresh(['items', 'vendors']);
        });
    }

    /**
     * Send RFQ to a list of vendor contact IDs.
     *
     * @param  int[]  $vendorContactIds
     */
    public function sendToVendors(RfqHeader $rfq, array $vendorContactIds): RfqHeader
    {
        return $rfq->lockForTransition(function (RfqHeader $rfq) use ($vendorContactIds): RfqHeader {
            if (! $rfq->canBeSent() && $rfq->status !== RfqHeader::STATUS_SENT) {
                throw new \InvalidArgumentException('RFQ cannot be sent in its current status.');
            }

            foreach ($vendorContactIds as $contactId) {
                $rfq->vendors()->firstOrCreate(
                    ['contact_id' => $contactId],
                    [
                        'status' => 'invited',
                        'sent_at' => now(),
                        'response_deadline' => $rfq->submission_deadline,
                    ]
                );
            }

            // Mark already-existing unsent vendors as sent
            $rfq->vendors()
                ->whereIn('contact_id', $vendorContactIds)
                ->whereNull('sent_at')
                ->update(['sent_at' => now(), 'status' => 'invited']);

            $rfq->update(['status' => RfqHeader::STATUS_SENT]);

            return $rfq->fresh(['items', 'vendors.contact']);
        });
    }

    /**
     * Record a vendor's quote against one of the RFQ's vendor invitations.
     *
     * The invitation is looked up through the RFQ: invitations carry no
     * organization column, so an id found only elsewhere is refused.
     */
    public function recordQuote(RfqHeader $rfq, int $rfqVendorId, array $quoteData): RfqQuote
    {
        $rfqVendor = $rfq->vendors()->find($rfqVendorId);

        if ($rfqVendor === null) {
            throw new \InvalidArgumentException('Vendor invitation does not belong to this RFQ.');
        }

        if ($rfqVendor->status === 'declined') {
            throw new \InvalidArgumentException('Cannot record a quote for a vendor who declined.');
        }

        return DB::transaction(function () use ($rfqVendor, $quoteData) {
            $lines = $quoteData['lines'] ?? [];
            unset($quoteData['lines'], $quoteData['rfq_vendor_id']);

            $quoteData['rfq_id'] = $rfqVendor->rfq_id;
            $quoteData['rfq_vendor_id'] = $rfqVendor->id;
            $quoteData['contact_id'] = $rfqVendor->contact_id;
            $quoteData['status'] = 'received';

            // Calculate total from lines if not provided
            if (empty($quoteData['total_amount']) && !empty($lines)) {
                $total = '0';
                foreach ($lines as $line) {
                    $total = bcadd($total, (string) ($line['line_total'] ?? 0), 4);
                }
                $quoteData['total_amount'] = $total;
            }

            $quote = RfqQuote::create($quoteData);

            foreach ($lines as $lineData) {
                $quote->lines()->create($lineData);
            }

            $rfqVendor->update(['status' => 'responded']);

            return $quote->load(['lines']);
        });
    }

    /**
     * Award the RFQ to one of its quotes and reject the others.
     *
     * The RFQ is locked and the quote re-read under that lock: two awards of
     * different quotes at once would otherwise both pass the status check and
     * leave the RFQ with two winners.
     */
    public function awardQuote(RfqHeader $rfq, int $quoteId): RfqQuote
    {
        return $rfq->lockForTransition(function (RfqHeader $rfq) use ($quoteId): RfqQuote {
            $quote = $this->quoteOf($rfq, $quoteId);

            if (! $quote->canBeAwarded()) {
                throw new \InvalidArgumentException('Quote cannot be awarded in its current status.');
            }

            RfqQuote::where('rfq_id', $rfq->id)
                ->where('id', '!=', $quote->id)
                ->whereIn('status', ['received', 'evaluated'])
                ->update(['status' => 'rejected']);

            RfqVendor::where('rfq_id', $rfq->id)
                ->where('id', '!=', $quote->rfq_vendor_id)
                ->whereIn('status', ['invited', 'responded'])
                ->update(['status' => 'rejected']);

            $quote->update(['status' => 'awarded']);
            $quote->rfqVendor()->update(['status' => 'awarded']);
            $rfq->update(['status' => RfqHeader::STATUS_AWARDED]);

            return $quote->fresh(['rfqVendor', 'rfq']);
        });
    }

    /**
     * Convert the RFQ's awarded quote to a purchase order and close the RFQ.
     *
     * The quote stays awarded after conversion, so the RFQ status is what
     * stops a second order: it is checked on the locked RFQ, which conversion
     * moves to closed.
     */
    public function convertToPurchaseOrder(RfqHeader $rfq, int $quoteId): PurchaseOrder
    {
        return $rfq->lockForTransition(function (RfqHeader $rfq) use ($quoteId): PurchaseOrder {
            $quote = $this->quoteOf($rfq, $quoteId);

            if (! $quote->isAwarded()) {
                throw new \InvalidArgumentException('Only awarded quotes can be converted to a purchase order.');
            }

            if ($rfq->status !== RfqHeader::STATUS_AWARDED) {
                throw new \InvalidArgumentException('This RFQ has already been converted to a purchase order.');
            }

            $quote->load(['lines.rfqItem']);

            $lines = $quote->lines->map(function ($quoteLine) {
                $rfqItem = $quoteLine->rfqItem;

                return [
                    'product_id'     => $rfqItem?->product_id,
                    'variant_id'     => $rfqItem?->variant_id ?? null,
                    'description'    => $rfqItem?->description ?? '',
                    'quantity'       => $quoteLine->quantity,
                    'unit_id'        => $rfqItem?->unit_id,
                    'unit_price'     => $quoteLine->unit_price,
                    'discount_type'  => 'percentage',
                    'discount_value' => $quoteLine->discount_pct,
                    'tax_rate'       => $quoteLine->tax_rate,
                ];
            })->toArray();

            $poData = [
                'supplier_id'            => $quote->contact_id,
                'order_date'             => now()->toDateString(),
                'expected_delivery_date' => $rfq->delivery_date?->toDateString(),
                'delivery_address'       => $rfq->delivery_address,
                'currency_code'          => $quote->currency_code,
                'notes'                  => "Created from RFQ {$rfq->rfq_number}. Vendor quote: {$quote->quote_number}",
                'reference'              => $rfq->rfq_number,
            ];

            $purchaseOrder = $this->purchaseOrderService->create($poData, $lines);

            $rfq->update(['status' => RfqHeader::STATUS_CLOSED]);

            return $purchaseOrder;
        });
    }

    /**
     * Build a comparison matrix of all quotes for an RFQ.
     */
    public function compareQuotes(RfqHeader $rfq): array
    {
        $rfq->load(['items', 'quotes.lines.rfqItem', 'quotes.contact']);

        $quotes = $rfq->quotes->whereIn('status', ['received', 'evaluated', 'awarded']);

        $matrix = [
            'rfq_id' => $rfq->id,
            'rfq_number' => $rfq->rfq_number,
            'items' => $rfq->items->map(fn($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
            ])->values()->toArray(),
            'quotes' => $quotes->map(function ($quote) use ($rfq) {
                $linesByItem = $quote->lines->keyBy('rfq_item_id');

                return [
                    'quote_id' => $quote->id,
                    'vendor_name' => $quote->contact?->getDisplayName(),
                    'quote_number' => $quote->quote_number,
                    'currency_code' => $quote->currency_code,
                    'total_amount' => (float) $quote->total_amount,
                    'delivery_days' => $quote->delivery_days,
                    'payment_terms' => $quote->payment_terms,
                    'valid_until' => $quote->valid_until?->toDateString(),
                    'status' => $quote->status,
                    'line_prices' => $rfq->items->map(function ($item) use ($linesByItem) {
                        $line = $linesByItem->get($item->id);

                        return [
                            'rfq_item_id' => $item->id,
                            'unit_price' => $line ? (float) $line->unit_price : null,
                            'line_total' => $line ? (float) $line->line_total : null,
                            'delivery_days' => $line?->delivery_days,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray(),
        ];

        // Identify lowest price per item
        foreach ($matrix['items'] as $itemIndex => $item) {
            $lowestPrice = null;
            $lowestQuoteId = null;

            foreach ($matrix['quotes'] as $quoteData) {
                $linePrice = $quoteData['line_prices'][$itemIndex]['unit_price'] ?? null;

                if ($linePrice !== null && ($lowestPrice === null || $linePrice < $lowestPrice)) {
                    $lowestPrice = $linePrice;
                    $lowestQuoteId = $quoteData['quote_id'];
                }
            }

            $matrix['items'][$itemIndex]['lowest_price'] = $lowestPrice;
            $matrix['items'][$itemIndex]['lowest_quote_id'] = $lowestQuoteId;
        }

        return $matrix;
    }

    /**
     * One of the RFQ's quotes; quotes carry no organization column, so an id
     * found only on another RFQ is refused.
     */
    private function quoteOf(RfqHeader $rfq, int $quoteId): RfqQuote
    {
        $quote = $rfq->quotes()->find($quoteId);

        if ($quote === null) {
            throw new \InvalidArgumentException('Quote does not belong to this RFQ.');
        }

        return $quote;
    }
}
