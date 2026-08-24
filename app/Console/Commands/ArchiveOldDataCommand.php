<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Core\ArchiveService;
use Illuminate\Console\Command;

/**
 * Moves old, settled records into their archive tables.
 *
 * Scheduled weekly in routes/console.php. The archiving rules themselves live
 * in ArchiveService — this command only exposes them on the CLI.
 */
class ArchiveOldDataCommand extends Command
{
    protected $signature = 'erp:archive
        {--journal-days=365 : Archive posted journal entries older than this many days}
        {--invoice-days=365 : Archive settled invoices older than this many days}
        {--audit-days=180 : Archive audit logs older than this many days}
        {--batch=500 : Rows to process per transaction}
        {--dry-run : Report what would be archived without changing anything}';

    protected $description = 'Archive old ERP records to archive tables to keep live tables lean';

    public function handle(ArchiveService $archiveService): int
    {
        $options = [
            'journal_days' => (int) $this->option('journal-days'),
            'invoice_days' => (int) $this->option('invoice-days'),
            'audit_days'   => (int) $this->option('audit-days'),
            'batch_size'   => (int) $this->option('batch'),
        ];

        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? '[DRY RUN] Counting records to archive...' : 'Archiving old records...');

        $counts = $dryRun
            ? $archiveService->preview($options)
            : $archiveService->runAll($options);

        foreach ($counts as $entity => $count) {
            $this->line(sprintf('  %-16s %d', str_replace('_', ' ', $entity), $count));
        }

        $total = array_sum($counts);

        $this->info($dryRun
            ? "[DRY RUN] Would archive {$total} records total."
            : "Done. Archived {$total} records total.");

        return self::SUCCESS;
    }
}
