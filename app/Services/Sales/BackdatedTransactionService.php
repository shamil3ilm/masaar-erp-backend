<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Sales\BackdatedTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BackdatedTransactionService
{
    public function __construct() {}

    /**
     * Create a backdated transaction log entry.
     */
    public function create(array $data, int $userId): BackdatedTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['entry_date'] = $data['entry_date'] ?? now()->toDateString();

            $this->validateDate($data['transaction_date'], $data['organization_id']);

            return BackdatedTransaction::create($data);
        });
    }

    /**
     * Approve a backdated transaction.
     */
    public function approve(BackdatedTransaction $transaction, int $userId): BackdatedTransaction
    {
        if ($transaction->isApproved()) {
            throw new \InvalidArgumentException('Transaction is already approved.');
        }

        return DB::transaction(function () use ($transaction, $userId) {
            $transaction->update([
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $transaction->fresh();
        });
    }

    /**
     * Reject a backdated transaction.
     */
    public function reject(BackdatedTransaction $transaction, ?string $reason = null): BackdatedTransaction
    {
        if ($transaction->isApproved()) {
            throw new \InvalidArgumentException('Cannot reject an already approved transaction.');
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $transaction->update([
                'reason' => $transaction->reason
                    ? $transaction->reason."\n\nRejected: ".($reason ?? 'No reason provided')
                    : 'Rejected: '.($reason ?? 'No reason provided'),
            ]);

            // Optionally, delete or mark as rejected
            $transaction->delete();

            return $transaction;
        });
    }

    /**
     * Validate that a backdated date is acceptable.
     */
    public function validateDate(string $transactionDate, ?int $organizationId = null): bool
    {
        $date = Carbon::parse($transactionDate);
        $today = now();

        // Cannot be in the future
        if ($date->isAfter($today)) {
            throw new \InvalidArgumentException('Transaction date cannot be in the future.');
        }

        // Cannot be more than 1 year in the past (configurable)
        $maxBackdateDays = config('erp.max_backdate_days', 365);
        if ($date->diffInDays($today) > $maxBackdateDays) {
            throw new \InvalidArgumentException("Transaction date cannot be more than {$maxBackdateDays} days in the past.");
        }

        // A period belongs to a fiscal year, and the fiscal year to the
        // organisation; and a closed period is flagged is_closed, not status.
        $orgId = $organizationId ?? auth()->user()->organization_id;

        $closedPeriod = AccountingPeriod::withoutGlobalScopes()
            ->whereHas('fiscalYear', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $orgId))
            ->where('start_date', '<=', $transactionDate)
            ->where('end_date', '>=', $transactionDate)
            ->where('is_closed', true)
            ->exists();

        if ($closedPeriod) {
            throw new \InvalidArgumentException('Transaction date falls within a closed accounting period.');
        }

        return true;
    }
}
