<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\PayrollCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PayrollCorrectionController extends Controller
{
    public function __construct(
        private readonly PayrollCorrectionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->service->list($request->only([
            'status', 'employee_id', 'original_period_id', 'per_page',
        ]));

        return $this->paginated($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'                  => ['required', 'integer', $this->inOrganization('employees')],
            'original_payroll_period_id'   => ['required', 'integer', $this->inOrganization('payroll_periods')],
            'correction_payroll_period_id' => ['nullable', 'integer', $this->inOrganization('payroll_periods')],
            'correction_type'              => 'required|in:salary_change,component_adjustment,tax_correction,deduction_adjustment',
            'original_amount'              => 'required|numeric',
            'corrected_amount'             => 'required|numeric',
            'reason'                       => 'nullable|string',
        ]);

        $correction = $this->service->create($validated);

        return $this->created($correction->load(['employee', 'originalPeriod']), 'Payroll correction created.');
    }

    public function show(string $id): JsonResponse
    {
        return $this->success($this->service->findWithRelations($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $correction = $this->service->find($id);

        // Answered before validation so a correction that is no longer a draft
        // is reported as such whatever the body holds; the service checks again
        // on the locked row.
        if (! $correction->isDraft()) {
            return $this->error('Only draft corrections can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'correction_payroll_period_id' => ['nullable', 'integer', $this->inOrganization('payroll_periods')],
            'correction_type'              => 'sometimes|in:salary_change,component_adjustment,tax_correction,deduction_adjustment',
            'original_amount'              => 'sometimes|numeric',
            'corrected_amount'             => 'sometimes|numeric',
            'reason'                       => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => $this->service->update($correction, $validated)->fresh(['employee', 'originalPeriod']),
            'Payroll correction updated.',
            'INVALID_STATUS'
        );
    }

    public function approve(string $id): JsonResponse
    {
        $correction = $this->service->find($id);

        return $this->tryAction(
            fn() => $this->service->approve($correction, auth()->id())->load(['employee', 'approver']),
            'Payroll correction approved.',
            'INVALID_STATUS'
        );
    }

    public function post(string $id): JsonResponse
    {
        $correction = $this->service->find($id);

        return $this->tryAction(
            fn() => $this->service->post($correction)->load(['employee', 'originalPeriod']),
            'Payroll correction posted.',
            'INVALID_STATUS'
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $correction = $this->service->find($id);

        return $this->tryAction(
            fn() => $this->service->cancel($correction),
            'Payroll correction cancelled.',
            'INVALID_STATUS'
        );
    }

    /** An id that must belong to a row of the caller's organization. */
    private function inOrganization(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('organization_id', auth()->user()->organization_id);
    }
}
