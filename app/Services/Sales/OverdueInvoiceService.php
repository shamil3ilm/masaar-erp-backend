<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Invoice;
use App\Notifications\Sales\InvoiceOverdueNotification;
use App\Traits\StructuredLogger;
use Illuminate\Support\Carbon;

/**
 * Moves past-due invoices to the overdue status and reminds the customer.
 *
 * Scheduled hourly by the invoices:mark-overdue command.
 */
class OverdueInvoiceService
{
    use StructuredLogger;

    /**
     * Move past-due invoices to the overdue status and tell the customer.
     *
     * Only invoices that actually change status are notified, so the customer
     * receives one reminder per invoice however often this runs.
     *
     * @return int  Number of invoices moved to overdue.
     */
    public function markOverdueInvoices(): int
    {
        $moved = 0;

        Invoice::withoutGlobalScopes()
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('due_date', '<', now())
            ->with('customer')
            ->chunkById(200, function ($invoices) use (&$moved): void {
                foreach ($invoices as $invoice) {
                    $invoice->transitionTo(Invoice::STATUS_OVERDUE);
                    $moved++;

                    $this->notifyCustomerOfOverdueInvoice($invoice);
                }
            });

        return $moved;
    }

    /**
     * Send the overdue reminder, without letting a delivery failure stop the run.
     */
    private function notifyCustomerOfOverdueInvoice(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        if (! $customer || empty($customer->email)) {
            return;
        }

        $daysOverdue = (int) now()->startOfDay()->diffInDays(
            Carbon::parse($invoice->due_date)->startOfDay()
        );

        try {
            $customer->notify(new InvoiceOverdueNotification($invoice, $daysOverdue));
        } catch (\Throwable $e) {
            $this->logWarning('Overdue invoice notification failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
