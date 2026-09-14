<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Core\Organization;
use App\Models\Reports\ReportExecution;
use App\Models\Reports\SavedReport;
use App\Models\User;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ExecuteScheduledReportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 600];
    public int $timeout = 600; // 10 minutes max
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return $this->report->id . ':' . now()->format('Y-m-d-H');
    }

    public function __construct(
        protected SavedReport $report,
        protected ?int $userId = null
    ) {}

    public function handle(ReportDataService $reportData, ReportExportService $exportService): void
    {
        // Idempotency: skip if a completed execution already exists within this job's timeout window
        // (prevents duplicate reports when the scheduler dispatches the job twice)
        $recentCompleted = ReportExecution::withoutGlobalScopes()
            ->where('saved_report_id', $this->report->id)
            ->where('status', ReportExecution::STATUS_COMPLETED)
            ->where('started_at', '>=', now()->subSeconds($this->timeout))
            ->exists();

        if ($recentCompleted) {
            Log::info('ExecuteScheduledReportJob: skipping duplicate dispatch — already completed', [
                'report_id' => $this->report->id,
            ]);
            return;
        }

        $execution = ReportExecution::withoutGlobalScopes()->create([
            'organization_id' => $this->report->organization_id,
            'saved_report_id' => $this->report->id,
            'user_id' => $this->userId ?? $this->report->user_id,
            'report_type' => $this->report->report_type,
            'parameters' => $this->report->parameters,
            'format' => $this->report->export_format,
            'trigger' => ReportExecution::TRIGGER_SCHEDULED,
            'status' => ReportExecution::STATUS_PENDING,
        ]);

        // The financial reports read the organisation from the signed-in user,
        // and a queued job has none, so the report runs as its owner. The user
        // is forgotten afterwards so the next job on this worker starts clean.
        auth()->setUser(User::findOrFail($this->report->user_id));

        try {
            $organization = Organization::find($this->report->organization_id);

            $data = $reportData->generate(
                $this->report->report_type,
                $this->report->parameters ?? [],
                $this->report->organization_id,
            );

            $exportService->setContext($this->report->organization_id, $organization?->toArray() ?? []);
            $path = $exportService->export($this->report->report_type, $data, $this->report->export_format, $execution);

            $this->report->update([
                'last_run_at' => now(),
                'next_run_at' => $this->report->calculateNextRunAt(),
            ]);

            $this->sendNotifications($execution, $organization, $path);

            Log::info("Report executed successfully: {$this->report->name}", [
                'execution_id' => $execution->id,
                'file_path' => $path,
            ]);
        } catch (\Throwable $e) {
            Log::error("Report execution failed: {$this->report->name}", [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $execution->markAsFailed($e->getMessage());

            throw $e;
        } finally {
            auth()->forgetUser();
        }
    }

    protected function sendNotifications(ReportExecution $execution, ?Organization $organization, string $filePath): void
    {
        $recipients = $this->report->recipients ?? [];

        if (empty($recipients)) {
            return;
        }

        try {
            if (! Storage::exists($filePath)) {
                Log::error('Report file missing', ['path' => $filePath]);
                return;
            }

            foreach ($recipients as $email) {
                Mail::send(
                    'emails.reports.scheduled-report',
                    [
                        'report' => $this->report,
                        'execution' => $execution,
                        'organization' => $organization,
                    ],
                    function ($message) use ($email, $filePath) {
                        $message->to($email)
                            ->subject("Scheduled Report: {$this->report->name}")
                            ->attach(Storage::path($filePath));
                    }
                );
            }

            Log::info("Report notifications sent", [
                'report_id' => $this->report->id,
                'recipients' => $recipients,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to send report notifications", [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Scheduled report job failed permanently", [
            'report_id' => $this->report->id,
            'report_name' => $this->report->name,
            'error' => $exception->getMessage(),
        ]);
    }
}
