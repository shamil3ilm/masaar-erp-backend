<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Http\Controllers\Controller;
use App\Models\Automation\AutomationRule;
use App\Services\Automation\AutomationRuleRunner;
use App\Services\Automation\AutomationRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationRuleController extends Controller
{
    public function __construct(
        private AutomationRuleService $ruleService,
        private AutomationRuleRunner $runner
    ) {}

    /**
     * List automation rules with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $rules = $this->ruleService->paginate(
            $request->user()->organization_id,
            $request->only(['trigger_type', 'entity_type', 'is_active', 'trigger_event', 'search']),
            $this->safeSortBy($request->sort_by, ['name', 'priority', 'created_at', 'updated_at', 'is_active'], 'priority'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            (int) ($request->per_page ?? 15)
        );

        return $this->paginated($rules);
    }

    /**
     * Store a new automation rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:event,schedule,manual',
            'trigger_event' => 'nullable|required_if:trigger_type,event|string|max:255',
            'trigger_schedule' => 'nullable|required_if:trigger_type,schedule|string|max:255',
            'entity_type' => 'required|string|max:50',
            'conditions' => 'required|array',
            'actions' => 'required|array',
            'actions.*.type' => 'required|string',
            'priority' => 'nullable|integer|min:0',
            'stop_on_match' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $rule = $this->ruleService->create($validated, $request->user()->id);

        return $this->created($rule->load('creator'));
    }

    /**
     * Show a specific automation rule.
     */
    public function show(AutomationRule $automationRule): JsonResponse
    {
        return $this->success(
            $automationRule->load(['creator', 'schedules' => function ($q) {
                $q->orderBy('scheduled_for', 'desc')->limit(5);
            }])
        );
    }

    /**
     * Update an automation rule.
     */
    public function update(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'sometimes|in:event,schedule,manual',
            'trigger_event' => 'nullable|string|max:255',
            'trigger_schedule' => 'nullable|string|max:255',
            'entity_type' => 'sometimes|string|max:50',
            'conditions' => 'sometimes|array',
            'actions' => 'sometimes|array',
            'actions.*.type' => 'required_with:actions|string',
            'priority' => 'nullable|integer|min:0',
            'stop_on_match' => 'nullable|boolean',
        ]);

        $rule = $this->ruleService->update($automationRule, $validated);

        return $this->success($rule->load('creator'), 'Automation rule updated successfully.');
    }

    /**
     * Delete an automation rule.
     */
    public function destroy(AutomationRule $automationRule): JsonResponse
    {
        $this->ruleService->delete($automationRule);

        return $this->success(null, 'Automation rule deleted successfully.');
    }

    /**
     * Activate or deactivate an automation rule.
     * PATCH /automation-rules/{id}/active  {"active": true|false}
     */
    public function setActive(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $activate = $request->boolean('active');

        if ($activate && $automationRule->isActive()) {
            return $this->error('Rule is already active.', 'ALREADY_ACTIVE', 422);
        }

        if (!$activate && !$automationRule->isActive()) {
            return $this->error('Rule is already inactive.', 'ALREADY_INACTIVE', 422);
        }

        $rule = $activate
            ? $this->ruleService->activate($automationRule)
            : $this->ruleService->deactivate($automationRule);

        return $this->success(
            $rule,
            $activate ? 'Automation rule activated successfully.' : 'Automation rule deactivated successfully.'
        );
    }

    /**
     * Test an automation rule (dry-run without executing actions).
     */
    public function test(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'nullable|string|max:255',
            'entity_id' => 'nullable|integer',
            'sample_data' => 'nullable|array',
        ]);

        $entityType = $validated['entity_type'] ?? null;
        $entityId = $validated['entity_id'] ?? null;

        // If entity_type and entity_id are provided, resolve and evaluate against the real entity
        if ($entityType && $entityId) {
            $entityClass = $this->runner->entityClassFor($entityType);

            if (!$entityClass || !class_exists($entityClass)) {
                return $this->error('Invalid entity type.', 'INVALID_ENTITY_TYPE', 422);
            }

            $entity = $this->runner->findEntity($request->user()->organization_id, $entityClass, (int) $entityId);

            if (!$entity) {
                return $this->notFound('Entity not found.');
            }

            $conditionsMet = $this->runner->evaluate($automationRule, $entity);

            return $this->success([
                'rule_id' => $automationRule->uuid,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'conditions_met' => $conditionsMet,
                'actions_would_execute' => $conditionsMet ? $automationRule->actions : [],
            ], 'Test completed successfully.');
        }

        // Dry-run with sample data only (no real entity lookup)
        $sampleData = $validated['sample_data'] ?? [];

        return $this->success([
            'rule_id' => $automationRule->uuid,
            'sample_data' => $sampleData,
            'conditions' => $automationRule->conditions,
            'actions_would_execute' => $automationRule->actions,
        ], 'Test completed successfully.');
    }

    /**
     * Get execution logs for an automation rule.
     */
    public function logs(Request $request, AutomationRule $automationRule): JsonResponse
    {
        return $this->paginated($this->ruleService->paginateLogs(
            $automationRule,
            $request->status,
            $request->days ? (int) $request->days : null,
            (int) ($request->per_page ?? 15)
        ));
    }
}
