<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\HR\Leave\LeaveEncashment;
use App\Services\HR\LeaveAccrualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveAccrualController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private LeaveAccrualService $accrualService
    ) {}

    /**
     * Process accruals for the organization.
     */
    public function processAccruals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accrual_date' => 'nullable|date',
        ]);

        $organizationId = $this->organizationId($request);
        $processed = $this->accrualService->processAccruals(
            $organizationId,
            $validated['accrual_date'] ?? null
        );

        return $this->success(
            ['processed_count' => $processed],
            "Processed accruals for {$processed} employee-leave type combinations."
        );
    }

    /**
     * Get accrual history for a balance.
     */
    public function accrualHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'leave_type_id' => ['required', $this->ownedBy('leave_types')],
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $balance = $this->accrualService->getBalance(
            (int) $validated['employee_id'],
            (int) $validated['leave_type_id'],
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        if (!$balance) {
            return $this->notFound('Leave balance not found.');
        }

        return $this->success([
            'balance' => $balance,
            'accruals' => $this->accrualService->accrualsFor($balance),
        ]);
    }

    /**
     * Adjust an employee's leave balance.
     */
    public function adjustBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'leave_type_id' => ['required', $this->ownedBy('leave_types')],
            'adjustment_type' => 'required|in:add,deduct',
            'days' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'effective_date' => 'nullable|date',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by'] = auth()->id();
        $validated['approved_by'] = auth()->id();

        $adjustment = $this->accrualService->adjustBalance($validated);

        return $this->created($adjustment);
    }

    /**
     * List adjustments.
     */
    public function adjustments(Request $request): JsonResponse
    {
        $perPage = $request->per_page ? (int) $request->per_page : null;

        $adjustments = $this->accrualService->listAdjustments([
            'employee_id'   => $request->employee_id,
            'leave_type_id' => $request->leave_type_id,
        ], $perPage);

        return $perPage === null ? $this->success($adjustments) : $this->paginated($adjustments);
    }

    /**
     * Request leave encashment.
     */
    public function encashLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'leave_type_id' => ['required', $this->ownedBy('leave_types')],
            'requested_days' => 'required|numeric|min:0.5',
            'daily_rate' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by'] = auth()->id();

        $encashment = $this->accrualService->encashLeave($validated);

        return $this->created($encashment);
    }

    /**
     * List encashment requests.
     */
    public function encashments(Request $request): JsonResponse
    {
        $perPage = $request->per_page ? (int) $request->per_page : null;

        $encashments = $this->accrualService->listEncashments([
            'employee_id' => $request->employee_id,
            'status'      => $request->status,
        ], $perPage);

        return $perPage === null ? $this->success($encashments) : $this->paginated($encashments);
    }

    /**
     * Approve an encashment request.
     */
    public function approveEncashment(Request $request, LeaveEncashment $leaveEncashment): JsonResponse
    {
        $validated = $request->validate([
            'approved_days' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $encashment = $this->accrualService->approveEncashment(
                $leaveEncashment,
                isset($validated['approved_days']) ? (float) $validated['approved_days'] : null,
                $validated['notes'] ?? null,
                (int) auth()->id(),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->success($encashment);
    }

    /**
     * Get employee leave balance.
     */
    public function getBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'leave_type_id' => ['required', $this->ownedBy('leave_types')],
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $balance = $this->accrualService->getBalance(
            (int) $validated['employee_id'],
            (int) $validated['leave_type_id'],
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        if (!$balance) {
            return $this->notFound('Leave balance not found.');
        }

        return $this->success($balance);
    }
}
