<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\HR\PayrollPeriodResource;
use App\Http\Resources\HR\PayslipResource;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use App\Services\HR\GosiExportService;
use App\Services\HR\PayrollService;
use App\Services\HR\WpsExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService    $payrollService,
        private WpsExportService  $wpsExportService,
        private GosiExportService $gosiExportService,
    ) {
    }

    /**
     * List payroll periods.
     */
    public function periods(Request $request): JsonResponse
    {
        $periods = $this->payrollService->listPeriods(
            ['status' => $request->status, 'year' => $request->year],
            $request->integer('per_page', 15)
        );

        return $this->paginated($periods, PayrollPeriodResource::class);
    }

    /**
     * Create a payroll period.
     */
    public function createPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:50',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after:start_date',
            'payment_date' => 'nullable|date|after_or_equal:end_date',
        ]);

        $period = $this->payrollService->createPeriod($validated);

        return $this->created(new PayrollPeriodResource($period), 'Payroll period created successfully.');
    }

    /**
     * Show a payroll period with payslips.
     */
    public function showPeriod(PayrollPeriod $payrollPeriod): JsonResponse
    {
        return $this->success(new PayrollPeriodResource(
            $payrollPeriod->load(['payslips.employee'])
        ));
    }

    /**
     * Generate payslips for a period.
     */
    public function generatePayslips(PayrollPeriod $payrollPeriod): JsonResponse
    {
        try {
            $count = $this->payrollService->generatePayslips($payrollPeriod, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(
            new PayrollPeriodResource($payrollPeriod->fresh()),
            "Generated {$count} payslips."
        );
    }

    /**
     * Get period summary.
     */
    public function periodSummary(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $summary = $this->payrollService->getPeriodSummary($payrollPeriod);

        return $this->success($summary);
    }

    /**
     * Close a payroll period.
     */
    public function closePeriod(PayrollPeriod $payrollPeriod): JsonResponse
    {
        return $this->tryAction(
            fn() => new PayrollPeriodResource($this->payrollService->closePeriod($payrollPeriod, auth()->id())),
            'Payroll period closed successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * List payslips.
     */
    public function payslips(Request $request): JsonResponse
    {
        $payslips = $this->payrollService->listPayslips(
            [
                'period_id'   => $request->period_id,
                'employee_id' => $request->employee_id,
                'status'      => $request->status,
            ],
            $request->integer('per_page', 15)
        );

        return $this->paginated($payslips, PayslipResource::class);
    }

    /**
     * Show a specific payslip.
     */
    public function showPayslip(Payslip $payslip): JsonResponse
    {
        return $this->success(new PayslipResource(
            $payslip->load(['employee', 'payrollPeriod', 'items.salaryComponent', 'employeeSalary'])
        ));
    }

    /**
     * Generate single employee payslip.
     */
    public function generateSinglePayslip(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
        ]);

        try {
            $payslip = $this->payrollService->generatePayslipForEmployee($payrollPeriod, (int) $validated['employee_id']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new PayslipResource($payslip), 'Payslip generated successfully.');
    }

    /**
     * Submit payslip for approval.
     */
    public function submitPayslip(Payslip $payslip): JsonResponse
    {
        return $this->tryAction(
            fn() => new PayslipResource($this->payrollService->submitPayslip($payslip, auth()->id())),
            'Payslip submitted for approval.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Approve a payslip.
     */
    public function approvePayslip(Payslip $payslip): JsonResponse
    {
        return $this->tryAction(
            fn() => new PayslipResource($this->payrollService->approvePayslip($payslip, auth()->id())),
            'Payslip approved successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Mark payslip as paid.
     */
    public function markAsPaid(Request $request, Payslip $payslip): JsonResponse
    {
        $validated = $request->validate([
            'payment_mode'      => 'required|string|max:20',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        return $this->tryAction(
            fn() => new PayslipResource($this->payrollService->markAsPaid($payslip, $validated['payment_mode'], $validated['payment_reference'] ?? null)),
            'Payslip marked as paid.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Bulk approve payslips.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payslip_ids'   => 'required|array',
            'payslip_ids.*' => [
                Rule::exists('payslips', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
        ]);

        $count = $this->payrollService->bulkApprove(
            $validated['payslip_ids'],
            auth()->user()->organization_id,
            auth()->id()
        );

        return $this->success(null, "Approved {$count} payslips.");
    }

    /**
     * Bulk mark payslips as paid.
     */
    public function bulkPay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payslip_ids'       => 'required|array',
            'payslip_ids.*'     => [
                Rule::exists('payslips', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
            'payment_mode'      => 'required|string|max:20',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $count = $this->payrollService->bulkPay(
            $validated['payslip_ids'],
            auth()->user()->organization_id,
            $validated['payment_mode'],
            $validated['payment_reference'] ?? null
        );

        return $this->success(null, "Paid {$count} payslips.");
    }

    // -------------------------------------------------------------------------
    // WPS Export
    // -------------------------------------------------------------------------

    /**
     * Download the WPS SIF file for a payroll period.
     *
     * Query parameter:
     *   - bank_code (required, string, 9 chars): employer bank routing code.
     *
     * Returns a raw text/plain file download (not a JSON envelope) when
     * the period has paid payslips; returns a JSON error otherwise.
     */
    public function wpsExport(Request $request, PayrollPeriod $payrollPeriod): StreamedResponse|JsonResponse
    {
        $request->validate([
            'bank_code' => 'required|string|max:9',
        ]);

        $bankCode = (string) $request->input('bank_code');

        $stats = $this->wpsExportService->getStats($payrollPeriod);

        if ($stats['ready_count'] === 0) {
            return $this->error(
                'No paid payslips with a valid IBAN found for this period. Use wps-validate to review issues.',
                'WPS_NO_ELIGIBLE_RECORDS',
                422
            );
        }

        return $this->wpsExportService->download($payrollPeriod, $bankCode);
    }

    /**
     * Validate WPS readiness for a payroll period.
     *
     * Returns summary stats and a list of employees with missing/invalid data.
     */
    public function wpsValidate(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $stats = $this->wpsExportService->getStats($payrollPeriod);

        $warnings = $stats['missing_iban'] > 0
            ? $this->wpsExportService->missingIbanEmployees($payrollPeriod)
            : [];

        return $this->success([
            'valid'           => $stats['missing_iban'] === 0 && $stats['ready_count'] > 0,
            'total_employees' => $stats['total_employees'],
            'ready_count'     => $stats['ready_count'],
            'missing_iban'    => $stats['missing_iban'],
            'total_amount'    => $stats['total_amount'],
            'currency'        => $stats['currency'],
            'warnings'        => $warnings,
        ], 'WPS validation complete.');
    }

    // -------------------------------------------------------------------------
    // GOSI Export
    // -------------------------------------------------------------------------

    /**
     * Download the GOSI contribution CSV file for a payroll period.
     *
     * Returns a CSV file download (not a JSON envelope) when eligible
     * GOSI contribution records exist; returns a JSON error otherwise.
     */
    public function gosiExport(Request $request, PayrollPeriod $payrollPeriod): StreamedResponse|JsonResponse
    {
        $validation = $this->gosiExportService->validate($payrollPeriod);

        if ($validation['ready_count'] === 0) {
            return $this->error(
                'No GOSI contribution records found for this period. Use gosi-validate to review issues.',
                'GOSI_NO_ELIGIBLE_RECORDS',
                422
            );
        }

        return $this->gosiExportService->download($payrollPeriod);
    }

    /**
     * Validate GOSI readiness for a payroll period.
     *
     * Returns a list of employees with missing required fields (national ID,
     * employee number, salary data) or no contribution record for the period.
     */
    public function gosiValidate(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $validation = $this->gosiExportService->validate($payrollPeriod);

        return $this->success($validation, 'GOSI validation complete.');
    }
}
