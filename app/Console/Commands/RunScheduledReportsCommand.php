<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExecuteScheduledReportJob;
use App\Models\Reports\SavedReport;
use App\Services\Reports\ReportExportService;
use Illuminate\Console\Command;

class RunScheduledReportsCommand extends Command
{
    protected $signature = 'reports:run-scheduled
                            {--schedule= : Filter by schedule type (daily, weekly, monthly, quarterly)}
                            {--organization= : Filter by organization ID}
                            {--force : Run even if not due}
                            {--sync : Run synchronously instead of queueing}';

    protected $description = 'Run scheduled reports that are due for execution';

    public function handle(): int
    {
        $this->info('Checking for scheduled reports...');

        // saved_reports has no is_active column, and the schedule lives in
        // schedule_frequency. Both filters named columns that do not exist, so
        // this found no reports to run and said so cheerfully.
        $query = SavedReport::where('is_scheduled', true);

        // Filter by schedule type
        if ($schedule = $this->option('schedule')) {
            $query->where('schedule_frequency', $schedule);
        }

        // Filter by organization
        if ($orgId = $this->option('organization')) {
            $query->where('organization_id', $orgId);
        }

        // Filter by due time unless forced
        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
        }

        $reports = $query->get();

        if ($reports->isEmpty()) {
            $this->info('No scheduled reports due for execution.');

            return self::SUCCESS;
        }

        $this->info("Found {$reports->count()} report(s) to execute.");

        $bar = $this->output->createProgressBar($reports->count());
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($reports as $report) {
            try {
                $this->executeReport($report);
                $successful++;
            } catch (\Throwable $e) {
                $this->error("\nFailed to execute report '{$report->name}': {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed: {$successful} successful, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function executeReport(SavedReport $report): void
    {
        $this->line("\n  Processing: {$report->name} ({$report->report_type})");

        if ($this->option('sync')) {
            // Run synchronously
            $job = new ExecuteScheduledReportJob($report);
            $job->handle(app(ReportExportService::class));
        } else {
            // Dispatch to queue
            ExecuteScheduledReportJob::dispatch($report);
        }

        $this->line('  → Dispatched for execution');
    }
}
