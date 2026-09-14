<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Models\HR\EmployeeLoan;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\Payslip;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * What an employee may read about themselves and their workplace.
 *
 * Every personal record is looked up through the Employee passed in and
 * constrained to that employee's id, so an id taken from a request can only
 * ever reach the caller's own payslip, leave request, loan or document.
 */
class EmployeeSelfServiceService
{
    public function __construct(
        private LeaveService $leaveService,
        private StatutoryDeductionService $statutoryService,
    ) {}

    public function attendance(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->get();
    }

    public function leaveBalances(Employee $employee, int $year): Collection
    {
        return LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get();
    }

    /**
     * Days requested and not yet decided, so not yet taken from the balance,
     * keyed by leave type id.
     */
    public function pendingLeaveDays(Employee $employee, int $year): SupportCollection
    {
        return LeaveRequest::where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereYear('from_date', $year)
            ->get(['leave_type_id', 'total_days'])
            ->groupBy('leave_type_id')
            ->map(fn ($requests) => $requests->sum('total_days'));
    }

    /**
     * @param  array{status?: mixed, year?: mixed}  $filters  a filter applies whenever its key is present
     */
    public function leaveRequests(Employee $employee, array $filters, int $perPage): LengthAwarePaginator
    {
        return LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('created_at')
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('year', $filters), fn ($q) => $q->whereYear('from_date', $filters['year']))
            ->paginate($perPage);
    }

    /**
     * Withdraws one of the employee's own requests that is not decided yet.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the request is not the employee's
     * @throws \InvalidArgumentException when the request is already decided
     */
    public function withdrawLeaveRequest(Employee $employee, int $id, string $reason): LeaveRequest
    {
        $request = LeaveRequest::where('employee_id', $employee->id)->findOrFail($id);

        return $this->leaveService->withdraw($request, $reason);
    }

    /**
     * @param  mixed  $year  filters by the year the payslip was created when not null
     */
    public function payslips(Employee $employee, mixed $year, int $perPage): LengthAwarePaginator
    {
        return Payslip::where('employee_id', $employee->id)
            ->with('payrollPeriod')
            ->orderByDesc('created_at')
            ->when($year !== null, fn ($q) => $q->whereYear('created_at', $year))
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $relations
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the payslip is not the employee's
     */
    public function payslip(Employee $employee, int $id, array $relations): Payslip
    {
        return Payslip::where('employee_id', $employee->id)
            ->with($relations)
            ->findOrFail($id);
    }

    /**
     * The employee's salary with its earnings, deductions and the statutory
     * deductions it attracts, or null when no salary is assigned.
     *
     * @return array<string, mixed>|null
     */
    public function salaryBreakdown(Employee $employee): ?array
    {
        $salary = $employee->currentSalary;

        if (! $salary) {
            return null;
        }

        $salary->load('components.salaryComponent');

        $grossSalary = $salary->gross_salary;
        $deductions = $salary->getDeductions();

        $statutory = $this->statutoryService->calculateDeductions(
            $employee,
            $grossSalary,
            $employee->organization->country_code
        );

        return [
            'gross_salary' => $grossSalary,
            'currency' => $salary->currency_code,
            'earnings' => $salary->getEarnings()->map(fn ($c) => [
                'name' => $c->salaryComponent->name,
                'amount' => $c->amount,
                'is_taxable' => $c->salaryComponent->is_taxable,
            ]),
            'deductions' => $deductions->map(fn ($c) => [
                'name' => $c->salaryComponent->name,
                'amount' => $c->amount,
            ]),
            'statutory_deductions' => $statutory['employee_deductions'],
            'employer_contributions' => $statutory['employer_contributions'],
            'summary' => [
                'total_earnings' => $grossSalary,
                'total_deductions' => $deductions->sum('amount') + $statutory['total_employee'],
                'total_statutory' => $statutory['total_employee'],
                'net_salary' => $grossSalary - $deductions->sum('amount') - $statutory['total_employee'],
            ],
        ];
    }

    public function loans(Employee $employee): Collection
    {
        return EmployeeLoan::where('employee_id', $employee->id)
            ->with('repayments')
            ->orderByDesc('created_at')
            ->get();
    }

    public function documents(Employee $employee): Collection
    {
        return EmployeeDocument::where('employee_id', $employee->id)
            ->orderBy('document_type')
            ->get();
    }

    /**
     * Active employees of the organization with only the contact and
     * placement columns a colleague may see.
     *
     * @param  array{department_id?: mixed, search?: mixed}  $filters  a filter applies whenever its key is present
     */
    public function directory(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Employee::where('organization_id', $organizationId)
            ->where('employment_status', 'active')
            ->with(['department', 'designation', 'branch'])
            ->when(array_key_exists('department_id', $filters), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(array_key_exists('search', $filters), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->select([
                'id', 'employee_number', 'first_name', 'last_name',
                'email', 'phone', 'department_id', 'designation_id',
                'branch_id', 'profile_photo_path',
            ])
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    public function holidays(int $organizationId, mixed $year): Collection
    {
        return Holiday::where('organization_id', $organizationId)
            ->whereYear('holiday_date', $year)
            ->orderBy('holiday_date')
            ->get();
    }
}
