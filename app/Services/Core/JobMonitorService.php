<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\JobMonitor;
use App\Models\Core\JobMonitorLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JobMonitorService
{
    public function register(string $jobClass, string $jobName, array $options = []): JobMonitor
    {
        return JobMonitor::create([
            'organization_id'      => $options['organization_id'] ?? null,
            'job_class'            => $jobClass,
            'job_name'             => $jobName,
            'queue_name'           => $options['queue_name'] ?? 'default',
            'status'               => JobMonitor::STATUS_QUEUED,
            'payload'              => $options['payload'] ?? null,
            'max_attempts'         => $options['max_attempts'] ?? 3,
            'triggered_by'         => $options['triggered_by'] ?? JobMonitor::TRIGGERED_MANUAL,
            'triggered_by_user_id' => $options['triggered_by_user_id'] ?? null,
            'tags'                 => $options['tags'] ?? null,
            'queued_at'            => Carbon::now(),
        ]);
    }

    public function markRunning(int $monitorId): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->markRunning();
    }

    public function markCompleted(int $monitorId, string $output = ''): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->markCompleted($output);
    }

    public function markFailed(int $monitorId, string $error): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->markFailed($error);
    }

    public function updateProgress(int $monitorId, int $percentage, string $message = ''): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->updateProgress($percentage, $message);
    }

    public function log(int $monitorId, string $level, string $message, array $context = []): void
    {
        JobMonitorLog::create([
            'job_monitor_id' => $monitorId,
            'level'          => $level,
            'message'        => $message,
            'context'        => !empty($context) ? $context : null,
            'created_at'     => Carbon::now(),
        ]);
    }

    public function getStats(?int $organizationId = null): array
    {
        $query = JobMonitor::query();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $total    = (clone $query)->count();
        $queued   = (clone $query)->where('status', JobMonitor::STATUS_QUEUED)->count();
        $running  = (clone $query)->where('status', JobMonitor::STATUS_RUNNING)->count();
        $completed = (clone $query)->where('status', JobMonitor::STATUS_COMPLETED)->count();
        $failed   = (clone $query)->where('status', JobMonitor::STATUS_FAILED)->count();
        $retrying = (clone $query)->where('status', JobMonitor::STATUS_RETRYING)->count();

        $avgDuration = (clone $query)
            ->where('status', JobMonitor::STATUS_COMPLETED)
            ->whereNotNull('run_duration_seconds')
            ->avg('run_duration_seconds');

        $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

        return [
            'total'         => $total,
            'queued'        => $queued,
            'running'       => $running,
            'completed'     => $completed,
            'failed'        => $failed,
            'retrying'      => $retrying,
            'avg_duration'  => $avgDuration ? round((float) $avgDuration, 2) : null,
            'failure_rate'  => $failureRate,
        ];
    }

    public function getFailedJobs(?int $organizationId = null): Collection
    {
        $query = JobMonitor::failed()->with('triggeredByUser')->orderByDesc('failed_at');

        if ($organizationId !== null) {
            $query->forOrganization($organizationId);
        }

        return $query->get();
    }

    public function getRunningJobs(): Collection
    {
        return JobMonitor::running()
            ->with('triggeredByUser')
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Jobs of the organization and platform jobs (no organization), or all
     * jobs when no organization is given, narrowed by the filters that are set.
     *
     * @param  array{status?: ?string, queue_name?: ?string, job_class?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function list(?int $organizationId, array $filters, string $sortBy, string $sortDir, int $perPage): LengthAwarePaginator
    {
        $query = JobMonitor::query()->with('triggeredByUser');

        if ($organizationId !== null) {
            $query->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->orWhereNull('organization_id');
            });
        }

        return $query
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['queue_name']), fn ($q) => $q->byQueue($filters['queue_name']))
            ->when(!empty($filters['job_class']), fn ($q) => $q->where('job_class', 'like', '%' . $filters['job_class'] . '%'))
            ->when(!empty($filters['date_from']), fn ($q) => $q->where('queued_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->where('queued_at', '<=', $filters['date_to'] . ' 23:59:59'))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function find(int $monitorId): JobMonitor
    {
        return $this->findOrFail($monitorId);
    }

    /**
     * A job with who triggered it and its logs.
     */
    public function details(int $monitorId): JobMonitor
    {
        return JobMonitor::with(['triggeredByUser', 'logs'])->findOrFail($monitorId);
    }

    /**
     * A job's logs, oldest first, narrowed to one level when given.
     */
    public function logs(int $monitorId, ?string $level, int $perPage): LengthAwarePaginator
    {
        return $this->findOrFail($monitorId)
            ->logs()
            ->orderBy('created_at')
            ->when($level !== null, fn ($q) => $q->where('level', $level))
            ->paginate($perPage);
    }

    /**
     * Queues a failed job for another attempt. The status and attempt count
     * are checked on the locked row, so two retries cannot both queue it.
     */
    public function retryFailed(int $monitorId): void
    {
        $monitor = DB::transaction(function () use ($monitorId): JobMonitor {
            $monitor = JobMonitor::query()->lockForUpdate()->findOrFail($monitorId);

            if ($monitor->status !== JobMonitor::STATUS_FAILED) {
                throw new RuntimeException('Only failed jobs can be retried.');
            }

            if ($monitor->attempts >= $monitor->max_attempts) {
                throw new RuntimeException("Job has reached max attempts ({$monitor->max_attempts}).");
            }

            $monitor->update([
                'status'        => JobMonitor::STATUS_RETRYING,
                'error_message' => null,
                'failed_at'     => null,
                'next_retry_at' => Carbon::now()->addSeconds(30),
            ]);

            return $monitor;
        });

        $this->log($monitorId, JobMonitorLog::LEVEL_INFO, 'Job queued for retry', [
            'attempt' => $monitor->attempts + 1,
        ]);
    }

    public function cleanOldRecords(int $daysToKeep = 30): int
    {
        $cutoff = Carbon::now()->subDays($daysToKeep);

        $ids = JobMonitor::whereIn('status', [JobMonitor::STATUS_COMPLETED, JobMonitor::STATUS_FAILED])
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        JobMonitorLog::whereIn('job_monitor_id', $ids)->delete();
        JobMonitor::whereIn('id', $ids)->delete();

        return $ids->count();
    }

    private function findOrFail(int $monitorId): JobMonitor
    {
        return JobMonitor::findOrFail($monitorId);
    }
}
