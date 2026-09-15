<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\JobMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class JobMonitorController extends Controller
{
    public function __construct(
        protected JobMonitorService $service
    ) {}

    /**
     * GET /job-monitor
     * List all jobs, filterable by status, queue, job_class, date range.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => 'nullable|string|in:queued,running,completed,failed,retrying',
            'queue_name'  => 'nullable|string',
            'job_class'   => 'nullable|string',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $results = $this->service->list(
            $this->organizationId($request),
            $request->only(['status', 'queue_name', 'job_class', 'date_from', 'date_to']),
            $this->safeSortBy($request->get('sort_by'), ['queued_at', 'status', 'job_name', 'job_class'], 'queued_at'),
            $this->safeSortOrder($request->get('sort_dir'), 'desc'),
            (int) $request->get('per_page', 20),
        );

        return $this->paginated($results);
    }

    /**
     * GET /job-monitor/{id}
     * Show job details including logs.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->service->details($id));
    }

    /**
     * GET /job-monitor/stats
     * Summary statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $stats = $this->service->getStats($organizationId);

        return $this->success($stats);
    }

    /**
     * GET /job-monitor/running
     * Currently running jobs.
     */
    public function running(Request $request): JsonResponse
    {
        $jobs = $this->service->getRunningJobs();

        return $this->success($jobs);
    }

    /**
     * GET /job-monitor/failed
     * Failed jobs, optionally scoped to org.
     */
    public function failed(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $jobs = $this->service->getFailedJobs($organizationId);

        return $this->success($jobs);
    }

    /**
     * POST /job-monitor/{id}/retry
     * Retry a failed job.
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        try {
            $this->service->retryFailed($id);

            return $this->success($this->service->find($id), 'Job queued for retry');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'RETRY_FAILED', 422);
        }
    }

    /**
     * GET /job-monitor/{id}/logs
     * Logs for a specific job.
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'level'    => 'nullable|string|in:info,warning,error,debug',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $logs = $this->service->logs(
            $id,
            $request->filled('level') ? (string) $request->get('level') : null,
            (int) $request->get('per_page', 50),
        );

        return $this->paginated($logs);
    }

    /**
     * POST /job-monitor/cleanup
     * Delete old completed/failed records.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days_to_keep' => 'nullable|integer|min:1|max:365',
        ]);

        $daysToKeep = $validated['days_to_keep'] ?? 30;

        $deleted = $this->service->cleanOldRecords($daysToKeep);

        return $this->success(
            ['deleted_count' => $deleted],
            "Cleaned up {$deleted} old job record(s)"
        );
    }
}
