<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\AuditChecklist;
use App\Models\Manufacturing\AuditFinding;
use App\Models\Manufacturing\AuditPlan;
use App\Models\Manufacturing\AuditReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Audit plans with their checklist items, findings and reports. Those child
 * rows have no organization column; they are reached only through an audit
 * plan of the organization.
 */
class AuditManagementService
{
    public function list(int $organizationId): LengthAwarePaginator
    {
        return AuditPlan::where('organization_id', $organizationId)
            ->with('leadAuditor')
            ->paginate(20);
    }

    public function create(int $organizationId, array $data): AuditPlan
    {
        return AuditPlan::create([
            ...$data,
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * One of the organization's audit plans; a missing id is a 404.
     *
     * @param  array<int, string>  $with
     */
    public function find(int $organizationId, int $id, array $with = []): AuditPlan
    {
        return AuditPlan::where('organization_id', $organizationId)
            ->with($with)
            ->findOrFail($id);
    }

    public function addChecklistItem(AuditPlan $plan, array $data): AuditChecklist
    {
        return $plan->checklists()->create([...$data, 'uuid' => (string) Str::uuid()]);
    }

    /**
     * A checklist item of the plan; an item of another plan is a 404.
     */
    public function findChecklistItem(AuditPlan $plan, int $checklistId): AuditChecklist
    {
        return $plan->checklists()->findOrFail($checklistId);
    }

    public function answerChecklistItem(AuditChecklist $item, array $data): AuditChecklist
    {
        $item->update($data);

        return $item;
    }

    public function addFinding(AuditPlan $plan, array $data): AuditFinding
    {
        return $plan->findings()->create([...$data, 'uuid' => (string) Str::uuid()]);
    }

    /**
     * Close a finding of the plan; a finding of another plan is a 404.
     */
    public function closeFinding(AuditPlan $plan, int $findingId): AuditFinding
    {
        $finding = $plan->findings()->findOrFail($findingId);

        $finding->update(['status' => 'closed']);

        return $finding;
    }

    /**
     * Issue the plan's report and mark the plan completed.
     *
     * Both are written on the locked plan in one transaction, so a report is
     * never kept for a plan that failed to complete.
     */
    public function createReport(AuditPlan $plan, array $data): AuditReport
    {
        return $plan->lockForTransition(function (AuditPlan $plan) use ($data): AuditReport {
            $report = AuditReport::create([
                ...$data,
                'uuid'          => (string) Str::uuid(),
                'audit_plan_id' => $plan->id,
            ]);

            $plan->update(['status' => 'completed']);

            return $report;
        });
    }
}
