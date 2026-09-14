<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\RentalContract;
use App\Models\RealEstate\SecurityDeposit;
use App\Services\Core\NumberGeneratorService;
use InvalidArgumentException;

/**
 * Tenant security deposits: collection, interest accrual, and refunds.
 *
 * A contract holds at most one deposit; its status is derived from how much of
 * the required amount has been collected and how much has been refunded.
 */
class SecurityDepositService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    public function createSecurityDeposit(RentalContract $contract, array $data): SecurityDeposit
    {
        if ($contract->securityDeposit()->exists()) {
            throw new InvalidArgumentException('A security deposit already exists for this contract.');
        }

        $depositNumber = $this->numberGenerator->generate('RE-DEP', null, $contract->organization_id);

        return SecurityDeposit::create(array_merge($data, [
            'organization_id' => $contract->organization_id,
            'contract_id'     => $contract->id,
            'deposit_number'  => $depositNumber,
            'status'          => 'pending',
        ]));
    }

    /**
     * Adds a collected amount to the locked deposit, so collections recorded at
     * the same time add up instead of one overwriting the other.
     */
    public function recordDepositCollection(SecurityDeposit $deposit, float $amount, string $date): SecurityDeposit
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('A collected amount must be positive.');
        }

        return $deposit->lockForTransition(function (SecurityDeposit $deposit) use ($amount, $date): SecurityDeposit {
            $newCollected = bcadd((string) $deposit->collected_amount, (string) $amount, 4);

            $status = bccomp($newCollected, (string) $deposit->required_amount, 4) >= 0
                ? 'collected'
                : 'partial';

            $deposit->update([
                'collected_amount' => $newCollected,
                'collected_date'   => $deposit->collected_date ?? $date,
                'status'           => $status,
            ]);

            return $deposit->fresh();
        });
    }

    public function accrueDepositInterest(SecurityDeposit $deposit): SecurityDeposit
    {
        $deposit->update(['accrued_interest' => $deposit->computeCurrentInterest()]);

        return $deposit->fresh();
    }

    /**
     * Refunds part of the deposit from the locked row. A refund takes back only
     * what was collected and has not been refunded yet, counting refunds
     * recorded since the caller loaded the deposit.
     */
    public function refundDeposit(SecurityDeposit $deposit, float $amount, string $reason): SecurityDeposit
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('A refund amount must be positive.');
        }

        return $deposit->lockForTransition(function (SecurityDeposit $deposit) use ($amount, $reason): SecurityDeposit {
            $refundable = bcsub((string) $deposit->collected_amount, (string) $deposit->refunded_amount, 4);

            if (bccomp((string) $amount, $refundable, 4) > 0) {
                throw new InvalidArgumentException("Refund amount exceeds the refundable deposit of {$refundable}.");
            }

            $newRefunded = bcadd((string) $deposit->refunded_amount, (string) $amount, 4);

            $status = bccomp($newRefunded, (string) $deposit->collected_amount, 4) >= 0
                ? 'refunded'
                : 'partially_refunded';

            $deposit->update([
                'refunded_amount' => $newRefunded,
                'refund_date'     => now()->toDateString(),
                'refund_reason'   => $reason,
                'status'          => $status,
            ]);

            return $deposit->fresh();
        });
    }
}
