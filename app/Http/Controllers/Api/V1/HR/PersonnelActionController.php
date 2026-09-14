<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\PersonnelActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Personnel Actions Controller — SAP PA40.
 *
 * POST   /hr/personnel-actions                       initiate
 * GET    /hr/personnel-actions                       index
 * GET    /hr/personnel-actions/{id}                  show
 * POST   /hr/personnel-actions/{id}/submit           → submitted
 * POST   /hr/personnel-actions/{id}/approve          → approved + executed
 * POST   /hr/personnel-actions/{id}/reject           → rejected
 * POST   /hr/personnel-actions/{id}/reverse          → reversed
 */
class PersonnelActionController extends Controller
{
    public function __construct(
        private readonly PersonnelActionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->service->list(
            $request->user()->organization_id,
            $request->only(['employee_id', 'action_type', 'status'])
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', $this->inOrganization('employees')],
            'action_type' => 'required|string|in:hire,rehire,transfer,promotion,demotion,exit,leave_of_absence',
            'effective_date' => 'required|date',
            'payload' => 'nullable|array',
            // The action's steps write these ids onto the employee record.
            'payload.department_id' => ['nullable', 'integer', $this->inOrganization('departments')],
            'payload.designation_id' => ['nullable', 'integer', $this->inOrganization('designations')],
            'payload.position_id' => ['nullable', 'integer', $this->inOrganization('positions')],
            'payload.cost_center_id' => ['nullable', 'integer', $this->inOrganization('cost_centers')],
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
        ]);

        $action = $this->service->initiate($validated, $request->user());

        return $this->success($action->load('steps'), 'Personnel action initiated', 201);
    }

    public function show(string $id): JsonResponse
    {
        return $this->success($this->service->findWithDetails($id));
    }

    public function submit(string $id): JsonResponse
    {
        $action = $this->service->find($id);

        return $this->transition(
            fn () => $this->service->submit($action),
            'Personnel action submitted for approval'
        );
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $action = $this->service->find($id);

        return $this->transition(
            fn () => $this->service->approve($action, $request->user())->load('steps'),
            'Personnel action approved and executed'
        );
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $action = $this->service->find($id);

        return $this->transition(
            fn () => $this->service->reject($action, $request->user(), $validated['reason']),
            'Personnel action rejected'
        );
    }

    public function reverse(Request $request, string $id): JsonResponse
    {
        $action = $this->service->find($id);

        return $this->transition(
            fn () => $this->service->reverse($action, $request->user()),
            'Personnel action reversed'
        );
    }

    /**
     * Runs a status change, answering one the action's status or type does not
     * allow with 422 INVALID_STATUS, as the other HR lifecycle endpoints do.
     */
    private function transition(callable $change, string $message): JsonResponse
    {
        try {
            return $this->success($change(), $message);
        } catch (\LogicException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }
    }

    /** An id that must belong to a row of the caller's organization. */
    private function inOrganization(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('organization_id', auth()->user()->organization_id);
    }
}
