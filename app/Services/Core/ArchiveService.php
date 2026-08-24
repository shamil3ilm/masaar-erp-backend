<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Invoice;
use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves old, settled records into archive tables to keep the hot tables lean.
 *
 * Only records that can no longer change are archived:
 *   - Journal entries: posted, older than the given number of days (default 365)
 *   - Invoices: paid/cancelled/void, older than the given number of days (default 365)
 *   - Audit logs: older than the given number of days (default 180)
 *
 * Archiving copies a record into its archive table and then hard-deletes the
 * original, so both steps run inside one transaction per batch.
 */
class ArchiveService
{
    public const DEFAULT_JOURNAL_DAYS = 365;
    public const DEFAULT_INVOICE_DAYS = 365;
    public const DEFAULT_AUDIT_DAYS   = 180;

    /** Journal entries old enough to archive. */
    private function journalEntriesOlderThan(int $daysOld): Builder
    {
        return JournalEntry::where('status', 'posted')
            ->where('entry_date', '<', Carbon::now()->subDays($daysOld));
    }

    /** Invoices old enough to archive. */
    private function invoicesOlderThan(int $daysOld): Builder
    {
        return Invoice::whereIn('status', ['paid', 'cancelled', 'void'])
            ->where('invoice_date', '<', Carbon::now()->subDays($daysOld));
    }

    /** Audit logs old enough to archive. */
    private function auditLogsOlderThan(int $daysOld): Builder
    {
        return AuditLog::where('created_at', '<', Carbon::now()->subDays($daysOld));
    }

    /**
     * Count what would be archived, without changing anything.
     *
     * @param  array{journal_days?: int, invoice_days?: int, audit_days?: int}  $options
     * @return array{journal_entries: int, invoices: int, audit_logs: int}
     */
    public function preview(array $options = []): array
    {
        return [
            'journal_entries' => $this->journalEntriesOlderThan($options['journal_days'] ?? self::DEFAULT_JOURNAL_DAYS)->count(),
            'invoices'        => $this->invoicesOlderThan($options['invoice_days'] ?? self::DEFAULT_INVOICE_DAYS)->count(),
            'audit_logs'      => $this->auditLogsOlderThan($options['audit_days'] ?? self::DEFAULT_AUDIT_DAYS)->count(),
        ];
    }

    public function archiveJournalEntries(int $daysOld = self::DEFAULT_JOURNAL_DAYS, int $batchSize = 500): int
    {
        $archived = 0;

        $this->journalEntriesOlderThan($daysOld)
            ->chunkById($batchSize, function ($entries) use (&$archived) {
                DB::transaction(function () use ($entries, &$archived) {
                    foreach ($entries as $entry) {
                        DB::table('journal_entry_archives')->insertOrIgnore([
                            'uuid'            => $entry->uuid,
                            'organization_id' => $entry->organization_id,
                            'entry_number'    => $entry->entry_number,
                            'type'            => $entry->source_type,
                            'reference'       => $entry->reference,
                            'description'     => $entry->description,
                            'entry_date'      => $entry->entry_date,
                            'total_debit'     => $entry->total_debit,
                            'total_credit'    => $entry->total_credit,
                            'status'          => $entry->status,
                            'currency_code'   => $entry->currency_code ?? 'SAR',
                            'metadata'        => json_encode(['lines_count' => $entry->lines()->count()]),
                            'archived_at'     => now(),
                            'created_at'      => $entry->created_at,
                            'updated_at'      => now(),
                        ]);
                        $entry->forceDelete();
                        $archived++;
                    }
                });
            });

        Log::info('ArchiveService: journal entries archived', ['count' => $archived, 'days_old' => $daysOld]);

        return $archived;
    }

    public function archiveInvoices(int $daysOld = self::DEFAULT_INVOICE_DAYS, int $batchSize = 500): int
    {
        $archived = 0;

        $this->invoicesOlderThan($daysOld)
            ->chunkById($batchSize, function ($invoices) use (&$archived) {
                DB::transaction(function () use ($invoices, &$archived) {
                    foreach ($invoices as $invoice) {
                        DB::table('invoice_archives')->insertOrIgnore([
                            'uuid'            => $invoice->uuid,
                            'organization_id' => $invoice->organization_id,
                            'invoice_number'  => $invoice->invoice_number,
                            'status'          => $invoice->status,
                            'invoice_date'    => $invoice->invoice_date,
                            'due_date'        => $invoice->due_date,
                            'subtotal'        => $invoice->subtotal,
                            'tax_amount'      => $invoice->tax_amount,
                            'total'           => $invoice->total,
                            'amount_paid'     => $invoice->amount_paid,
                            'amount_due'      => $invoice->amount_due,
                            'currency_code'   => $invoice->currency_code ?? 'SAR',
                            'snapshot'        => json_encode([
                                'customer_id' => $invoice->customer_id,
                                'lines_count' => $invoice->lines()->count(),
                            ]),
                            'archived_at'     => now(),
                            'created_at'      => $invoice->created_at,
                            'updated_at'      => now(),
                        ]);
                        $invoice->forceDelete();
                        $archived++;
                    }
                });
            });

        Log::info('ArchiveService: invoices archived', ['count' => $archived, 'days_old' => $daysOld]);

        return $archived;
    }

    public function archiveAuditLogs(int $daysOld = self::DEFAULT_AUDIT_DAYS, int $batchSize = 1000): int
    {
        $archived = 0;

        $this->auditLogsOlderThan($daysOld)
            ->chunkById($batchSize, function ($logs) use (&$archived) {
                DB::transaction(function () use ($logs, &$archived) {
                    foreach ($logs as $log) {
                        DB::table('audit_log_archives')->insertOrIgnore([
                            'organization_id' => $log->organization_id,
                            'event'           => $log->event,
                            'auditable_type'  => $log->auditable_type,
                            'auditable_id'    => $log->auditable_id,
                            'user_id'         => $log->user_id,
                            'old_values'      => json_encode($log->old_values),
                            'new_values'      => json_encode($log->new_values),
                            'ip_address'      => $log->ip_address,
                            'archived_at'     => now(),
                            'created_at'      => $log->created_at,
                            'updated_at'      => now(),
                        ]);
                        $log->forceDelete();
                        $archived++;
                    }
                });
            });

        Log::info('ArchiveService: audit logs archived', ['count' => $archived, 'days_old' => $daysOld]);

        return $archived;
    }

    /**
     * Run all archive routines and return counts per entity type.
     *
     * @param  array{journal_days?: int, invoice_days?: int, audit_days?: int, batch_size?: int}  $options
     * @return array{journal_entries: int, invoices: int, audit_logs: int}
     */
    public function runAll(array $options = []): array
    {
        return [
            'journal_entries' => $this->archiveJournalEntries(
                $options['journal_days'] ?? self::DEFAULT_JOURNAL_DAYS,
                $options['batch_size'] ?? 500,
            ),
            'invoices'        => $this->archiveInvoices(
                $options['invoice_days'] ?? self::DEFAULT_INVOICE_DAYS,
                $options['batch_size'] ?? 500,
            ),
            'audit_logs'      => $this->archiveAuditLogs(
                $options['audit_days'] ?? self::DEFAULT_AUDIT_DAYS,
                $options['batch_size'] ?? 1000,
            ),
        ];
    }
}
