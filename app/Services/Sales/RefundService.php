<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Refund;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Wallet;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Columns only this service sets. A refund is approved, processed and
     * posted through approve() and process(), never by the data it is created from.
     */
    private const SERVER_OWNED = [
        'id', 'uuid', 'status', 'created_by', 'journal_entry_id',
        'approved_by', 'approved_at', 'processed_by', 'processed_at',
        'transaction_reference', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function __construct(
        private WalletService $walletService,
        private CreditNoteService $creditNoteService,
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * A refund of the given organization; 404 when there is none.
     */
    public function find(int $organizationId, int $id): Refund
    {
        return Refund::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * A refund of the given organization with the reference columns of its
     * contact, its sales return and what it refunds; 404 when there is none.
     */
    public function findWithDetails(int $organizationId, int $id): Refund
    {
        return Refund::where('organization_id', $organizationId)
            ->with([
                'contact:'.implode(',', Contact::REFERENCE_COLUMNS),
                'salesReturn',
                'refundable' => fn ($morphTo) => $morphTo->constrain([
                    Invoice::class => fn ($query) => $query->select(Invoice::REFERENCE_COLUMNS),
                ]),
            ])
            ->findOrFail($id);
    }

    public function create(array $data, int $userId): Refund
    {
        return DB::transaction(function () use ($data, $userId) {
            return Refund::create(array_merge(Arr::except($data, self::SERVER_OWNED), [
                'status' => Refund::STATUS_PENDING,
                'created_by' => $userId,
            ]));
        });
    }

    public function createFromSalesReturn(SalesReturn $salesReturn, string $refundMethod, int $userId): Refund
    {
        $refundAmount = $salesReturn->refund_amount ?: $salesReturn->total;

        if (bccomp((string) $refundAmount, '0', 4) <= 0) {
            throw new \InvalidArgumentException('Refund amount must be positive.');
        }

        $maxRefundable = $salesReturn->invoice
            ? (string) $salesReturn->invoice->amount_paid
            : (string) $salesReturn->total;

        if (bccomp((string) $refundAmount, $maxRefundable, 4) > 0) {
            throw new \InvalidArgumentException(
                "Refund amount ({$refundAmount}) exceeds the original payment amount ({$maxRefundable})."
            );
        }

        return DB::transaction(function () use ($salesReturn, $refundMethod, $userId, $refundAmount) {
            $refund = Refund::create([
                'organization_id' => $salesReturn->organization_id,
                'branch_id' => $salesReturn->branch_id,
                'refund_number' => $this->numberGenerator->generate('refund'),
                'refund_type' => Refund::TYPE_CUSTOMER,
                'refundable_type' => SalesReturn::class,
                'refundable_id' => $salesReturn->id,
                'contact_id' => $salesReturn->customer_id,
                'sales_return_id' => $salesReturn->id,
                'amount' => $refundAmount,
                'currency_code' => $salesReturn->currency_code,
                'refund_method' => $refundMethod,
                'refund_date' => now()->toDateString(),
                'reason' => $salesReturn->reason_notes ?? 'Sales return refund',
                'status' => Refund::STATUS_PENDING,
                'created_by' => $userId,
            ]);

            $salesReturn->update(['refund_id' => $refund->id]);

            return $refund;
        });
    }

    /**
     * Approve a pending refund.
     *
     * The status is checked on the locked refund, so a refund cancelled by a
     * concurrent request is not approved.
     */
    public function approve(Refund $refund, int $userId): Refund
    {
        return $refund->lockForTransition(function (Refund $refund) use ($userId): Refund {
            if ($refund->status !== Refund::STATUS_PENDING) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
            }

            $refund->approve($userId);

            return $refund->fresh();
        });
    }

    /**
     * Pay out an approved refund.
     *
     * The status is checked on the locked refund, so a double submit credits
     * the wallet or issues the credit note once.
     */
    public function process(Refund $refund, int $userId, ?string $transactionReference = null): Refund
    {
        return $refund->lockForTransition(function (Refund $refund) use ($userId, $transactionReference): Refund {
            if ($refund->status !== Refund::STATUS_APPROVED) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
            }

            switch ($refund->refund_method) {
                case Refund::METHOD_WALLET:
                    $this->processWalletRefund($refund);
                    break;

                case Refund::METHOD_CREDIT_NOTE:
                    $this->processCreditNoteRefund($refund, $userId);
                    break;

                case Refund::METHOD_BANK_TRANSFER:
                case Refund::METHOD_CASH:
                case Refund::METHOD_ORIGINAL:
                    // These require manual processing confirmation
                    break;
            }

            $refund->markProcessed($userId, $transactionReference);

            return $refund->fresh();
        });
    }

    /**
     * Cancel a refund that has not been processed.
     *
     * The status is checked on the locked refund, so a refund paid out by a
     * concurrent request is not marked cancelled afterwards.
     */
    public function cancel(Refund $refund): Refund
    {
        return $refund->lockForTransition(function (Refund $refund): Refund {
            if ($refund->status === Refund::STATUS_PROCESSED) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => 'Cannot cancel a processed refund.',
                ]);
            }

            $refund->update(['status' => Refund::STATUS_CANCELLED]);

            return $refund->fresh();
        });
    }

    public function list(int $organizationId, array $filters = [], int $perPage = 20)
    {
        $query = Refund::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['refund_type'])) {
            $query->where('refund_type', $filters['refund_type']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('refund_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('refund_date', '<=', $filters['to_date']);
        }

        return $query->with(['contact:'.implode(',', Contact::REFERENCE_COLUMNS), 'salesReturn'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    private function processWalletRefund(Refund $refund): void
    {
        $wallet = $this->walletService->getOrCreateWallet(
            $refund->organization_id,
            $refund->contact_id,
            Wallet::TYPE_CUSTOMER,
            $refund->currency_code
        );

        $this->walletService->credit(
            $wallet,
            (float) $refund->amount,
            "Refund #{$refund->refund_number}",
            Refund::class,
            $refund->id
        );
    }

    private function processCreditNoteRefund(Refund $refund, int $userId): void
    {
        $creditNote = $this->creditNoteService->create([
            'organization_id' => $refund->organization_id,
            'credit_note_type' => CreditNote::TYPE_SALES,
            'contact_id' => $refund->contact_id,
            'credit_note_date' => now()->toDateString(),
            'currency_code' => $refund->currency_code,
            'total' => $refund->amount,
            'reason' => "Refund #{$refund->refund_number}: {$refund->reason}",
        ], $userId);

        $this->creditNoteService->approve($creditNote, $userId);
    }

}
