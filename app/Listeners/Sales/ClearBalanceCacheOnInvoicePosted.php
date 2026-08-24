<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Sales\InvoicePosted;
use App\Services\Core\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Clears the cached customer balance after an invoice is posted, so the next
 * read recalculates it from the invoices.
 */
class ClearBalanceCacheOnInvoicePosted implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly CacheService $cache) {}

    public function handle(InvoicePosted $event): void
    {
        $customer = $event->invoice->customer;

        if (! $customer) {
            return;
        }

        $this->cache->bustCustomerBalance(
            (int) $event->invoice->organization_id,
            (int) $customer->id,
        );
    }
}
