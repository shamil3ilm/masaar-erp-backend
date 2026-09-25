<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\HR\EmployeeService;
use App\Services\HR\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ShiftService $shiftService,
        private EmployeeService $employeeService,
    ) {}

    /**
     * List shifts for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $shifts = $this->shiftService->list(
            auth()->user()->organization_id,
            $request->boolean('active_only', false),
            $request->integer('per_page', 20)
        );

        return $this->paginated($shifts);
    }

    /**
     * Create a new shift definition.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shift_code' => 'required|string|max:20',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'integer|min:0|max:480',
            'is_overnight' => 'boolean',
            'is_flexible' => 'boolean',
            'flexible_start_window_minutes' => 'nullable|integer|min:0|max:240',
            'overtime_eligible' => 'boolean',
        ]);

        $shift = $this->shiftService->create($validated, auth()->user()->organization_id);

        return $this->created($shift, 'Shift created successfully.');
    }

    /**
     * Update an existing shift.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $shift = $this->shiftService->findForOrganization(auth()->user()->organization_id, $id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'shift_code' => 'string|max:20',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i',
            'break_minutes' => 'integer|min:0|max:480',
            'is_overnight' => 'boolean',
            'is_flexible' => 'boolean',
            'flexible_start_window_minutes' => 'nullable|integer|min:0|max:240',
            'overtime_eligible' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return $this->success($this->shiftService->update($shift, $validated), 'Shift updated successfully.');
    }

    /**
     * Delete a shift (only if not actively assigned).
     */
    public function destroy(int $id): JsonResponse
    {
        $shift = $this->shiftService->findForOrganization(auth()->user()->organization_id, $id);

        try {
            $this->shiftService->delete($shift);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'SHIFT_IN_USE', 422);
        }

        return $this->noContent();
    }

    /**
     * Assign a shift to an employee.
     */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'shift_id' => ['required', $this->ownedBy('shifts')],
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $employee = $this->employeeService->find((int) $validated['employee_id']);
        $shift = $this->shiftService->find((int) $validated['shift_id']);

        try {
            $assignment = $this->shiftService->assignShift($employee, $shift, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($assignment->load(['employee', 'shift']), 'Shift assigned successfully.');
    }
}
