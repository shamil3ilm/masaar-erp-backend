<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Finance\PettyCashFund;
use App\Models\Finance\PettyCashReplenishment;
use App\Models\Finance\PettyCashVoucher;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PettyCashService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Create a new petty cash voucher (draft).
     */
    public function createVoucher(PettyCashFund $fund, array $data): PettyCashVoucher
    {
        if (!$fund->is_active) {
            throw new InvalidArgumentException('Cannot create voucher for an inactive fund.');
        }

        $amount = (float) $data['amount'];

        if (bccomp((string)$amount, '0', 4) <= 0) {
            throw new \InvalidArgumentException('Voucher amount must be positive.');
        }

        if ($fund->max_transaction_limit > 0 && $amount > (float) $fund->max_transaction_limit) {
            throw new InvalidArgumentException(
                "Amount {$amount} exceeds the maximum transaction limit of {$fund->max_transaction_limit}."
            );
        }

        $voucherNumber = $this->numberGenerator->generate(
            $fund->organization_id ?? $data['organization_id'],
            'petty_cash_voucher'
        );

        return PettyCashVoucher::create([
            'fund_id'          => $fund->id,
            'voucher_number'   => $voucherNumber,
            'voucher_date'     => $data['voucher_date'] ?? now()->toDateString(),
            'transaction_type' => $data['transaction_type'],
            'amount'           => $amount,
            'description'      => $data['description'],
            'category'         => $data['category'] ?? null,
            'payee_payer'      => $data['payee_payer'] ?? null,
            'receipt_number'   => $data['receipt_number'] ?? null,
            'account_id'       => $data['account_id'] ?? null,
            'status'           => PettyCashVoucher::STATUS_DRAFT,
            'created_by'       => auth()->id(),
        ]);
    }

    /**
     * Approve a draft voucher.
     */
    public function approveVoucher(PettyCashVoucher $voucher): PettyCashVoucher
    {
        if (!$voucher->isDraft()) {
            throw new InvalidArgumentException('Only draft vouchers can be approved.');
        }

        $voucher->transitionTo(PettyCashVoucher::STATUS_APPROVED, [
            'approved_by' => auth()->id(),
        ]);

        return $voucher->fresh();
    }

    /**
     * Post an approved voucher: adjust the fund balance and book the voucher.
     *
     * Runs on the locked voucher and then the locked fund, so a second submit
     * finds the voucher posted, and the balance is changed from the balance as
     * it stands. A journal entry that cannot be posted throws and nothing is.
     */
    public function postVoucher(PettyCashVoucher $voucher): PettyCashVoucher
    {
        return $voucher->lockForTransition(function (PettyCashVoucher $voucher): PettyCashVoucher {
            if (! $voucher->isApproved()) {
                throw new InvalidArgumentException('Only approved vouchers can be posted.');
            }

            $fund      = PettyCashFund::lockForUpdate()->findOrFail($voucher->fund_id);
            $amount    = (string) $voucher->amount;
            $isPayment = $voucher->transaction_type === PettyCashVoucher::TYPE_PAYMENT;

            if ($isPayment && bccomp($amount, (string) $fund->current_balance, 4) > 0) {
                throw new InvalidArgumentException(
                    "Insufficient fund balance. Available: {$fund->current_balance}, Required: {$amount}."
                );
            }

            $fund->update([
                'current_balance' => $isPayment
                    ? bcsub((string) $fund->current_balance, $amount, 4)
                    : bcadd((string) $fund->current_balance, $amount, 4),
            ]);

            $voucher->transitionTo(PettyCashVoucher::STATUS_POSTED);

            $this->bookVoucher($voucher, $fund);

            return $voucher->fresh();
        });
    }

    /**
     * Books a voucher between the account it names and the fund's account: a
     * payment debits the voucher's account and credits the fund, a receipt the
     * reverse. A voucher that names no account is not booked.
     */
    private function bookVoucher(PettyCashVoucher $voucher, PettyCashFund $fund): void
    {
        if ($voucher->account_id === null) {
            Log::warning('Petty cash voucher posted without a journal entry: it names no account.', [
                'voucher_id' => $voucher->id,
            ]);

            return;
        }

        $amount = (float) $voucher->amount;
        $label  = $voucher->description ?: "Petty cash voucher {$voucher->voucher_number}";

        [$debitAccountId, $creditAccountId] = $voucher->transaction_type === PettyCashVoucher::TYPE_PAYMENT
            ? [$voucher->account_id, $fund->account_id]
            : [$fund->account_id, $voucher->account_id];

        $entry = $this->journalService->createEntry([
            'organization_id' => $fund->organization_id,
            'entry_date'      => $voucher->voucher_date->toDateString(),
            'reference'       => $voucher->voucher_number,
            'description'     => $label,
            'source_type'     => PettyCashVoucher::class,
            'source_id'       => $voucher->id,
        ], [
            ['account_id' => $debitAccountId, 'description' => $label, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccountId, 'description' => "Petty cash fund: {$fund->name}", 'debit' => 0, 'credit' => $amount],
        ]);

        $this->journalService->postEntry($entry);
    }

    /**
     * Request fund replenishment.
     */
    public function requestReplenishment(PettyCashFund $fund, float $amount, ?string $notes = null): PettyCashReplenishment
    {
        if (!$fund->is_active) {
            throw new InvalidArgumentException('Cannot replenish an inactive fund.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Replenishment amount must be positive.');
        }

        return PettyCashReplenishment::create([
            'fund_id'             => $fund->id,
            'replenishment_date'  => now()->toDateString(),
            'amount'              => $amount,
            'notes'               => $notes,
            'requested_by'        => auth()->id(),
            'status'              => PettyCashReplenishment::STATUS_REQUESTED,
        ]);
    }

    /**
     * Approve a replenishment request.
     */
    public function approveReplenishment(PettyCashReplenishment $replenishment): PettyCashReplenishment
    {
        if (!$replenishment->isRequested()) {
            throw new InvalidArgumentException('Only requested replenishments can be approved.');
        }

        $replenishment->transitionTo(PettyCashReplenishment::STATUS_APPROVED, [
            'approved_by' => auth()->id(),
        ]);

        return $replenishment->fresh();
    }

    /**
     * Disburse an approved replenishment, adding it to the fund balance.
     *
     * Runs on the locked replenishment and then the locked fund, so a second
     * submit finds it disbursed and the amount is added to the balance as it
     * stands, not to one read before another posting changed it.
     */
    public function disburseReplenishment(PettyCashReplenishment $replenishment): PettyCashReplenishment
    {
        return $replenishment->lockForTransition(function (PettyCashReplenishment $replenishment): PettyCashReplenishment {
            if (! $replenishment->isApproved()) {
                throw new InvalidArgumentException('Only approved replenishments can be disbursed.');
            }

            $fund = PettyCashFund::lockForUpdate()->findOrFail($replenishment->fund_id);

            $fund->update([
                'current_balance' => bcadd((string) $fund->current_balance, (string) $replenishment->amount, 4),
            ]);

            $replenishment->transitionTo(PettyCashReplenishment::STATUS_DISBURSED);

            return $replenishment->fresh();
        });
    }
}
