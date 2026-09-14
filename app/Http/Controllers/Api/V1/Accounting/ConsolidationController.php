<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ConsolidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsolidationController extends Controller
{
    public function __construct(
        private ConsolidationService $consolidationService
    ) {}

    // =========================================================================
    // Consolidation Groups
    // =========================================================================

    /**
     * List consolidation groups.
     */
    public function indexGroups(Request $request): JsonResponse
    {
        $groups = $this->consolidationService->paginateGroups(
            $request->active === 'true',
            $request->integer('per_page', 15),
        );

        return $this->paginated($groups, null);
    }

    /**
     * Show a single consolidation group.
     */
    public function showGroup(int $id): JsonResponse
    {
        $group = $this->consolidationService->findGroupWithDetails($id);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        return $this->success($group, 'Consolidation group retrieved.');
    }

    /**
     * Create a new consolidation group.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'description'                   => 'nullable|string',
            'currency_code'                 => 'required|string|size:3',
            'entities'                      => 'nullable|array',
            'entities.*.entity_organization_id' => 'required_with:entities|exists:organizations,id',
            'entities.*.name'               => 'required_with:entities|string|max:255',
            'entities.*.ownership_percent'  => 'nullable|numeric|min:0|max:100',
            'entities.*.consolidation_method' => 'nullable|in:full,proportional,equity',
            'entities.*.local_currency'     => 'nullable|string|size:3',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $entities = $validated['entities'] ?? [];
        unset($validated['entities']);

        $group = $this->consolidationService->createGroup($validated, $entities, auth()->id());

        return $this->created($group, 'Consolidation group created.');
    }

    /**
     * Update a consolidation group.
     */
    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $group = $this->consolidationService->findGroup($id);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'currency_code' => 'sometimes|string|size:3',
            'is_active'     => 'sometimes|boolean',
        ]);

        $group = $this->consolidationService->updateGroup($group, $validated);

        return $this->success($group, 'Consolidation group updated.');
    }

    /**
     * Delete a consolidation group.
     */
    public function destroyGroup(int $id): JsonResponse
    {
        $group = $this->consolidationService->findGroup($id);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        try {
            $this->consolidationService->deleteGroup($group);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DELETE_BLOCKED', 422);
        }

        return $this->success(null, 'Consolidation group deleted.');
    }

    // =========================================================================
    // Consolidation Entities
    // =========================================================================

    /**
     * Add an entity to a consolidation group.
     */
    public function addEntity(Request $request, int $groupId): JsonResponse
    {
        $group = $this->consolidationService->findGroup($groupId);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        $validated = $request->validate([
            'entity_organization_id' => 'required|exists:organizations,id',
            'name'                   => 'required|string|max:255',
            'ownership_percent'      => 'nullable|numeric|min:0|max:100',
            'consolidation_method'   => 'nullable|in:full,proportional,equity',
            'local_currency'         => 'nullable|string|size:3',
        ]);

        $entity = $this->consolidationService->addEntity($group, $validated, auth()->id());

        return $this->created(
            $entity->load('entityOrganization'),
            'Entity added to consolidation group.'
        );
    }

    /**
     * Remove an entity from a consolidation group.
     */
    public function removeEntity(int $entityId): JsonResponse
    {
        $entity = $this->consolidationService->findEntity($entityId);

        if (!$entity) {
            return $this->notFound('Consolidation entity not found.');
        }

        $this->consolidationService->removeEntity($entity);

        return $this->success(null, 'Entity removed from consolidation group.');
    }

    // =========================================================================
    // Consolidation Periods
    // =========================================================================

    /**
     * List consolidation periods.
     */
    public function indexPeriods(Request $request): JsonResponse
    {
        $periods = $this->consolidationService->paginatePeriods(
            $request->group_id,
            $request->status,
            $request->integer('per_page', 15),
        );

        return $this->paginated($periods, null);
    }

    /**
     * Show a single consolidation period.
     */
    public function showPeriod(int $id): JsonResponse
    {
        $period = $this->consolidationService->findPeriodWithDetails($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        return $this->success($period, 'Consolidation period retrieved.');
    }

    /**
     * Create a consolidation period.
     *
     * The group and fiscal year must belong to the caller's organisation.
     */
    public function storePeriod(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'consolidation_group_id' => [
                'required',
                Rule::exists('consolidation_groups', 'id')->where('organization_id', $organizationId),
            ],
            'fiscal_year_id'         => [
                'nullable',
                Rule::exists('fiscal_years', 'id')->where('organization_id', $organizationId),
            ],
            'period_start'           => 'required|date',
            'period_end'             => 'required|date|after_or_equal:period_start',
        ]);

        $validated['organization_id'] = $organizationId;

        $period = $this->consolidationService->createPeriod($validated, auth()->id());

        return $this->created(
            $period->load('group:id,name,currency_code'),
            'Consolidation period created.'
        );
    }

    /**
     * Collect entity balances into the consolidation period.
     */
    public function collectBalances(Request $request, int $id): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        if ($period->isCompleted()) {
            return $this->error('Cannot collect balances for a completed period.', 'PERIOD_COMPLETED', 422);
        }

        $this->consolidationService->collectEntityBalances($period, auth()->id());

        return $this->success(
            [
                'period'             => $period->fresh(),
                'balances_collected' => $this->consolidationService->countCollectedBalances($period),
            ],
            'Entity balances collected successfully.'
        );
    }

    /**
     * Complete a consolidation period.
     */
    public function completePeriod(Request $request, int $id): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        return $this->tryAction(
            fn() => $this->consolidationService->completePeriod($period, auth()->id()),
            'Consolidation period completed.'
        );
    }

    /**
     * Get the consolidated financial report for a period.
     */
    public function report(int $id): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        $report = $this->consolidationService->generateConsolidatedReport($period);

        return $this->success($report, 'Consolidated report generated.');
    }

    // =========================================================================
    // Elimination Entries
    // =========================================================================

    /**
     * List elimination entries for a period.
     */
    public function indexEliminations(Request $request, int $periodId): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($periodId);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        $entries = $this->consolidationService->paginateEliminations(
            $period,
            $request->entry_type,
            $request->integer('per_page', 20),
        );

        return $this->paginated($entries, null);
    }

    /**
     * Auto-generate IC elimination entries for a consolidation period.
     *
     * POST /consolidation/periods/{id}/generate-eliminations
     */
    public function generateEliminations(int $id): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        if ($period->isCompleted()) {
            return $this->error('Cannot generate eliminations for a completed period.', 'PERIOD_COMPLETED', 422);
        }

        try {
            $entries = $this->consolidationService->generateEliminationEntries($period, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success([
            'generated' => count($entries),
            'entries'   => $entries,
        ], 'Elimination entries generated.');
    }

    /**
     * List auto-generated elimination entries for a period.
     *
     * GET /consolidation/periods/{id}/eliminations-auto
     */
    public function eliminationsAuto(int $id): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        $entries = $this->consolidationService->getEliminationEntries($period);

        return $this->success($entries->all(), 'Elimination entries retrieved.');
    }

    /**
     * Create an elimination entry for a consolidation period.
     */
    public function storeElimination(Request $request, int $periodId): JsonResponse
    {
        $period = $this->consolidationService->findPeriod($periodId);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        if ($period->isCompleted()) {
            return $this->error('Cannot add elimination entries to a completed period.', 'PERIOD_COMPLETED', 422);
        }

        $validated = $request->validate([
            'entry_type'       => 'required|in:intercompany_receivable,intercompany_payable,dividend,investment,other',
            'description'      => 'required|string|max:500',
            'debit_account_id' => 'required|exists:chart_of_accounts,id',
            'credit_account_id' => 'required|exists:chart_of_accounts,id|different:debit_account_id',
            'amount'           => 'required|numeric|min:0.0001',
            'currency_code'    => 'nullable|string|size:3',
        ]);

        $validated['organization_id']         = $this->organizationId($request);
        $validated['consolidation_period_id'] = $periodId;

        $entry = $this->consolidationService->createEliminationEntry($validated, auth()->id());

        return $this->created(
            $entry->load(['debitAccount:id,code,name', 'creditAccount:id,code,name']),
            'Elimination entry created.'
        );
    }
}
