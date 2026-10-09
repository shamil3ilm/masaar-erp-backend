<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Aml\AmlMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Screens one posted transaction - an invoice or a completed payment - against
 * the AML controls: the reporting threshold, structuring, rapid movement and
 * the contact's risk rating.
 *
 * The screening runs here rather than in the service that records the document
 * so that it cannot reach that document: AmlMonitoringService::screenTransaction()
 * re-throws, and inline it would take the invoice or payment down with it.
 * The figures travel as scalars rather than as a model so the payload still
 * names the transaction after the row is changed or removed.
 */
class RunAmlTransactionScreeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt only. A screening writes one flag row per control it trips
     * and reads none of them back first, so an attempt that fails after the
     * first write would, on a retry, flag the same transaction twice.
     */
    public int $tries = 1;

    public function __construct(
        private readonly string $transactionType,
        private readonly int    $transactionId,
        private readonly float  $amount,
        private readonly string $currency,
        private readonly int    $organizationId,
        private readonly ?int   $contactId = null,
    ) {
        $this->onQueue('aml-monitoring');
    }

    public function handle(AmlMonitoringService $service): void
    {
        $service->screenTransaction(
            transactionType: $this->transactionType,
            transactionId:   $this->transactionId,
            amount:          $this->amount,
            currency:        $this->currency,
            organizationId:  $this->organizationId,
            contactId:       $this->contactId,
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunAmlTransactionScreeningJob failed', [
            'transaction_type' => $this->transactionType,
            'transaction_id'   => $this->transactionId,
            'organization_id'  => $this->organizationId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
