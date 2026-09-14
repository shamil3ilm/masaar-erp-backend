<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\ManagerSelfServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagerSelfServiceController extends Controller
{
    public function __construct(
        private readonly ManagerSelfServiceService $service,
    ) {}

    /**
     * GET /api/v1/hr/manager/team
     */
    public function team(Request $request): JsonResponse
    {
        $includeIndirect = $request->boolean('include_indirect', false);
        $managerId       = (int) auth()->id();

        $team = $this->service->getTeam($managerId, $includeIndirect);

        return $this->success($team);
    }

    /**
     * GET /api/v1/hr/manager/pending-approvals
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $managerId = (int) auth()->id();
        $approvals = $this->service->getPendingApprovals($managerId);

        return $this->success($approvals);
    }

    /**
     * GET /api/v1/hr/manager/team-attendance?date=YYYY-MM-DD
     */
    public function teamAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $managerId = (int) auth()->id();
        $date      = $request->input('date', now()->toDateString());

        $attendance = $this->service->getTeamAttendance($managerId, $date);

        return $this->success($attendance);
    }

    /**
     * GET /api/v1/hr/manager/team-leave-calendar?month=YYYY-MM
     */
    public function teamLeaveCalendar(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $managerId = (int) auth()->id();
        $month     = $request->input('month', now()->format('Y-m'));

        $calendar = $this->service->getTeamLeaveCalendar($managerId, $month);

        return $this->success($calendar);
    }

    /**
     * GET /api/v1/hr/manager/delegations
     */
    public function delegations(Request $request): JsonResponse
    {
        $delegations = $this->service->listDelegations((int) auth()->id(), $request->integer('per_page', 15));

        return $this->paginated($delegations);
    }

    /**
     * POST /api/v1/hr/manager/delegations
     */
    public function createDelegation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // The delegate acts on the manager's approvals and is returned with the delegation.
            'delegate_id'     => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
            'delegation_type' => 'required|in:full,leave_approval,attendance_approval,expense_approval',
            'valid_from'      => 'required|date',
            'valid_to'        => 'nullable|date|after_or_equal:valid_from',
            'reason'          => 'nullable|string',
        ]);

        $validated['manager_id'] = auth()->id();

        try {
            $delegation = $this->service->createDelegation($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($delegation->load('delegate'), 'Delegation created.');
    }

    /**
     * DELETE /api/v1/hr/manager/delegations/{delegationId}
     */
    public function revokeDelegation(string $delegationId): JsonResponse
    {
        $delegation = $this->service->findDelegationForManager((int) auth()->id(), $delegationId);

        $delegation = $this->service->revokeDelegation($delegation);

        return $this->success($delegation, 'Delegation revoked.');
    }
}
