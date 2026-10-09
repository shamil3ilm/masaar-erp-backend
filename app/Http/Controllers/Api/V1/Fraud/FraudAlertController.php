<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fraud;

use App\Http\Controllers\Controller;
use App\Services\Fraud\FraudReviewService;
use App\Services\Fraud\FraudRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FraudAlertController extends Controller
{
    public function __construct(
        private readonly FraudReviewService $reviews,
        private readonly FraudRuleService $rules,
    ) {}

    // -------------------------------------------------------------------------
    // Alerts
    // -------------------------------------------------------------------------

    /**
     * Paginated list of fraud alerts for the authenticated organization.
     * Filterable by status, severity, entity_type and creation date.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->reviews->paginateAlerts(
            Auth::user()->organization_id,
            $this->filledFilters($request, ['status', 'severity', 'entity_type', 'from_date', 'to_date']),
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Show a single fraud alert with full evidence.
     */
    public function show(int $id): JsonResponse
    {
        return $this->success($this->reviews->findAlert(Auth::user()->organization_id, $id));
    }

    /**
     * Update the status of a fraud alert (reviewing / resolved / false_positive).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'  => 'required|in:reviewing,resolved,false_positive',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $alert = $this->reviews->review(
            Auth::user()->organization_id,
            $id,
            $validated['status'],
            $validated['notes'] ?? null,
            Auth::id(),
        );

        return $this->success($alert, 'Alert status updated.');
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    /**
     * List all fraud rules for the organization.
     */
    public function rules(Request $request): JsonResponse
    {
        $filters = $this->filledFilters($request, ['rule_type', 'entity_type']);
        $filters['active_only'] = $request->boolean('active_only');

        return $this->paginated($this->rules->paginateRules(
            Auth::user()->organization_id,
            $filters,
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Create a new fraud rule.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:200',
            'rule_type'    => 'required|in:velocity,amount,geographic,behavioral,pattern',
            'entity_type'  => 'required|in:invoice,payment,login,contact',
            'conditions'   => 'required|array',
            'severity'     => 'required|in:low,medium,high,critical',
            'is_active'    => 'boolean',
            // Refused while nothing honours it.
            //
            // FraudRuleEngine computes shouldBlock from this and puts it in
            // its EvaluationResult, and the only thing that reads it is a log
            // field in RunFraudChecksJob. Nothing blocks.
            //
            // It cannot, as the checks are arranged: the job is dispatched
            // afterCommit() with tries = 1, so it runs once the invoice,
            // payment or login it would block has already committed. A
            // reviewer ticking this box would be told the transaction is
            // stopped, and it would not be - which is worse than not offering
            // the control. All six shipped templates set it false, so nothing
            // relies on it today.
            //
            // Honouring it needs a decision rather than a patch: evaluate
            // blocking rules synchronously before the commit (latency, and
            // the fraud engine can then fail a sale), or redefine it as a
            // post-hoc hold on the entity, which is a different control with
            // a different name.
            // 'sometimes' because declined is an implicit rule: without it the
            // rule runs on an absent field and fails, which would refuse every
            // request that simply does not mention auto_block.
            'auto_block'   => 'sometimes|boolean|declined',
            'score_impact' => 'integer|min:1|max:100',
        ], [
            'auto_block.declined' => 'auto_block cannot be enabled: fraud rules are '
                .'evaluated after the transaction commits, so nothing can be blocked. '
                .'Use a high severity to raise an alert for review instead.',
        ]);

        $rule = $this->rules->create($validated, Auth::user()->organization_id, Auth::id());

        return $this->created($rule, 'Fraud rule created.');
    }

    /**
     * Toggle a fraud rule on/off.
     */
    public function toggleRule(int $id): JsonResponse
    {
        $rule = $this->rules->toggle(Auth::user()->organization_id, $id);

        $state = $rule->is_active ? 'enabled' : 'disabled';

        return $this->success($rule, "Fraud rule {$state}.");
    }

    /**
     * Seed default fraud rule templates into the organization.
     */
    public function seedDefaults(): JsonResponse
    {
        $created = $this->rules->seedDefaults(Auth::user()->organization_id, Auth::id());

        return $this->success(['created' => $created], "{$created} default rules seeded.");
    }

    /**
     * The given query parameters the request fills, by name.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function filledFilters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        return $filters;
    }
}
