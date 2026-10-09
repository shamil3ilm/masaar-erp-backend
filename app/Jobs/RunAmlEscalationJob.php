<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Services\Aml\AmlMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAmlEscalationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Minimum number of flags required to auto-create a SAR.
     *
     * Public because AmlMonitoringService decides whether to dispatch this job
     * and has to decide on the same number. It had its own: it dispatched at
     * two while this filed at three, so a two-flag transaction queued a job
     * that read its flags, found too few, and returned having done nothing -
     * leaving the flags at 'flagged' and no record of why nothing happened.
     * The common invoice-then-pay-immediately flow trips exactly two
     * (threshold_breach and rapid_movement), so that was ordinary traffic
     * queueing work that could not act.
     *
     * Three rather than two. A SAR is a report to a regulator, and filing one
     * automatically on an everyday invoice-and-payment pair is its own
     * compliance problem - a false report costs the filer credibility and
     * buries the real ones. Two flags still raise flags for a human to look
     * at; they do not file.
     */
    public const SAR_THRESHOLD = 3;

    public function __construct(
        private readonly string $transactionType,
        private readonly int    $transactionId,
        private readonly int    $organizationId,
    ) {
        $this->onQueue('aml-escalation');
    }

    /**
     * The kind of activity a set of flags describes.
     *
     * Every auto-filed SAR was typed 'structuring' regardless of what fired,
     * so a transaction flagged for a threshold breach and a high-risk contact
     * was reported to the regulator as structuring - which is a specific
     * offence it had no evidence of. The reasons were already computed for the
     * description and then discarded for the type.
     *
     * Most specific first. rapid_movement is layering in the ordinary AML
     * sense: funds moved on quickly to obscure their origin.
     *
     * Two of the activity types are never assigned here, deliberately.
     * sanctions_hit must come from actually screening a name against a
     * sanctions list, which these flags do not do - a high-risk contact is not
     * a sanctions match. smurfing means several people depositing on one
     * party's behalf, which nothing here detects. Naming either on the
     * strength of these flags would put a claim in a regulatory report that
     * the evidence does not support.
     *
     * @param  list<string>  $reasons
     */
    private function activityTypeFor(array $reasons): string
    {
        if (in_array(AmlTransactionFlag::STRUCTURING, $reasons, true)) {
            return AmlSuspiciousActivity::STRUCTURING;
        }

        if (in_array(AmlTransactionFlag::RAPID_MOVEMENT, $reasons, true)) {
            return AmlSuspiciousActivity::LAYERING;
        }

        return AmlSuspiciousActivity::UNUSUAL_PATTERN;
    }

    public function handle(AmlMonitoringService $service): void
    {
        $flags = AmlTransactionFlag::withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('transaction_type', $this->transactionType)
            ->where('transaction_id', $this->transactionId)
            ->where('status', AmlTransactionFlag::STATUS_FLAGGED)
            ->get();

        if ($flags->count() < self::SAR_THRESHOLD) {
            return;
        }

        // Idempotency: skip if a SAR already exists for this transaction.
        //
        // Not filtered by activity type any more. It filtered on structuring,
        // which was the only type this job ever wrote - so the check worked by
        // coincidence, and the moment the type is derived from the flags
        // (below) a retry would look for a structuring SAR, not find the
        // layering one it had just filed, and file a second report on the same
        // transaction. The transaction is what makes it a duplicate.
        $alreadyExists = AmlSuspiciousActivity::withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->whereJsonContains('related_transaction_ids', $this->transactionId)
            ->exists();

        if ($alreadyExists) {
            Log::info('RunAmlEscalationJob: SAR already exists, marking flags as escalated', [
                'transaction_type' => $this->transactionType,
                'transaction_id'   => $this->transactionId,
                'organization_id'  => $this->organizationId,
            ]);

            AmlTransactionFlag::withoutGlobalScopes()
                ->where('organization_id', $this->organizationId)
                ->where('transaction_type', $this->transactionType)
                ->where('transaction_id', $this->transactionId)
                ->update(['status' => AmlTransactionFlag::STATUS_ESCALATED]);

            return;
        }

        $contactId    = $flags->whereNotNull('contact_id')->first()?->contact_id;
        $flagReasons  = $flags->pluck('flag_reason')->unique()->implode(', ');
        $transactionIds = [$this->transactionId];

        $description = sprintf(
            'Auto-generated SAR: %d AML flags detected on %s #%d. Flag reasons: %s.',
            $flags->count(),
            $this->transactionType,
            $this->transactionId,
            $flagReasons,
        );

        try {
            $service->createSar(
                organizationId: $this->organizationId,
                contactId:      $contactId,
                activityType:   $this->activityTypeFor($flags->pluck('flag_reason')->all()),
                transactionIds: $transactionIds,
                description:    $description,
                createdBy:      null,
            );

            // Mark flags as escalated
            AmlTransactionFlag::withoutGlobalScopes()
                ->where('organization_id', $this->organizationId)
                ->where('transaction_type', $this->transactionType)
                ->where('transaction_id', $this->transactionId)
                ->update(['status' => AmlTransactionFlag::STATUS_ESCALATED]);

            Log::info('RunAmlEscalationJob: SAR auto-created', [
                'transaction_type' => $this->transactionType,
                'transaction_id'   => $this->transactionId,
                'organization_id'  => $this->organizationId,
                'flag_count'       => $flags->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('RunAmlEscalationJob: SAR creation failed', [
                'transaction_id'  => $this->transactionId,
                'organization_id' => $this->organizationId,
                'error'           => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunAmlEscalationJob failed permanently', [
            'transaction_type' => $this->transactionType,
            'transaction_id'   => $this->transactionId,
            'organization_id'  => $this->organizationId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
