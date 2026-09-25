<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Automation\AutomationScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs the automation rules whose schedule has come due.
 *
 * A rule with a schedule trigger only writes a pending entry for its next run;
 * this is what picks those entries up, for every organization, since cron
 * carries no tenant of its own.
 */
class ProcessAutomationSchedulesCommand extends Command
{
    protected $signature = 'automation:process-schedules';

    protected $description = 'Run the automation rules whose schedule has come due';

    public function handle(AutomationScheduleService $schedules): int
    {
        try {
            $results = $schedules->processScheduledRules();
        } catch (\Throwable $e) {
            $this->error("Failed to process automation schedules: {$e->getMessage()}");
            Log::error('automation:process-schedules failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $summary = [
            'processed' => count($results),
            'completed' => $this->countWithStatus($results, 'completed'),
            'failed' => $this->countWithStatus($results, 'failed'),
            'skipped' => $this->countWithStatus($results, 'skipped'),
        ];

        $this->info(sprintf(
            'Processed %d due schedule(s): %d completed, %d failed, %d skipped.',
            $summary['processed'],
            $summary['completed'],
            $summary['failed'],
            $summary['skipped'],
        ));

        Log::info('automation:process-schedules: swept the due automation schedules.', $summary);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function countWithStatus(array $results, string $status): int
    {
        return count(array_filter($results, fn (array $result): bool => ($result['status'] ?? null) === $status));
    }
}
