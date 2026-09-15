<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Core\ApprovalAction;
use App\Models\Core\ApprovalRequest;
use App\Models\Core\ApprovalWorkflow;
use App\Models\Core\ApprovalWorkflowStep;
use App\Services\Core\ApprovalWorkflowService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The organization's approval workflows and the approval inbox.
 *
 * Approving and rejecting go through ApprovalWorkflowService, which locks the
 * action and request, refuses self-approval, hands the request to the next
 * step's approvers and notifies the approved or rejected document.
 */
class WorkflowService
{
    public function __construct(private readonly ApprovalWorkflowService $approvals) {}

    /**
     * @param  array{approvable_type?: ?string, is_active?: ?bool, search?: ?string}  $filters
     */
    public function paginateWorkflows(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return ApprovalWorkflow::with('steps')
            ->where('organization_id', $organizationId)
            ->when($filters['approvable_type'] ?? null, fn ($query, $type) => $query->where('approvable_type', $type))
            ->when(($filters['is_active'] ?? null) !== null, fn ($query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a workflow with its steps. Step approvers must already be
     * validated as users or roles the organization may use.
     */
    public function createWorkflow(int $organizationId, array $data): ApprovalWorkflow
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $workflow = ApprovalWorkflow::create([
                'organization_id' => $organizationId,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'approvable_type' => $data['approvable_type'],
                'min_amount' => $data['min_amount'] ?? null,
                'max_amount' => $data['max_amount'] ?? null,
                'conditions' => $data['conditions'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'priority' => $data['priority'] ?? 0,
            ]);

            foreach ($data['steps'] ?? [] as $step) {
                ApprovalWorkflowStep::create([
                    'approval_workflow_id' => $workflow->id,
                    'name' => $step['name'],
                    'approver_type' => $step['approver_type'],
                    'approver_id' => $step['approver_id'],
                    'sequence' => $step['sequence'],
                    'action_type' => 'approve',
                    'conditions' => $step['condition'] ?? null,
                ]);
            }

            return $workflow->load('steps');
        });
    }

    public function findWorkflow(int $organizationId, int $workflowId, bool $withSteps = false): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::with($withSteps ? ['steps'] : [])
            ->where('organization_id', $organizationId)
            ->find($workflowId);
    }

    public function updateWorkflow(ApprovalWorkflow $workflow, array $data): ApprovalWorkflow
    {
        $workflow->update($data);

        return $workflow->load('steps');
    }

    /**
     * Open requests with an action pending for the user, latest submitted first.
     */
    public function paginatePending(int $organizationId, int $userId, int $perPage): LengthAwarePaginator
    {
        return ApprovalRequest::with(['workflow', 'currentStep', 'submittedBy'])
            ->where('organization_id', $organizationId)
            ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_IN_PROGRESS])
            ->whereHas('actions', fn ($query) => $query->where('assigned_to', $userId)->where('status', ApprovalAction::STATUS_PENDING))
            ->orderByDesc('submitted_at')
            ->paginate($perPage);
    }

    /**
     * Approved, rejected and cancelled requests, latest completed first.
     */
    public function paginateHistory(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return ApprovalRequest::with(['workflow', 'submittedBy', 'actions.actionBy'])
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                ApprovalRequest::STATUS_APPROVED,
                ApprovalRequest::STATUS_REJECTED,
                ApprovalRequest::STATUS_CANCELLED,
            ])
            ->orderByDesc('completed_at')
            ->paginate($perPage);
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findRequest(int $organizationId, int $requestId): ApprovalRequest
    {
        return ApprovalRequest::with(['workflow', 'actions'])
            ->where('organization_id', $organizationId)
            ->findOrFail($requestId);
    }

    /**
     * The user's pending action on the request's current step, or null.
     */
    public function pendingActionFor(ApprovalRequest $request, int $userId): ?ApprovalAction
    {
        return $request->actions()
            ->where('workflow_step_id', $request->current_step_id)
            ->where('assigned_to', $userId)
            ->where('status', ApprovalAction::STATUS_PENDING)
            ->first();
    }

    /**
     * @throws \InvalidArgumentException when the action was processed or the user may not approve it
     */
    public function approve(ApprovalAction $action, int $userId, ?string $comments): ApprovalRequest
    {
        return $this->approvals->approve($action, $userId, $comments);
    }

    /**
     * @throws \InvalidArgumentException when the action was processed or the user may not reject it
     */
    public function reject(ApprovalAction $action, int $userId, string $comments): ApprovalRequest
    {
        return $this->approvals->reject($action, $userId, $comments)->load(['workflow', 'actions']);
    }
}
