<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Services\HR\EmployeeService;
use App\Services\HR\HRReportService;
use App\Services\HR\StatutoryDeductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HRReportsController extends Controller
{
    public function __construct(
        protected HRReportService $reportService,
        protected StatutoryDeductionService $statutoryService,
        protected EmployeeService $employeeService,
    ) {}

    /**
     * Get HR Dashboard Summary.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->reportService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->reportService->getDashboardSummary();

        return $this->success($data);
    }

    /**
     * Get Headcount Report.
     */
    public function headcount(Request $request): JsonResponse
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'department_id' => $this->departmentRule($request),
        ]);

        $user = $request->user();

        $this->reportService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->reportService->generateHeadcountReport(
            $request->get('as_of_date', now()->toDateString()),
            $this->departmentId($request)
        );

        return $this->success($data);
    }

    /**
     * Get Turnover Report.
     */
    public function turnover(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $this->reportService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->reportService->generateTurnoverReport(
            $request->get('start_date'),
            $request->get('end_date')
        );

        return $this->success($data);
    }

    /**
     * Get Attendance Report.
     */
    public function attendance(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department_id' => $this->departmentRule($request),
        ]);

        $user = $request->user();

        $this->reportService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->reportService->generateAttendanceReport(
            $request->get('start_date'),
            $request->get('end_date'),
            $this->departmentId($request)
        );

        return $this->success($data);
    }

    /**
     * Get Leave Analysis Report.
     */
    public function leaveAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department_id' => $this->departmentRule($request),
        ]);

        $user = $request->user();

        $this->reportService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->reportService->generateLeaveReport(
            $request->get('start_date'),
            $request->get('end_date'),
            $this->departmentId($request)
        );

        return $this->success($data);
    }

    /**
     * Get Payroll Summary Report.
     */
    public function payrollSummary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $this->reportService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->reportService->generatePayrollReport(
            $request->get('start_date'),
            $request->get('end_date')
        );

        return $this->success($data);
    }

    /**
     * Get Statutory Deduction Configuration.
     */
    public function statutoryConfig(Request $request): JsonResponse
    {
        $user = $request->user();
        $countryCode = $request->get('country_code', $user->organization->country_code);

        $config = $this->statutoryService->getConfiguration($countryCode);

        $response = [
            'country_code' => $countryCode,
            'schemes' => $config,
        ];

        // Add India-specific data
        if ($countryCode === 'IN') {
            $response['professional_tax_slabs'] = [
                'states' => ['MH', 'KA', 'TN', 'GJ', 'WB', 'AP', 'TS', 'KL'],
                'slabs' => $this->statutoryService->getIndiaPtSlabs($request->get('state', 'MH')),
            ];
            $response['income_tax_slabs'] = [
                'new_regime' => $this->statutoryService->getIndiaTaxSlabs('new'),
                'old_regime' => $this->statutoryService->getIndiaTaxSlabs('old'),
            ];
        }

        return $this->success($response);
    }

    /**
     * Calculate statutory deductions preview.
     */
    public function calculateStatutory(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            'country_code' => 'nullable|string|size:2',
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('organization_id', $user->organization_id)],
            'state' => 'nullable|string', // For India PT
            'tax_regime' => 'nullable|string|in:new,old', // For India TDS
        ]);

        $countryCode = $request->get('country_code', $user->organization->country_code);
        $grossSalary = (float) $request->get('gross_salary');

        // Use the named employee's data when one is given
        $employee = $request->filled('employee_id')
            ? $this->employeeService->find($request->integer('employee_id'))
            : null;

        // Create mock employee if not provided
        if (!$employee) {
            $employee = new Employee([
                'organization_id' => $user->organization_id,
                'nationality' => $countryCode,
                'work_state' => $request->get('state', 'MH'),
                'tax_regime' => $request->get('tax_regime', 'new'),
            ]);
            $employee->organization = $user->organization;
        }

        $deductions = $this->statutoryService->calculateDeductions($employee, $grossSalary, $countryCode);

        return $this->success([
            'gross_salary' => $grossSalary,
            'country_code' => $countryCode,
            ...$deductions,
            'net_after_statutory' => $grossSalary - $deductions['total_employee'],
        ]);
    }

    /**
     * Generate statutory compliance report.
     */
    public function statutoryCompliance(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $data = $this->statutoryService->generateComplianceReport(
            $user->organization_id,
            $request->get('start_date'),
            $request->get('end_date'),
            $user->organization->country_code
        );

        return $this->success($data);
    }

    /**
     * @return list<mixed>
     */
    private function departmentRule(Request $request): array
    {
        return ['nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $request->user()->organization_id)];
    }

    /**
     * The department filter as the report service takes it: the query string
     * carries it as text.
     */
    private function departmentId(Request $request): ?int
    {
        return $request->filled('department_id') ? $request->integer('department_id') : null;
    }
}
