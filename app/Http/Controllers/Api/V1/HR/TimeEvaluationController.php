<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\TimeSheet;
use App\Models\HR\TimeWageType;
use App\Services\HR\TimeEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimeEvaluationController extends Controller
{
    public function __construct(
        private TimeEvaluationService $service
    ) {}

    // ---------------------------------------------------------------
    // Time Sheets — CRUD
    // ---------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listTimeSheets(
            $request->only(['employee_id', 'status', 'period_start', 'period_end']),
            $this->safeSortBy($request->sort_by, ['period_start', 'period_end', 'status', 'created_at'], 'period_start'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15)
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'employee_id'  => ['required', 'integer', Rule::exists('employees', 'id')->where('organization_id', $organizationId)],
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $validated['organization_id'] = $organizationId;
        $validated['created_by']      = auth()->id();

        try {
            $timeSheet = $this->service->createTimeSheet($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success($timeSheet->load(['employee', 'creator']), 'Time sheet created.', 201);
    }

    public function update(Request $request, TimeSheet $timeSheet): JsonResponse
    {
        if (!$timeSheet->isDraft()) {
            return $this->error('Only draft time sheets can be updated.', 'INVALID_STATE', 422);
        }

        $validated = $request->validate([
            'period_start' => 'sometimes|date',
            'period_end'   => 'sometimes|date|after_or_equal:period_start',
        ]);

        $timeSheet->update($validated);

        return $this->success($timeSheet->fresh(), 'Time sheet updated successfully.');
    }

    public function show(TimeSheet $timeSheet): JsonResponse
    {
        return $this->success(
            $timeSheet->load(['employee', 'creator', 'approver', 'entries.wageType', 'entries.costCenter'])
        );
    }

    public function destroy(TimeSheet $timeSheet): JsonResponse
    {
        if (!$timeSheet->isDraft()) {
            return $this->error('Only draft time sheets can be deleted.', 'INVALID_STATE', 422);
        }

        $timeSheet->delete();

        return $this->success(null, 'Time sheet deleted.');
    }

    // ---------------------------------------------------------------
    // Entries
    // ---------------------------------------------------------------

    public function addEntry(Request $request, TimeSheet $timeSheet): JsonResponse
    {
        $validated = $request->validate([
            'entry_date'     => 'required|date',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i',
            'hours'          => 'required|numeric|min:0.01|max:24',
            'entry_type'     => 'nullable|in:regular,overtime,absence,holiday,training',
            'wage_type_id'   => ['nullable', 'integer', Rule::exists('time_wage_types', 'id')->where('organization_id', $timeSheet->organization_id)],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('organization_id', $timeSheet->organization_id)],
            'work_order_id'  => 'nullable|integer',
            'activity_code'  => 'nullable|string|max:20',
            'notes'          => 'nullable|string|max:1000',
        ]);

        try {
            $entry = $this->service->addEntry($timeSheet, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success($entry->load(['wageType', 'costCenter']), 'Entry added.', 201);
    }

    // ---------------------------------------------------------------
    // Workflow actions
    // ---------------------------------------------------------------

    public function submit(TimeSheet $timeSheet): JsonResponse
    {
        return $this->tryAction(
            function () use ($timeSheet) {
                $this->service->submit($timeSheet);
                return $timeSheet->refresh();
            },
            'Time sheet submitted.',
            'INVALID_STATE'
        );
    }

    public function approve(TimeSheet $timeSheet): JsonResponse
    {
        return $this->tryAction(
            function () use ($timeSheet) {
                $this->service->approve($timeSheet);
                return $timeSheet->refresh();
            },
            'Time sheet approved.',
            'INVALID_STATE'
        );
    }

    public function reject(Request $request, TimeSheet $timeSheet): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return $this->tryAction(
            function () use ($timeSheet, $validated) {
                $this->service->reject($timeSheet, $validated['reason']);
                return $timeSheet->refresh();
            },
            'Time sheet rejected.',
            'INVALID_STATE'
        );
    }

    // ---------------------------------------------------------------
    // Evaluation
    // ---------------------------------------------------------------

    public function evaluate(TimeSheet $timeSheet): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->evaluate($timeSheet),
            'Evaluation completed.',
            'INVALID_STATE'
        );
    }

    public function transferToPayroll(TimeSheet $timeSheet): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->transferToPayroll($timeSheet),
            'Transferred to payroll.',
            'INVALID_STATE'
        );
    }

    public function costAllocation(TimeSheet $timeSheet): JsonResponse
    {
        $allocation = $this->service->generateCostAllocation($timeSheet);

        return $this->success($allocation, 'Cost allocation generated.');
    }

    // ---------------------------------------------------------------
    // Wage Types
    // ---------------------------------------------------------------

    public function wageTypes(Request $request): JsonResponse
    {
        return $this->success($this->service->activeWageTypes($request->category));
    }

    public function storeWageType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:10',
            'name'            => 'required|string|max:100',
            'wage_category'   => 'required|in:overtime,night_differential,weekend,holiday,absence_deduction,other',
            'rate_multiplier' => 'nullable|numeric|min:0|max:9.9999',
            'is_active'       => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $wageType = $this->service->createWageType($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DUPLICATE_CODE', 422);
        }

        return $this->success($wageType, 'Wage type created.', 201);
    }

    public function updateWageType(Request $request, TimeWageType $timeWageType): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:100',
            'wage_category'   => 'sometimes|required|in:overtime,night_differential,weekend,holiday,absence_deduction,other',
            'rate_multiplier' => 'sometimes|nullable|numeric|min:0|max:9.9999',
            'is_active'       => 'sometimes|boolean',
        ]);

        $timeWageType->update($validated);

        return $this->success($timeWageType->fresh(), 'Wage type updated.');
    }

    public function destroyWageType(TimeWageType $timeWageType): JsonResponse
    {
        if ($timeWageType->evaluationResults()->exists()) {
            return $this->error(
                'Cannot delete a wage type that has evaluation results.',
                'HAS_REFERENCES',
                422
            );
        }

        $timeWageType->delete();

        return $this->success(null, 'Wage type deleted.');
    }
}
