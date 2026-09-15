<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\SupportsAgGrid;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\CostAllocation;
use App\Models\Accounting\CostCenter;
use App\Services\Accounting\CostCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostCenterController extends Controller
{
    use SupportsAgGrid;
    use ValidatesOwnedRows;
    public function __construct(
        private readonly CostCenterService $service
    ) {}

    // ================================================================
    // Cost Centers
    // ================================================================

    /**
     * List cost centers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status'     => $request->filled('status') ? $request->status : null,
            'search'     => $request->filled('search') ? $request->search : null,
            'parent_id'  => $request->filled('parent_id') ? $request->integer('parent_id') : null,
            'roots_only' => $request->boolean('roots_only'),
        ];

        if ($this->isAgGridRequest($request)) {
            return $this->applyAgGrid($this->service->costCenterQuery($filters), $request);
        }

        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->listCostCenters($filters, $perPage));
    }

    /**
     * Create a new cost center.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:50'],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'parent_id'       => ['nullable', 'integer', $this->ownedBy('cost_centers')],
            'manager_id'      => ['nullable', 'integer', $this->ownedBy('employees')],
            'department_id'   => ['nullable', 'integer', $this->ownedBy('departments')],
            'status'          => ['nullable', Rule::in([CostCenter::STATUS_ACTIVE, CostCenter::STATUS_INACTIVE])],
            'valid_from'      => ['nullable', 'date'],
            'valid_to'        => ['nullable', 'date', 'after_or_equal:valid_from'],
            'gl_account_id'   => ['nullable', 'integer', $this->ownedBy('chart_of_accounts')],
            'is_statistical'  => ['nullable', 'boolean'],
        ]);

        $costCenter = $this->service->createCostCenter(
            array_merge($validated, ['organization_id' => $orgId]),
            $request->user()->id
        );

        return $this->created($costCenter->load(['parent:id,code,name', 'manager:id,first_name,last_name']));
    }

    /**
     * Show a single cost center.
     */
    public function show(CostCenter $costCenter): JsonResponse
    {
        $costCenter->load([
            'parent:id,code,name',
            'children:id,code,name,status',
            'manager:id,first_name,last_name',
            'department:id,name',
            'glAccount:id,code,name',
        ]);

        return $this->success($costCenter);
    }

    /**
     * Update a cost center.
     */
    public function update(Request $request, CostCenter $costCenter): JsonResponse
    {
        $validated = $request->validate([
            'code'            => ['sometimes', 'required', 'string', 'max:50'],
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'parent_id'       => ['nullable', 'integer', $this->ownedBy('cost_centers')],
            'manager_id'      => ['nullable', 'integer', $this->ownedBy('employees')],
            'department_id'   => ['nullable', 'integer', $this->ownedBy('departments')],
            'status'          => ['nullable', Rule::in([CostCenter::STATUS_ACTIVE, CostCenter::STATUS_INACTIVE])],
            'valid_from'      => ['nullable', 'date'],
            'valid_to'        => ['nullable', 'date', 'after_or_equal:valid_from'],
            'gl_account_id'   => ['nullable', 'integer', $this->ownedBy('chart_of_accounts')],
            'is_statistical'  => ['nullable', 'boolean'],
        ]);

        $updated = $this->service->updateCostCenter($costCenter, $validated, $request->user()->id);

        return $this->success($updated->load(['parent:id,code,name', 'manager:id,first_name,last_name']));
    }

    /**
     * Soft-delete a cost center.
     */
    public function destroy(CostCenter $costCenter): JsonResponse
    {
        $costCenter->delete();

        return $this->success(['message' => 'Cost center deleted.']);
    }

    /**
     * Deactivate a cost center without deleting it.
     */
    public function deactivate(Request $request, CostCenter $costCenter): JsonResponse
    {
        $updated = $this->service->deactivate($costCenter, $request->user()->id);

        return $this->success($updated);
    }

    // ================================================================
    // Assignments
    // ================================================================

    /**
     * Assign an employee to this cost center.
     *
     * POST /controlling/cost-centers/{costCenter}/assign
     */
    public function assign(Request $request, CostCenter $costCenter): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'      => ['required', 'integer', $this->ownedBy('employees')],
            'profit_center_id' => ['nullable', 'integer', $this->ownedBy('profit_centers')],
            'split_percent'    => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'effective_from'   => ['required', 'date'],
            'effective_to'     => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $employee = $this->service->findEmployee((int) $validated['employee_id']);

        $assignment = $this->service->assignEmployee(
            employee:       $employee,
            costCenterId:   $costCenter->id,
            profitCenterId: $validated['profit_center_id'] ?? null,
            splitPercent:   (float) ($validated['split_percent'] ?? 100.0),
            effectiveFrom:  $validated['effective_from'],
            userId:         $request->user()->id
        );

        return $this->created($assignment->load(['costCenter:id,code,name', 'profitCenter:id,code,name']));
    }

    // ================================================================
    // Report
    // ================================================================

    /**
     * Cost center financial report for a given date range.
     *
     * GET /controlling/cost-centers/{costCenter}/report?from=&to=
     */
    public function report(Request $request, CostCenter $costCenter): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $orgId = $this->organizationId($request);

        $data = $this->service->getCostCenterReport(
            $orgId,
            $request->from,
            $request->to,
            $costCenter->id
        );

        return $this->success([
            'cost_center' => $costCenter->only(['id', 'code', 'name']),
            'period'      => ['from' => $request->from, 'to' => $request->to],
            'lines'       => $data,
        ]);
    }

    /**
     * Aggregated report across ALL cost centers.
     *
     * GET /controlling/cost-centers/report?from=&to=
     */
    public function reportAll(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $orgId = $this->organizationId($request);

        $data = $this->service->getCostCenterReport($orgId, $request->from, $request->to);

        return $this->success([
            'period' => ['from' => $request->from, 'to' => $request->to],
            'lines'  => $data,
        ]);
    }

    // ================================================================
    // Plan vs Actual Report
    // ================================================================

    /**
     * Plan vs actual report for a cost center (by fiscal year and optional period).
     *
     * GET /controlling/cost-centers/{costCenter}/plan-vs-actual?fiscal_year=2025&period=3
     */
    public function planVsActual(Request $request, CostCenter $costCenter): JsonResponse
    {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period'      => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $report = $this->service->getCostCenterPlanVsActual(
            $costCenter,
            (int) $request->fiscal_year,
            $request->filled('period') ? (int) $request->period : null
        );

        return $this->success($report);
    }

    // ================================================================
    // Period Planning
    // ================================================================

    /**
     * Set (upsert) a period plan line for a cost center.
     *
     * POST /controlling/cost-centers/{costCenter}/plan
     */
    public function setPlan(Request $request, CostCenter $costCenter): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'period'          => ['required', 'integer', 'min:1', 'max:12'],
            'cost_element_id' => ['required', 'integer', $this->ownedBy('cost_elements')],
            'amount'          => ['required', 'numeric', 'min:0'],
        ]);

        return $this->tryAction(
            fn() => $this->service->setPeriodPlan(
                $costCenter,
                (int) $validated['fiscal_year'],
                (int) $validated['period'],
                (int) $validated['cost_element_id'],
                (float) $validated['amount']
            ),
            'Success',
        );
    }

    /**
     * Get the full 12-period plan matrix for a cost center / fiscal year.
     *
     * GET /controlling/cost-centers/{costCenter}/plan?fiscal_year=2025
     */
    public function getPlan(Request $request, CostCenter $costCenter): JsonResponse
    {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $plan = $this->service->getPeriodPlan($costCenter, (int) $request->fiscal_year);

        return $this->success($plan);
    }

    // ================================================================
    // Allocations
    // ================================================================

    /**
     * List allocations (optionally filtered by cost center).
     *
     * GET /controlling/allocations
     */
    public function allocations(Request $request): JsonResponse
    {
        $filters = [
            'status'              => $request->filled('status') ? $request->status : null,
            'from_cost_center_id' => $request->filled('from_cost_center_id') ? $request->integer('from_cost_center_id') : null,
            'to_cost_center_id'   => $request->filled('to_cost_center_id') ? $request->integer('to_cost_center_id') : null,
        ];

        return $this->paginated(
            $this->service->listAllocations($filters, $request->integer('per_page', 20))
        );
    }

    /**
     * Create a new allocation.
     *
     * POST /controlling/allocations
     */
    public function storeAllocation(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'fiscal_year_id'      => ['nullable', 'integer', $this->ownedBy('fiscal_years')],
            'period_start'        => ['required', 'date'],
            'period_end'          => ['required', 'date', 'after_or_equal:period_start'],
            'from_cost_center_id' => ['required', 'integer', $this->ownedBy('cost_centers')],
            'to_cost_center_id'   => ['required', 'integer', $this->ownedBy('cost_centers'), 'different:from_cost_center_id'],
            'allocation_method'   => ['nullable', Rule::in([CostAllocation::METHOD_FIXED, CostAllocation::METHOD_PERCENTAGE, CostAllocation::METHOD_ACTIVITY])],
            'allocation_percent'  => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'allocation_amount'   => ['nullable', 'numeric', 'min:0.0001'],
            'description'         => ['nullable', 'string', 'max:500'],
        ]);

        $allocation = $this->service->createAllocation(
            array_merge($validated, ['organization_id' => $orgId]),
            $request->user()->id
        );

        return $this->created($allocation->load(['fromCostCenter:id,code,name', 'toCostCenter:id,code,name']));
    }

    /**
     * Show a single allocation.
     *
     * GET /controlling/allocations/{allocation}
     */
    public function showAllocation(CostAllocation $allocation): JsonResponse
    {
        $allocation->load([
            'fromCostCenter:id,code,name',
            'toCostCenter:id,code,name',
            'fiscalYear:id,name',
            'journalEntry:id,entry_number,status',
            'createdBy:id,name',
        ]);

        return $this->success($allocation);
    }

    /**
     * Post an allocation — creates the journal entry.
     *
     * POST /controlling/allocations/{allocation}/post
     */
    public function postAllocation(Request $request, CostAllocation $allocation): JsonResponse
    {
        $posted = $this->service->postAllocation($allocation, $request->user()->id);

        return $this->success($posted);
    }

    /**
     * GET /controlling/cost-centers/hierarchy-tree
     *
     * Returns the full cost-center standard hierarchy as a nested tree,
     * equivalent to SAP CO standard hierarchy (transaction OKEON).
     */
    public function hierarchyTree(Request $request): JsonResponse
    {
        $tree = $this->service->hierarchyTree($request->user()->organization_id);

        return $this->success($tree, 'Cost center standard hierarchy retrieved');
    }
}
