<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Sales\PaymentReceived;
use App\Services\Core\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Clears the cached customer balance after a payment is received, so the next
 * read recalculates it from the invoices.
 */
class ClearBalanceCacheOnPaymentReceived implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly CacheService $cache) {}

    public function handle(PaymentReceived $event): void
    {
        $customer = $event->payment->customer;

        if (! $customer) {
            return;
        }

        $this->cache->bustCustomerBalance(
            (int) $event->payment->organization_id,
            (int) $customer->id,
        );
    }
}
