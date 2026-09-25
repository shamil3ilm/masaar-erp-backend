<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Sales\Invoice;
use App\Notifications\Sales\InvoiceSentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies the compliance platform's invoice events to the invoice they name.
 *
 * The signature and replay checks run in middleware before this is reached.
 * Here each event is applied on the locked invoice row, and a status never
 * moves to a lower priority than the one recorded, so events arriving out of
 * order or twice leave the invoice where the latest meaningful one put it.
 * Events that cannot be placed are logged and dropped: the platform counts
 * any refusal as a failed delivery and disables a subscription after ten.
 */
final class ZatcaWebhookService
{
    /**
     * Status priority map — higher value means higher priority.
     * A status should never be downgraded to a lower-priority one.
     */
    private const STATUS_PRIORITY = [
        Invoice::COMPLIANCE_NOT_APPLICABLE => 0,
        Invoice::COMPLIANCE_PENDING => 1,
        Invoice::COMPLIANCE_SUBMITTED => 2,
        Invoice::COMPLIANCE_REPORTED => 3,
        Invoice::COMPLIANCE_CLEARED => 3,
        Invoice::COMPLIANCE_REJECTED => 4,
    ];

    public function process(mixed $event, mixed $data): void
    {
        if (empty($event) || ! is_string($event) || ! is_array($data)) {
            $this->logUnknownEvent(is_string($event) ? $event : '');

            return;
        }

        // The platform names the document by its own invoice id. That is what
        // its submission response returned, and what was stored here as
        // compliance_uuid.
        $complianceUuid = $data['invoice_id'] ?? null;

        if (empty($complianceUuid)) {
            $this->logUnroutable($event, null);

            return;
        }

        // compliance_uuid is unique, so the invoice carries its own
        // organisation. The payload's org_id is the platform's, not ours.
        $invoice = Invoice::withoutGlobalScopes()
            ->where('compliance_uuid', $complianceUuid)
            ->first();

        if (! $invoice) {
            // Credit notes are submitted too, and they are not in invoices.
            $this->logUnroutable($event, $complianceUuid);

            return;
        }

        match ($event) {
            'invoice.cleared' => $this->handleCleared($invoice, $data),
            'invoice.reported' => $this->applyStatus($invoice, Invoice::COMPLIANCE_REPORTED, $data, $event, updateHashAndQr: false),
            'invoice.rejected' => $this->handleRejected($invoice, $data),
            'invoice.issued' => $this->handleIssued($invoice, $data),
            default => $this->logUnknownEvent($event),
        };
    }

    /**
     * Records the clearance and, the first time a standard invoice is
     * cleared, sends the customer the notification InvoiceService::send()
     * withheld until clearance.
     */
    private function handleCleared(Invoice $invoice, array $data): void
    {
        $newlyCleared = $this->applyStatus($invoice, Invoice::COMPLIANCE_CLEARED, $data, 'invoice.cleared', updateHashAndQr: true);

        if (! $newlyCleared) {
            return;
        }

        try {
            $cleared = Invoice::withoutGlobalScopes()->with('customer')->find($invoice->id);

            if ($cleared?->invoice_type === Invoice::TYPE_STANDARD && $cleared->customer?->email) {
                $cleared->customer->notify(new InvoiceSentNotification($cleared));
            }
        } catch (\Throwable $e) {
            Log::warning('ZATCA webhook: clearance notification failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleRejected(Invoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data): void {
            $locked = $this->lockedInvoice($invoice);

            if (! $locked || $this->skipsDowngrade($locked, Invoice::COMPLIANCE_REJECTED, 'invoice.rejected')) {
                return;
            }

            $locked->compliance_status = Invoice::COMPLIANCE_REJECTED;
            $locked->compliance_response = $data['errors'] ?? $data['validation_results'] ?? $data;
            $locked->save();

            Log::info('ZATCA webhook: invoice.rejected processed', [
                'invoice_id' => $locked->id,
                'event' => 'invoice.rejected',
            ]);
        });
    }

    private function handleIssued(Invoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data): void {
            $locked = $this->lockedInvoice($invoice);

            if (! $locked) {
                return;
            }

            $fields = array_filter([
                'compliance_hash' => $data['hash'] ?? null,
                'compliance_qr_code' => $data['qr_code'] ?? null,
            ]);

            if (! empty($fields)) {
                $locked->fill($fields)->save();
            }

            Log::info('ZATCA webhook: invoice.issued processed', [
                'invoice_id' => $locked->id,
                'event' => 'invoice.issued',
            ]);
        });
    }

    /**
     * Moves the locked invoice to the status unless that is a downgrade.
     *
     * @return bool whether the invoice reached this status with this event,
     *              having held another before it
     */
    private function applyStatus(Invoice $invoice, string $newStatus, array $data, string $event, bool $updateHashAndQr): bool
    {
        return DB::transaction(function () use ($invoice, $newStatus, $data, $event, $updateHashAndQr): bool {
            $locked = $this->lockedInvoice($invoice);

            if (! $locked || $this->skipsDowngrade($locked, $newStatus, $event)) {
                return false;
            }

            $previousStatus = $locked->compliance_status;
            $fields = ['compliance_status' => $newStatus];

            if ($updateHashAndQr) {
                $fields += array_filter([
                    'compliance_hash' => $data['hash'] ?? null,
                    'compliance_qr_code' => $data['qr_code'] ?? null,
                ]);
            }

            $locked->fill($fields)->save();

            Log::info('ZATCA webhook: event processed', [
                'invoice_id' => $locked->id,
                'event' => $event,
                'new_status' => $newStatus,
            ]);

            return $previousStatus !== $newStatus;
        });
    }

    private function lockedInvoice(Invoice $invoice): ?Invoice
    {
        return $invoice->newQuery()
            ->withoutGlobalScopes()
            ->lockForUpdate()
            ->find($invoice->id);
    }

    private function skipsDowngrade(Invoice $locked, string $requested, string $event): bool
    {
        $currentPriority = self::STATUS_PRIORITY[$locked->compliance_status] ?? 0;
        $requestedPriority = self::STATUS_PRIORITY[$requested] ?? 0;

        if ($requestedPriority >= $currentPriority) {
            return false;
        }

        Log::info('ZATCA webhook: skipping downgrade', [
            'event' => $event,
            'invoice_id' => $locked->id,
            'current_status' => $locked->compliance_status,
            'requested_status' => $requested,
        ]);

        return true;
    }

    private function logUnroutable(string $event, ?string $complianceUuid): void
    {
        Log::warning('ZATCA webhook: no invoice for event', [
            'event' => $event,
            'compliance_uuid' => $complianceUuid,
        ]);
    }

    private function logUnknownEvent(string $event): void
    {
        Log::info('ZATCA webhook: unknown event received, acknowledging to prevent retries', [
            'event' => $event,
        ]);
    }
}
