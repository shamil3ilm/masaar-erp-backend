<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorCreditNote;
use App\Models\Purchase\VendorCreditNoteLine;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Support\TaxMath;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VendorCreditNoteService
{
    public function __construct(
        private JournalService $journalService,
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * List vendor credit notes with optional filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = VendorCreditNote::with(['vendor', 'bill'])
            ->orderByDesc('credit_date');

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('credit_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('credit_date', '<=', $filters['end_date']);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage);
    }

    /**
     * Create a vendor credit note with lines.
     */
    public function create(array $data, array $lines): VendorCreditNote
    {
        return DB::transaction(function () use ($data, $lines): VendorCreditNote {
            if (empty($data['credit_note_number'])) {
                $data['credit_note_number'] = $this->numberGenerator->generate('VCN');
            }

            $data['status'] = VendorCreditNote::STATUS_DRAFT;

            $creditNote = VendorCreditNote::create($data);

            $this->storeLines($creditNote, $lines);

            return $creditNote->load('lines');
        });
    }

    /**
     * Update a draft credit note.
     *
     * The status is checked on the locked note, so a note posted by another
     * request meanwhile keeps the lines and totals it was posted with.
     */
    public function update(VendorCreditNote $creditNote, array $data, ?array $lines = null): VendorCreditNote
    {
        return $creditNote->lockForTransition(function (VendorCreditNote $creditNote) use ($data, $lines): VendorCreditNote {
            if ($creditNote->status !== VendorCreditNote::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft credit notes can be updated.');
            }

            $creditNote->update(collect($data)->except(['lines'])->toArray());

            if ($lines !== null) {
                $creditNote->lines()->delete();
                $this->storeLines($creditNote, $lines);
            }

            return $creditNote->fresh('lines');
        });
    }

    /**
     * Delete (soft-delete) a draft credit note with its lines.
     *
     * The status is checked on the locked note, so a note posted meanwhile is
     * not deleted, and the lines and the note go together.
     */
    public function delete(VendorCreditNote $creditNote): void
    {
        $creditNote->lockForTransition(function (VendorCreditNote $creditNote): void {
            if ($creditNote->status !== VendorCreditNote::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft credit notes can be deleted.');
            }

            $creditNote->lines()->delete();
            $creditNote->delete();
        });
    }

    /**
     * Post a vendor credit note and create the journal entry.
     * DR Accounts Payable / CR Purchase Returns.
     *
     * The entry is part of posting: when it cannot be created the note stays a draft.
     */
    public function post(VendorCreditNote $creditNote): VendorCreditNote
    {
        return $creditNote->lockForTransition(function (VendorCreditNote $creditNote): VendorCreditNote {
            if ($creditNote->status !== VendorCreditNote::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft credit notes can be posted.');
            }

            $payableAccountId = config('erp.default_accounts.payable');
            $purchaseReturnsAccountId = config('erp.default_accounts.purchase_returns', config('erp.default_accounts.payable'));

            if ($payableAccountId && $purchaseReturnsAccountId) {
                $this->journalService->createEntry([
                    'organization_id' => $creditNote->organization_id,
                    'entry_date' => $creditNote->credit_date->toDateString(),
                    'reference' => $creditNote->credit_note_number,
                    'description' => "Vendor Credit Note {$creditNote->credit_note_number}",
                    'source_type' => VendorCreditNote::class,
                    'source_id' => $creditNote->id,
                    'currency_code' => 'SAR',
                ], [
                    [
                        'account_id' => $payableAccountId,
                        'debit' => (float) $creditNote->total_amount,
                        'credit' => 0,
                        'description' => "AP Credit - {$creditNote->credit_note_number}",
                    ],
                    [
                        'account_id' => $purchaseReturnsAccountId,
                        'debit' => 0,
                        'credit' => (float) $creditNote->total_amount,
                        'description' => "Purchase Return - {$creditNote->credit_note_number}",
                    ],
                ]);
            }

            $creditNote->update([
                'status' => VendorCreditNote::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * Apply (part of) a credit note to a bill.
     *
     * The remaining credit and the bill's amount due are read from the locked
     * rows, so two applications cannot both spend the same credit.
     */
    public function apply(VendorCreditNote $creditNote, Bill $bill, float $amount): void
    {
        $creditNote->lockForTransition(function (VendorCreditNote $creditNote) use ($bill, $amount): void {
            if ($creditNote->status !== VendorCreditNote::STATUS_POSTED) {
                throw new InvalidArgumentException('Only posted credit notes can be applied.');
            }

            $remaining = $creditNote->getRemainingAmount();
            if ($amount > $remaining) {
                throw new InvalidArgumentException(
                    "Cannot apply {$amount}. Only {$remaining} remaining on this credit note."
                );
            }

            $bill = $bill->lockedCopy();

            if ((float) $bill->amount_due <= 0) {
                throw new InvalidArgumentException('Bill has no outstanding balance.');
            }

            $applyAmount = min($amount, (float) $bill->amount_due);
            $newApplied = bcadd((string) $creditNote->applied_amount, (string) $applyAmount, 4);
            $isFullyApplied = bccomp($newApplied, (string) $creditNote->total_amount, 4) >= 0;

            $creditNote->update([
                'applied_amount' => $newApplied,
                'status' => $isFullyApplied ? VendorCreditNote::STATUS_APPLIED : VendorCreditNote::STATUS_POSTED,
            ]);

            $bill->recordPayment($applyAmount);
        });
    }

    /**
     * Void a vendor credit note.
     *
     * The status is checked on the locked note, so a note fully applied by
     * another request meanwhile is not voided.
     */
    public function void(VendorCreditNote $creditNote): VendorCreditNote
    {
        return $creditNote->lockForTransition(function (VendorCreditNote $creditNote): VendorCreditNote {
            if ($creditNote->status === VendorCreditNote::STATUS_VOID) {
                throw new InvalidArgumentException('Credit note is already voided.');
            }

            if ($creditNote->status === VendorCreditNote::STATUS_APPLIED) {
                throw new InvalidArgumentException('Fully applied credit notes cannot be voided.');
            }

            $creditNote->update([
                'status' => VendorCreditNote::STATUS_VOID,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * Create the credit note's lines and store its totals from them.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function storeLines(VendorCreditNote $creditNote, array $lines): void
    {
        $subtotal = '0';
        $taxTotal = '0';

        foreach ($lines as $lineData) {
            $qty = (string) ($lineData['quantity'] ?? 1);
            $price = (string) ($lineData['unit_price'] ?? 0);
            $taxRate = (string) ($lineData['tax_rate'] ?? 0);
            $amounts = TaxMath::line($qty, $price, $taxRate);

            $creditNote->lines()->create([
                'organization_id' => $creditNote->organization_id,
                'product_id' => $lineData['product_id'] ?? null,
                'description' => $lineData['description'] ?? '',
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
                'tax_amount' => $amounts['tax'],
                'line_total' => $amounts['total'],
            ]);

            $subtotal = bcadd($subtotal, $amounts['subtotal'], TaxMath::SCALE);
            $taxTotal = bcadd($taxTotal, $amounts['tax'], TaxMath::SCALE);
        }

        $creditNote->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxTotal,
            'total_amount' => bcadd($subtotal, $taxTotal, TaxMath::SCALE),
        ]);
    }
}
