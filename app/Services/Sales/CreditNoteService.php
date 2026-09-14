<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ApiException;
use App\Exceptions\ERP\ValidationException;
use App\Exceptions\ErrorCodes;
use App\Models\Accounting\Account;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\CreditNoteApplication;
use App\Models\Sales\Invoice;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Support\TaxMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditNoteService
{
    public function __construct(
        private readonly AccountResolver $accountResolver,
    ) {}

    public function create(array $data, int $userId): CreditNote
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['lines']);

            $this->assertWithinInvoiceTotal($data, $items);

            // Generate credit note number if not provided
            if (empty($data['credit_note_number'])) {
                $data['credit_note_number'] = app(NumberGeneratorService::class)->generate('credit_note');
            }

            $creditNote = CreditNote::create(array_merge($data, [
                'status' => CreditNote::STATUS_DRAFT,
                'applied_amount' => 0,
                'available_amount' => $data['total'] ?? 0,
                'created_by' => $userId,
            ]));

            if (! empty($items)) {
                foreach ($items as $item) {
                    $amounts = $this->itemAmounts($item);

                    $creditNote->items()->create(array_merge($item, [
                        'subtotal' => $amounts['subtotal'],
                        'total' => $amounts['total'],
                        'tax_amount' => $amounts['tax'],
                    ]));
                }

                $this->recalculateTotals($creditNote);
            }

            // Create GL journal entry: debit AR, credit Sales Returns/Revenue.
            try {
                $orgId = $creditNote->organization_id ?? $data['organization_id'] ?? null;

                // An account is classified by account_type and narrowed by
                // sub_type. There is no type column, and no revenue value:
                // income is the type, receivable is a sub_type.
                $receivableAccount = $this->accountResolver->bySubType((int) $orgId, Account::SUBTYPE_RECEIVABLE)
                    ?? Account::where('organization_id', $orgId)
                        ->where('code', '1200')
                        ->first();

                $revenueAccount = Account::where('organization_id', $orgId)
                    ->where('account_type', 'income')
                    ->where(function ($q) {
                        $q->where('name', 'like', '%return%')
                            ->orWhere('name', 'like', '%credit%');
                    })
                    ->first()
                    ?? Account::where('organization_id', $orgId)
                        ->where('account_type', 'income')
                        ->first();

                if ($receivableAccount && $revenueAccount) {
                    $amount = (float) ($creditNote->total ?? 0);

                    app(JournalService::class)->create([
                        'entry_date' => $creditNote->credit_note_date ?? now(),
                        'reference' => $creditNote->credit_note_number,
                        'description' => "Credit Note - {$creditNote->credit_note_number}",
                        'source_type' => CreditNote::class,
                        'source_id' => $creditNote->id,
                    ], [
                        [
                            'account_id' => $receivableAccount->id,
                            'description' => "Credit note {$creditNote->credit_note_number} - AR reduction",
                            'debit' => 0,
                            'credit' => $amount,
                        ],
                        [
                            'account_id' => $revenueAccount->id,
                            'description' => "Credit note {$creditNote->credit_note_number} - Sales return",
                            'debit' => $amount,
                            'credit' => 0,
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('CreditNoteService: GL journal entry failed — rolling back credit note creation', [
                    'credit_note_id' => $creditNote->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Let the DB::transaction roll back
            }

            return $creditNote->fresh(['items']);
        });
    }

    /**
     * A credit note with its items, the reference columns of its contact and
     * invoice, and its applications with the reference columns of their invoices.
     */
    public function loadDetails(CreditNote $creditNote): CreditNote
    {
        return $creditNote->load([
            'items.product',
            'contact:'.implode(',', Contact::REFERENCE_COLUMNS),
            'invoice:'.implode(',', Invoice::REFERENCE_COLUMNS),
            'applications.invoice:'.implode(',', Invoice::REFERENCE_COLUMNS),
        ]);
    }

    /**
     * Approve a draft credit note.
     *
     * The status is checked on the locked row, so a note voided by a concurrent
     * request is not approved through a copy loaded while it was a draft.
     */
    public function approve(CreditNote $creditNote, int $userId): CreditNote
    {
        return $creditNote->lockForTransition(function (CreditNote $creditNote) use ($userId): CreditNote {
            if ($creditNote->status !== CreditNote::STATUS_DRAFT) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'current_status' => $creditNote->status,
                    'action' => 'approve',
                ]);
            }

            $creditNote->update([
                'status' => CreditNote::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * Apply a credit note to an invoice of the current organization; 404 when
     * the invoice is not visible.
     */
    public function applyToInvoiceId(CreditNote $creditNote, int $invoiceId, float $amount): CreditNoteApplication
    {
        return $this->applyToInvoice($creditNote, Invoice::findOrFail($invoiceId), $amount);
    }

    public function applyToInvoice(CreditNote $creditNote, Invoice $invoice, float $amount): CreditNoteApplication
    {
        if (! in_array($creditNote->status, [CreditNote::STATUS_APPROVED, CreditNote::STATUS_APPLIED])) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                'message' => 'Credit note must be approved before applying.',
            ]);
        }

        if ($creditNote->contact_id !== $invoice->customer_id) {
            throw ApiException::fromError(ErrorCodes::VALIDATION_FAILED, [
                'message' => 'Credit note and invoice must belong to the same contact.',
            ]);
        }

        return DB::transaction(function () use ($creditNote, $invoice, $amount) {
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if (! $creditNote->hasAvailableBalance()) {
                throw ApiException::fromError(ErrorCodes::BIZ_INSUFFICIENT_BALANCE);
            }

            // Guard: cannot apply credit to a voided or draft invoice
            if (in_array($invoice->status, [Invoice::STATUS_VOIDED, Invoice::STATUS_DRAFT], true)) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => "Cannot apply credit note to an invoice with status '{$invoice->status}'.",
                ]);
            }

            if ((float) $invoice->amount_due <= 0) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => 'Invoice has no outstanding balance.',
                ]);
            }

            if ($amount > (float) $creditNote->available_amount) {
                throw new ValidationException('Amount exceeds available credit.');
            }

            $application = $creditNote->applyToInvoice($invoice, $amount);
            $invoice->recordPayment((float) $application->amount);

            return $application;
        });
    }

    /**
     * Void a credit note that has not been applied.
     *
     * The applied amount is read from the locked note: credit applied to an
     * invoice by a concurrent request has paid part of that invoice, and
     * voiding the note would leave the payment without its credit.
     */
    public function void(CreditNote $creditNote): CreditNote
    {
        return $creditNote->lockForTransition(function (CreditNote $creditNote): CreditNote {
            if ($creditNote->status === CreditNote::STATUS_VOIDED) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => 'Credit note is already voided.',
                ]);
            }

            if (bccomp((string) $creditNote->applied_amount, '0', 2) > 0) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => 'Cannot void a credit note that has been partially or fully applied.',
                ]);
            }

            $creditNote->update([
                'status' => CreditNote::STATUS_VOIDED,
                'available_amount' => 0,
            ]);

            return $creditNote->fresh();
        });
    }

    public function list(int $organizationId, array $filters = [], int $perPage = 20)
    {
        $query = CreditNote::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('credit_note_type', $filters['type']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('credit_note_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('credit_note_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['has_balance'])) {
            $query->where('available_amount', '>', 0);
        }

        return $query->with([
            'contact:'.implode(',', Contact::REFERENCE_COLUMNS),
            'invoice:'.implode(',', Invoice::REFERENCE_COLUMNS),
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * A credit note raised against an invoice may not credit more than the
     * invoice's total, tax included. This holds for every caller: the credit
     * note endpoint, a refund and a resolved sales return.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    private function assertWithinInvoiceTotal(array $data, array $items): void
    {
        if (empty($data['invoice_id'])) {
            return;
        }

        $invoice = Invoice::where('organization_id', $data['organization_id'] ?? null)->find($data['invoice_id']);

        if ($invoice === null) {
            return;
        }

        $total = '0';
        foreach ($items as $item) {
            $total = bcadd($total, $this->itemAmounts($item)['total'], 2);
        }

        if (bccomp($total, (string) $invoice->total, 4) > 0) {
            throw new ValidationException('Credit note total exceeds invoice total.');
        }
    }

    /**
     * An item's subtotal, tax and total. Sales credit notes are stored at two
     * decimals.
     *
     * @param  array<string, mixed>  $item
     * @return array{discount: string, subtotal: string, tax: string, total: string}
     */
    private function itemAmounts(array $item): array
    {
        return TaxMath::line(
            (string) $item['quantity'],
            (string) $item['unit_price'],
            (string) ($item['tax_rate'] ?? 0),
            scale: 2,
        );
    }

    private function recalculateTotals(CreditNote $creditNote): void
    {
        $creditNote->load('items');
        $subtotal = $creditNote->items->sum('subtotal');
        $taxAmount = $creditNote->items->sum('tax_amount');
        $total = bcadd((string) $subtotal, (string) $taxAmount, 2);

        $creditNote->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'available_amount' => bcsub($total, (string) $creditNote->applied_amount, 2),
        ]);
    }
}
