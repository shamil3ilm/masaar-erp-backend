<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Core\Organization;
use App\Models\Reports\ReportExecution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The record of each report export: who ran it, in what format, and the file
 * it produced.
 */
class ReportExecutionService
{
    /**
     * Record a pending export the user started by hand.
     */
    public function startManual(User $user, string $reportType, string $format, array $parameters): ReportExecution
    {
        return ReportExecution::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'report_type' => $reportType,
            'parameters' => $parameters,
            'format' => $format,
            'trigger' => ReportExecution::TRIGGER_MANUAL,
            'status' => ReportExecution::STATUS_PENDING,
        ]);
    }

    /**
     * The organization's details printed on an exported report.
     */
    public function organizationData(int $organizationId): array
    {
        return Organization::find($organizationId)?->toArray() ?? [];
    }

    /**
     * An execution of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findForOrganization(int $organizationId, int $executionId): ReportExecution
    {
        return ReportExecution::where('organization_id', $organizationId)->findOrFail($executionId);
    }

    /**
     * The user's executions, newest first.
     */
    public function paginateForUser(User $user, mixed $perPage): LengthAwarePaginator
    {
        return ReportExecution::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->with('savedReport:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
