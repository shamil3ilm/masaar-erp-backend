<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Core\ApprovalWorkflowStep;
use App\Services\Automation\WorkflowService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class WorkflowController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly WorkflowService $workflows) {}

    /**
     * List approval workflows for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->workflows->paginateWorkflows(
            $request->user()->organization_id,
            [
                'approvable_type' => $request->approvable_type,
                'is_active' => $request->is_active !== null ? $request->boolean('is_active') : null,
                'search' => $request->search,
            ],
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Create a new approval workflow.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'approvable_type' => ['required', 'string', 'max:100'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'steps' => ['nullable', 'array'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.approver_type' => ['required', 'string', 'in:user,role'],
            'steps.*.approver_id' => ['required', 'integer'],
            'steps.*.sequence' => ['required', 'integer', 'min:0'],
            'steps.*.condition' => ['nullable', 'array'],
            ...$this->approverRules($request),
        ]);

        $workflow = $this->workflows->createWorkflow($this->organizationId($request), $validated);

        return $this->created($workflow, 'Approval workflow created successfully');
    }

    /**
     * Show a specific approval workflow.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $workflow = $this->workflows->findWorkflow($request->user()->organization_id, $id, withSteps: true);

        return $workflow ? $this->success($workflow) : $this->notFound('Workflow not found');
    }

    /**
     * Update an approval workflow.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $workflow = $this->workflows->findWorkflow($request->user()->organization_id, $id);

        if (!$workflow) {
            return $this->notFound('Workflow not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'approvable_type' => ['sometimes', 'string', 'max:100'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->success($this->workflows->updateWorkflow($workflow, $validated), 'Workflow updated successfully');
    }

    /**
     * List pending approval requests for the authenticated user.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        return $this->paginated($this->workflows->paginatePending(
            $request->user()->organization_id,
            $request->user()->id,
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Approve an approval request.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $approvalRequest = $this->workflows->findRequest($request->user()->organization_id, $id);
        $pendingAction = $this->workflows->pendingActionFor($approvalRequest, $request->user()->id);

        if (!$pendingAction) {
            return $this->error('No pending action found for this user', 'NO_PENDING_ACTION', 422);
        }

        return $this->tryAction(
            fn () => $this->workflows->approve($pendingAction, $request->user()->id, $validated['comments'] ?? null),
            'Request approved successfully'
        );
    }

    /**
     * Reject an approval request.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['required', 'string', 'max:1000'],
        ]);

        $approvalRequest = $this->workflows->findRequest($request->user()->organization_id, $id);
        $pendingAction = $this->workflows->pendingActionFor($approvalRequest, $request->user()->id);

        if (!$pendingAction) {
            return $this->error('No pending action found for this user', 'NO_PENDING_ACTION', 422);
        }

        return $this->tryAction(
            fn () => $this->workflows->reject($pendingAction, $request->user()->id, $validated['comments']),
            'Request rejected'
        );
    }

    /**
     * List approval history (completed approval requests).
     */
    public function history(Request $request): JsonResponse
    {
        return $this->paginated($this->workflows->paginateHistory(
            $request->user()->organization_id,
            $request->integer('per_page', 20)
        ));
    }

    /**
     * One exists rule per step for its approver: a user of the organization,
     * or a role of the organization or a global role. Without it a step could
     * route approvals, and their notifications, to another organization's user.
     *
     * @return array<string, list<mixed>>
     */
    private function approverRules(Request $request): array
    {
        $steps = $request->input('steps');

        if (!is_array($steps)) {
            return [];
        }

        $rules = [];

        foreach ($steps as $index => $step) {
            $type = is_array($step) ? ($step['approver_type'] ?? null) : null;

            $rules["steps.{$index}.approver_id"] = [
                $type === ApprovalWorkflowStep::APPROVER_TYPE_ROLE ? $this->ownedOrGlobalRole() : $this->ownedBy('users'),
            ];
        }

        return $rules;
    }

    private function ownedOrGlobalRole(): Exists
    {
        $organizationId = auth()->user()->organization_id;

        return Rule::exists('roles', 'id')->where(
            fn (Builder $query) => $query->where('organization_id', $organizationId)->orWhereNull('organization_id')
        );
    }
}
