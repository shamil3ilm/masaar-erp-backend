<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\HR\LeaveRequestResource;
use App\Models\HR\LeaveRequest;
use App\Services\HR\EmployeeService;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function __construct(
        private LeaveService $leaveService,
        private EmployeeService $employeeService,
    ) {
    }

    /**
     * List leave types.
     */
    public function leaveTypes(): JsonResponse
    {
        return $this->success($this->leaveService->activeTypes());
    }

    /**
     * List leave requests with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = $this->leaveService->listRequests(
            [
                'status'        => $request->status,
                'employee_id'   => $request->employee_id,
                'leave_type_id' => $request->leave_type_id,
                'pending'       => $request->pending,
                'start_date'    => $request->start_date,
                'end_date'      => $request->end_date,
            ],
            $this->safeSortBy($request->sort_by, ['from_date', 'to_date', 'status', 'created_at', 'updated_at'], 'from_date'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15)
        );

        return $this->paginated($requests, LeaveRequestResource::class);
    }

    /**
     * Store a new leave request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id)],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('organization_id', auth()->user()->organization_id)],
            'from_date' => 'required|date|after_or_equal:today',
            'to_date' => 'required|date|after_or_equal:from_date',
            'is_half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|required_if:is_half_day,true|in:first_half,second_half',
            'reason' => 'nullable|string|max:500',
            'contact_during_leave' => 'nullable|string|max:100',
            'address_during_leave' => 'nullable|string|max:500',
            'attachment_path' => 'nullable|string|max:500',
        ]);

        $employee = $this->employeeService->find((int) $validated['employee_id']);

        try {
            $leaveRequest = $this->leaveService->createRequest($employee, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new LeaveRequestResource($leaveRequest), 'Leave request created successfully.');
    }

    /**
     * Show a specific leave request.
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->success(new LeaveRequestResource(
            $leaveRequest->load(['employee', 'leaveType', 'approver'])
        ));
    }

    /**
     * Submit leave request for approval.
     */
    public function submit(LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->tryAction(
            fn() => new LeaveRequestResource($this->leaveService->submit($leaveRequest)),
            'Leave request submitted successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Approve or reject a leave request.
     * POST /leave/requests/{id}/review  {"action": "approve"|"reject", "rejection_reason": "..."}
     */
    public function review(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $validated = $request->validate([
            'action'           => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
        ]);

        if ($validated['action'] === 'approve') {
            return $this->tryAction(
                fn() => new LeaveRequestResource($this->leaveService->approve($leaveRequest, auth()->id())),
                'Leave request approved successfully.',
            );
        }

        return $this->tryAction(
            fn() => new LeaveRequestResource($this->leaveService->reject($leaveRequest, $validated['rejection_reason'], auth()->id())),
            'Leave request rejected.',
        );
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);

        return $this->tryAction(
            fn() => new LeaveRequestResource($this->leaveService->cancel($leaveRequest, $validated['reason'])),
            'Leave request cancelled.',
        );
    }

    /**
     * Get employee leave balances.
     */
    public function balances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id)],
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $employee = $this->employeeService->find((int) $validated['employee_id']);

        // A query-string year arrives as a string; the integer rule only checks its form.
        $year = isset($validated['year']) ? (int) $validated['year'] : null;

        return $this->success($this->leaveService->getAllBalances($employee, $year));
    }

    /**
     * Initialize leave balances for a year.
     */
    public function initializeBalances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $count = $this->leaveService->initializeYearBalances($validated['year'], auth()->user()->organization_id);

        return $this->success(null, "Initialized leave balances for {$count} employee-leave type combinations.");
    }

    /**
     * Get leave summary.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->leaveService->getOrganizationSummary();

        return $this->success($summary);
    }
}
