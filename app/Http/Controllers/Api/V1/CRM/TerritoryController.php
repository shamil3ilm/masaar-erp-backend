<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\CRM\TerritoryAssignment;
use App\Services\CRM\TerritoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private TerritoryService $territoryService
    ) {}

    // -------------------------------------------------------------------------
    // Territories CRUD
    // -------------------------------------------------------------------------

    /**
     * List territories for the authenticated organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'territory_type', 'parent_id', 'country_code']);
        $filters['roots_only'] = $request->boolean('roots_only');

        return $this->paginated($this->territoryService->paginateTerritories(
            $request->user()->organization_id,
            $filters,
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Create a territory.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('territories')],
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'territory_type' => 'sometimes|in:global,region,country,state,city,postal_zone,custom',
            'country_code' => 'nullable|string|max:3',
            'state_code' => 'nullable|string|max:10',
            'postal_codes' => 'nullable|array',
            'postal_codes.*' => 'string|max:20',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;

        $territory = $this->territoryService->createTerritory($validated, $request->user()->id);

        return $this->created($territory->load('parent'));
    }

    /**
     * Show a territory.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->territoryService->findTerritoryDetail($request->user()->organization_id, $id));
    }

    /**
     * Update a territory.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $territory = $this->territoryService->findTerritory($request->user()->organization_id, $id);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->ownedBy('territories')],
            'name' => 'sometimes|string|max:200',
            'code' => 'sometimes|string|max:50',
            'description' => 'nullable|string',
            'territory_type' => 'sometimes|in:global,region,country,state,city,postal_zone,custom',
            'country_code' => 'nullable|string|max:3',
            'state_code' => 'nullable|string|max:10',
            'postal_codes' => 'nullable|array',
            'postal_codes.*' => 'string|max:20',
            'status' => 'sometimes|in:active,inactive',
        ]);

        return $this->success($this->territoryService->updateTerritory($territory, $validated));
    }

    /**
     * Soft-delete a territory.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->territoryService->deleteTerritory(
            $this->territoryService->findTerritory($request->user()->organization_id, $id)
        );

        return $this->success([], 'Territory deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Assignments
    // -------------------------------------------------------------------------

    /**
     * List assignments for a territory.
     */
    public function assignmentIndex(Request $request, int $territoryId): JsonResponse
    {
        $territory = $this->territoryService->findTerritory($request->user()->organization_id, $territoryId);

        return $this->paginated($this->territoryService->paginateAssignments(
            $territory,
            $request->boolean('active_only'),
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Add an assignment to a territory.
     */
    public function assignmentStore(Request $request, int $territoryId): JsonResponse
    {
        $territory = $this->territoryService->findTerritory($request->user()->organization_id, $territoryId);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', $this->ownedBy('employees')],
            'role' => 'sometimes|in:owner,backup,viewer',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $assignment = $this->territoryService->assignEmployee(
            territory: $territory,
            employeeId: $validated['employee_id'],
            role: $validated['role'] ?? TerritoryAssignment::ROLE_OWNER,
            effectiveFrom: $validated['effective_from'],
            userId: $request->user()->id,
            effectiveTo: $validated['effective_to'] ?? null,
        );

        return $this->created($assignment);
    }

    /**
     * Remove an assignment (expire it as of yesterday).
     */
    public function assignmentDestroy(Request $request, int $assignmentId): JsonResponse
    {
        $assignment = $this->territoryService->findAssignment($request->user()->organization_id, $assignmentId);
        $this->territoryService->removeAssignment($assignment, $request->user()->id);

        return $this->success([], 'Assignment removed successfully.');
    }

    // -------------------------------------------------------------------------
    // Routing Rules
    // -------------------------------------------------------------------------

    /**
     * List routing rules.
     */
    public function routingRuleIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['entity_type', 'territory_id']);
        $filters['active_only'] = $request->boolean('active_only');

        return $this->paginated($this->territoryService->paginateRoutingRules(
            $request->user()->organization_id,
            $filters,
            $request->integer('per_page', 25)
        ));
    }

    /**
     * Create a routing rule.
     */
    public function routingRuleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'territory_id' => ['required', 'integer', $this->ownedBy('territories')],
            'entity_type' => 'sometimes|in:lead,opportunity,contact',
            'match_field' => 'required|in:country,state,postal_code,city,custom',
            'match_value' => 'required|string|max:200',
            'priority' => 'sometimes|integer|min:1|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;

        $rule = $this->territoryService->createRoutingRule($validated, $request->user()->id);

        return $this->created($rule->load('territory'));
    }

    /**
     * Update a routing rule.
     */
    public function routingRuleUpdate(Request $request, int $id): JsonResponse
    {
        $rule = $this->territoryService->findRoutingRule($request->user()->organization_id, $id);

        $validated = $request->validate([
            'territory_id' => ['sometimes', 'integer', $this->ownedBy('territories')],
            'entity_type' => 'sometimes|in:lead,opportunity,contact',
            'match_field' => 'sometimes|in:country,state,postal_code,city,custom',
            'match_value' => 'sometimes|string|max:200',
            'priority' => 'sometimes|integer|min:1|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success($this->territoryService->updateRoutingRule($rule, $validated));
    }

    /**
     * Delete a routing rule.
     */
    public function routingRuleDestroy(Request $request, int $id): JsonResponse
    {
        $this->territoryService->deleteRoutingRule(
            $this->territoryService->findRoutingRule($request->user()->organization_id, $id)
        );

        return $this->success([], 'Routing rule deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Auto-assign a lead to a territory owner based on routing rules.
     */
    public function autoAssignLead(Request $request, int $leadId): JsonResponse
    {
        $lead = $this->territoryService->findLead($request->user()->organization_id, $leadId);
        $assignment = $this->territoryService->autoAssignLead($lead, $request->user()->id);

        if ($assignment === null) {
            return $this->success(null, 'No matching territory or owner found for this lead.');
        }

        return $this->success($assignment, 'Lead auto-assigned successfully.');
    }

    /**
     * Get performance metrics for a territory over a date range.
     */
    public function performance(Request $request, int $id): JsonResponse
    {
        $territory = $this->territoryService->findTerritory($request->user()->organization_id, $id);

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $performance = $this->territoryService->getTerritoryPerformance(
            $territory,
            $validated['from'],
            $validated['to'],
        );

        return $this->success($performance);
    }

    /**
     * Get per-salesperson workload summary for the organisation.
     */
    public function teamWorkload(Request $request): JsonResponse
    {
        $workload = $this->territoryService->getTeamWorkload($request->user()->organization_id);

        return $this->success($workload);
    }
}
