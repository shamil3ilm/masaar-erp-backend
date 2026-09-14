<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Services\HR\AttendanceService;
use App\Services\HR\EmployeeSelfServiceService;
use App\Services\HR\LeaveService;
use App\Services\Print\PrintService;
use App\Traits\MasksSensitiveData;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Endpoints an employee uses about themselves. Each one starts from the
 * employee linked to the authenticated user and reads nothing else personal.
 */
class EmployeeSelfServiceController extends Controller
{
    use MasksSensitiveData;

    public function __construct(
        protected AttendanceService $attendanceService,
        protected LeaveService $leaveService,
        protected EmployeeSelfServiceService $selfService,
        protected PrintService $printService
    ) {}

    /**
     * Get current user's employee profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $employee->load([
            'department',
            'designation',
            'branch',
            'reportingManager',
            'currentSalary.components.salaryComponent',
        ]);

        return $this->success([
            'employee' => [...$employee->toArray(), ...$this->maskedNumbers($employee)],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * The employee's identity, tax and bank numbers, masked as EmployeeResource
     * masks them. The model hides them from serialization, and an employee
     * sees no more of their own numbers than HR does.
     *
     * @return array<string, string|null>
     */
    private function maskedNumbers(Employee $employee): array
    {
        return [
            'national_id' => $this->maskNationalId($employee->national_id),
            'passport_number' => $this->maskNationalId($employee->passport_number),
            'tax_number' => $this->maskTaxNumber($employee->tax_number),
            'social_security_number' => $this->maskTaxNumber($employee->social_security_number),
            'bank_account_number' => $this->maskBankAccount($employee->bank_account_number),
            'bank_iban' => $this->maskIban($employee->bank_iban),
        ];
    }

    /**
     * Get employee's attendance for current month.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $month = $request->get('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        return $this->success([
            'month' => $month,
            'records' => $this->selfService->attendance($employee, $startDate, $endDate),
            'summary' => $this->attendanceService->getEmployeeSummary($employee, $startDate, $endDate),
        ]);
    }

    /**
     * Check in for current day.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $request->validate([
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance = $this->attendanceService->checkIn(
            $employee,
            null,
            Attendance::SOURCE_MOBILE,
            $request->get('latitude') ? (float) $request->get('latitude') : null,
            $request->get('longitude') ? (float) $request->get('longitude') : null,
            $request->get('device_id')
        );

        return $this->success($attendance, 'Checked in successfully at '.$attendance->check_in->format('H:i'));
    }

    /**
     * Check out for current day.
     */
    public function checkOut(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $attendance = $this->attendanceService->checkOut(
            $employee,
            null,
            $request->get('latitude') ? (float) $request->get('latitude') : null,
            $request->get('longitude') ? (float) $request->get('longitude') : null
        );

        return $this->success($attendance, 'Checked out successfully at '.$attendance->check_out->format('H:i'));
    }

    /**
     * Get employee's leave balances.
     */
    public function myLeaveBalances(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $year = (int) $request->get('year', now()->year);

        $balances = $this->selfService->leaveBalances($employee, $year);
        $pending = $this->selfService->pendingLeaveDays($employee, $year);

        return $this->success($balances->map(fn ($b) => [
            'leave_type' => $b->leaveType->name,
            'leave_type_code' => $b->leaveType->code,
            'entitled' => (float) $b->entitled + (float) $b->accrued,
            'used' => (float) $b->taken,
            'pending' => (float) ($pending[$b->leave_type_id] ?? 0),
            'available' => $b->getAvailableBalance(),
            'carried_forward' => (float) $b->opening_balance,
        ]));
    }

    /**
     * Get employee's leave requests.
     */
    public function myLeaveRequests(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        return $this->paginated($this->selfService->leaveRequests(
            $employee,
            $request->only(['status', 'year']),
            $request->integer('per_page', 15)
        ));
    }

    /**
     * Submit a leave request.
     */
    public function submitLeaveRequest(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $validated = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('organization_id', $employee->organization_id)],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|string|in:first_half,second_half',
            'reason' => 'required|string|max:1000',
            'emergency_contact' => 'nullable|string|max:100',
        ]);

        // Map to service expected keys
        $validated['from_date'] = $validated['start_date'];
        $validated['to_date'] = $validated['end_date'];
        $validated['is_half_day'] = $validated['half_day'] ?? false;
        unset($validated['start_date'], $validated['end_date'], $validated['half_day']);

        try {
            $leaveRequest = $this->leaveService->createRequest($employee, $validated);
        } catch (\InvalidArgumentException $e) {
            // The leave type does not apply, the balance is short or the dates overlap.
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('An unexpected error occurred. Please try again.');
        }

        return $this->created($leaveRequest->load('leaveType'), 'Leave request submitted successfully');
    }

    /**
     * Cancel one of the employee's own leave requests that is not decided yet.
     */
    public function cancelLeaveRequest(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        try {
            $this->selfService->withdrawLeaveRequest($employee, $id, (string) $request->get('reason', ''));
        } catch (\InvalidArgumentException) {
            return $this->error('Cannot cancel this request.', 'INVALID_STATUS', 400);
        }

        return $this->success(null, 'Leave request cancelled.');
    }

    /**
     * Get employee's payslips.
     */
    public function myPayslips(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        return $this->paginated($this->selfService->payslips(
            $employee,
            $request->has('year') ? $request->get('year') : null,
            $request->integer('per_page', 12)
        ));
    }

    /**
     * Get single payslip details.
     */
    public function showPayslip(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        return $this->success($this->selfService->payslip(
            $employee,
            $id,
            ['items.salaryComponent', 'payrollPeriod', 'employee.department', 'employee.designation']
        ));
    }

    /**
     * Download payslip PDF.
     */
    public function downloadPayslip(Request $request, int $id): Response|JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $payslip = $this->selfService->payslip(
            $employee,
            $id,
            ['items.salaryComponent', 'payrollPeriod', 'employee.department', 'employee.designation', 'employee.organization']
        );

        $pdf = $this->printService->generatePdf('payslip', $payslip, 'a4');

        $filename = "payslip-{$payslip->payslip_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Get salary breakdown with statutory deductions preview.
     */
    public function salaryBreakdown(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        $breakdown = $this->selfService->salaryBreakdown($employee);

        if ($breakdown === null) {
            return $this->notFound('No salary structure assigned.');
        }

        return $this->success($breakdown);
    }

    /**
     * Get employee's loans.
     *
     * The loan has no total or outstanding column: the total is what the
     * instalments add up to, principal and interest, and the outstanding
     * amount is the balance kept on the loan.
     */
    public function myLoans(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        return $this->success($this->selfService->loans($employee)->map(fn ($loan) => [
            'id' => $loan->id,
            'loan_type' => $loan->loan_type,
            'principal_amount' => $loan->principal_amount,
            'interest_rate' => $loan->interest_rate,
            'total_amount' => round((float) $loan->emi_amount * $loan->tenure_months, 4),
            'emi_amount' => $loan->emi_amount,
            'tenure_months' => $loan->tenure_months,
            'disbursement_date' => $loan->disbursement_date,
            'total_paid' => $loan->repayments->where('status', 'paid')->sum('total_amount'),
            'outstanding' => $loan->balance,
            'status' => $loan->status,
            'repayments' => $loan->repayments->map(fn ($r) => [
                'due_date' => $r->due_date,
                'amount' => $r->total_amount,
                'status' => $r->status,
                'paid_date' => $r->paid_date,
            ]),
        ]));
    }

    /**
     * Get employee's documents.
     */
    public function myDocuments(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->noEmployee();
        }

        return $this->success($this->selfService->documents($employee)->map(fn ($doc) => [
            'id' => $doc->id,
            'document_type' => $doc->document_type,
            'document_number' => $doc->document_number,
            'issue_date' => $doc->issue_date,
            'expiry_date' => $doc->expiry_date,
            'is_expired' => $doc->expiry_date ? $doc->expiry_date->isPast() : false,
            'days_to_expiry' => $doc->expiry_date ? now()->diffInDays($doc->expiry_date, false) : null,
        ]));
    }

    /**
     * Get employee directory (colleagues).
     */
    public function directory(Request $request): JsonResponse
    {
        return $this->paginated($this->selfService->directory(
            $request->user()->organization_id,
            $request->only(['department_id', 'search']),
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Get organization holidays.
     */
    public function holidays(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        return $this->success([
            'year' => $year,
            'holidays' => $this->selfService->holidays($request->user()->organization_id, $year),
        ]);
    }

    private function noEmployee(): JsonResponse
    {
        return $this->notFound('No employee record found.');
    }
}
