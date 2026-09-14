<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\LeaveType;
use App\Services\HR\LeavePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeavePolicyController extends Controller
{
    public function __construct(
        private LeavePolicyService $policyService
    ) {}

    /**
     * List leave policies.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->per_page ? (int) $request->per_page : null;

        $policies = $this->policyService->list($request->boolean('active_only'), $perPage);

        return $perPage === null ? $this->success($policies) : $this->paginated($policies);
    }

    /**
     * Create a new leave policy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'policy_year_type' => 'nullable|in:calendar,fiscal,anniversary',
            'year_start_date' => 'nullable|date',
            'allow_negative_balance' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'min_notice_days' => 'nullable|integer|min:0|max:255',
            'allow_half_day' => 'nullable|boolean',
            'allow_hourly' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $policy = $this->policyService->create($validated);

        return $this->created($policy);
    }

    /**
     * Show a leave policy.
     */
    public function show(LeavePolicy $leavePolicy): JsonResponse
    {
        return $this->success(
            $leavePolicy->load(['leaveTypes.leaveTiers.approvers'])
        );
    }

    /**
     * Update a leave policy.
     */
    public function update(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'policy_year_type' => 'nullable|in:calendar,fiscal,anniversary',
            'year_start_date' => 'nullable|date',
            'allow_negative_balance' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'min_notice_days' => 'nullable|integer|min:0|max:255',
            'allow_half_day' => 'nullable|boolean',
            'allow_hourly' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $policy = $this->policyService->update($leavePolicy, $validated);

        return $this->success($policy);
    }

    /**
     * Delete a leave policy.
     */
    public function destroy(LeavePolicy $leavePolicy): JsonResponse
    {
        $this->policyService->delete($leavePolicy);

        return $this->success(null, 'Leave policy deleted successfully.');
    }

    /**
     * List leave types for a policy.
     */
    public function leaveTypes(LeavePolicy $leavePolicy): JsonResponse
    {
        return $this->success($this->policyService->activeLeaveTypes($leavePolicy));
    }

    /**
     * Create a leave type within a policy.
     */
    public function storeLeaveType(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'annual_quota' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
            'is_encashable' => 'nullable|boolean',
            'carry_forward' => 'nullable|boolean',
            'max_carry_forward_days' => 'nullable|numeric|min:0',
            'requires_attachment' => 'nullable|boolean',
            'requires_reason' => 'nullable|boolean',
            'applicable_gender' => 'nullable|in:all,male,female',
            'employment_type_restriction' => 'nullable|string|max:50',
            'applicable_after_months' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|numeric|min:1',
            'min_days_per_request' => 'nullable|numeric|min:0.5',
            'max_days_per_request' => 'nullable|numeric|min:0.5',
            'allowed_days_of_week' => 'nullable|array',
            'allowed_days_of_week.*' => 'integer|between:0,6',
            'blackout_dates' => 'nullable|array',
            'blackout_dates.*' => 'date',
            'accrual_type' => 'nullable|in:annual,monthly,quarterly,none',
            'accrual_day' => 'nullable|integer|min:1|max:31',
            'count_holidays' => 'nullable|boolean',
            'count_weekends' => 'nullable|boolean',
        ]);

        $leaveType = $this->policyService->createLeaveType($leavePolicy, $validated, $this->organizationId($request));

        return $this->created($leaveType);
    }

    /**
     * Assign a tier to a leave type.
     */
    public function assignTier(Request $request, LeaveType $leaveType): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_service_months' => 'nullable|integer|min:0',
            'max_service_months' => 'nullable|integer|min:0',
            'employee_grade' => 'nullable|string',
            'department_id' => 'nullable|string',
            'entitled_days' => 'required|numeric|min:0',
            'entitlement_period' => 'nullable|in:yearly,monthly',
            'monthly_accrual_rate' => 'nullable|numeric|min:0',
            'max_carryforward_days' => 'nullable|integer|min:0',
            'carryforward_expiry_months' => 'nullable|integer|min:0',
            'max_encashable_days' => 'nullable|integer|min:0',
            'encashment_rate' => 'nullable|numeric|min:0|max:100',
            'priority' => 'nullable|integer|min:0',
            'approvers' => 'nullable|array',
            // An approver decides this organization's leave: its own user, or
            // one of its roles or a platform-wide one.
            'approvers.*.user_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'approvers.*.role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where(
                    fn ($q) => $q->where('organization_id', $organizationId)->orWhereNull('organization_id')
                ),
            ],
            'approvers.*.designation' => 'nullable|string',
            'approvers.*.approval_level' => 'nullable|integer|min:1',
            'approvers.*.can_approve' => 'nullable|boolean',
            'approvers.*.can_reject' => 'nullable|boolean',
            'approvers.*.is_final_approver' => 'nullable|boolean',
        ]);

        $tier = $this->policyService->assignTier($leaveType, $validated);

        return $this->created($tier);
    }

    /**
     * Get accrual schedule for a policy.
     */
    public function accrualSchedule(LeavePolicy $leavePolicy): JsonResponse
    {
        $schedule = $this->policyService->getAccrualSchedule($leavePolicy);

        return $this->success($schedule);
    }

    /**
     * Update a leave tier.
     */
    public function updateTier(Request $request, string $leaveTier): JsonResponse
    {
        $tier = $this->policyService->findTier($leaveTier);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'min_service_months' => 'nullable|integer|min:0',
            'max_service_months' => 'nullable|integer|min:0',
            'employee_grade' => 'nullable|string',
            'department_id' => 'nullable|string',
            'entitled_days' => 'sometimes|numeric|min:0',
            'entitlement_period' => 'nullable|in:yearly,monthly',
            'monthly_accrual_rate' => 'nullable|numeric|min:0',
            'max_carryforward_days' => 'nullable|integer|min:0',
            'carryforward_expiry_months' => 'nullable|integer|min:0',
            'max_encashable_days' => 'nullable|integer|min:0',
            'encashment_rate' => 'nullable|numeric|min:0|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->success($this->policyService->updateTier($tier, $validated));
    }

    /**
     * Delete a leave tier.
     */
    public function destroyTier(string $leaveTier): JsonResponse
    {
        $this->policyService->deleteTier($this->policyService->findTier($leaveTier));

        return $this->success(null, 'Leave tier deleted successfully.');
    }
}
