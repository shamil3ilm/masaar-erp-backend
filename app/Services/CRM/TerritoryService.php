<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Territory;
use App\Models\CRM\TerritoryAssignment;
use App\Models\CRM\TerritoryRoutingRule;
use App\Models\HR\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TerritoryService
{
    // -------------------------------------------------------------------------
    // Territories
    // -------------------------------------------------------------------------

    /**
     * The organization's territories, newest first. A filter applies when its
     * key is present, even with an empty value.
     *
     * @param  array{status?: mixed, territory_type?: mixed, parent_id?: mixed, country_code?: mixed, roots_only?: bool}  $filters
     */
    public function paginateTerritories(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Territory::with(['parent', 'creator'])
            ->where('organization_id', $organizationId)
            ->latest()
            ->when(array_key_exists('status', $filters), fn ($query) => $query->where('status', $filters['status']))
            ->when(array_key_exists('territory_type', $filters), fn ($query) => $query->ofType($filters['territory_type']))
            ->when(array_key_exists('parent_id', $filters), fn ($query) => $query->where('parent_id', (int) $filters['parent_id']))
            ->when($filters['roots_only'] ?? false, fn ($query) => $query->roots())
            ->when(array_key_exists('country_code', $filters), fn ($query) => $query->forCountry($filters['country_code']))
            ->paginate($perPage);
    }

    /**
     * A territory of the organization.
     *
     * @param  list<string>  $with
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findTerritory(int $organizationId, int $territoryId, array $with = []): Territory
    {
        return Territory::with($with)
            ->where('organization_id', $organizationId)
            ->findOrFail($territoryId);
    }

    /**
     * A territory with its hierarchy, assignments and routing rules.
     */
    public function findTerritoryDetail(int $organizationId, int $territoryId): Territory
    {
        return $this->findTerritory($organizationId, $territoryId, [
            'parent',
            'children',
            'assignments.'.$this->employeeReference(),
            'routingRules',
            'creator',
        ]);
    }

    /**
     * Create a new territory.
     */
    public function createTerritory(array $data, int $userId): Territory
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;
            return Territory::create($data);
        });
    }

    public function updateTerritory(Territory $territory, array $data): Territory
    {
        $territory->update($data);

        return $territory->refresh()->load('parent');
    }

    public function deleteTerritory(Territory $territory): void
    {
        $territory->delete();
    }

    // -------------------------------------------------------------------------
    // Assignments
    // -------------------------------------------------------------------------

    /**
     * The territory's assignments, newest first.
     */
    public function paginateAssignments(Territory $territory, bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return $territory->assignments()
            ->with($this->employeeReference())
            ->latest()
            ->when($activeOnly, fn ($query) => $query->active())
            ->paginate($perPage);
    }

    /**
     * Assign an employee to a territory with a given role.
     */
    public function assignEmployee(
        Territory $territory,
        int $employeeId,
        string $role,
        string $effectiveFrom,
        int $userId,
        ?string $effectiveTo = null,
    ): TerritoryAssignment {
        return DB::transaction(function () use ($territory, $employeeId, $role, $effectiveFrom, $userId, $effectiveTo) {
            return TerritoryAssignment::create([
                'organization_id' => $territory->organization_id,
                'territory_id'    => $territory->id,
                'employee_id'     => $employeeId,
                'role'            => $role,
                'effective_from'  => $effectiveFrom,
                'effective_to'    => $effectiveTo,
                'created_by'      => $userId,
            ])->load($this->employeeReference());
        });
    }

    /**
     * An assignment of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findAssignment(int $organizationId, int $assignmentId): TerritoryAssignment
    {
        return TerritoryAssignment::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($assignmentId);
    }

    /**
     * Remove (expire) a territory assignment immediately.
     */
    public function removeAssignment(TerritoryAssignment $assignment, int $userId): void
    {
        DB::transaction(function () use ($assignment) {
            $assignment->effective_to = now()->subDay()->toDateString();
            $assignment->save();
        });
    }

    // -------------------------------------------------------------------------
    // Routing rules
    // -------------------------------------------------------------------------

    /**
     * The organization's routing rules, highest priority first.
     *
     * @param  array{entity_type?: mixed, territory_id?: mixed, active_only?: bool}  $filters
     */
    public function paginateRoutingRules(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return TerritoryRoutingRule::with('territory')
            ->where('organization_id', $organizationId)
            ->orderBy('priority')
            ->when(array_key_exists('entity_type', $filters), fn ($query) => $query->forEntityType($filters['entity_type']))
            ->when(array_key_exists('territory_id', $filters), fn ($query) => $query->where('territory_id', (int) $filters['territory_id']))
            ->when($filters['active_only'] ?? false, fn ($query) => $query->active())
            ->paginate($perPage);
    }

    /**
     * Create a territory routing rule.
     */
    public function createRoutingRule(array $data, int $userId): TerritoryRoutingRule
    {
        return DB::transaction(function () use ($data, $userId) {
            return TerritoryRoutingRule::create(array_merge($data, ['created_by' => $userId]));
        });
    }

    /**
     * A routing rule of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findRoutingRule(int $organizationId, int $ruleId): TerritoryRoutingRule
    {
        return TerritoryRoutingRule::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($ruleId);
    }

    public function updateRoutingRule(TerritoryRoutingRule $rule, array $data): TerritoryRoutingRule
    {
        $rule->update($data);

        return $rule->load('territory');
    }

    public function deleteRoutingRule(TerritoryRoutingRule $rule): void
    {
        $rule->delete();
    }

    /**
     * Match an entity (lead/contact/opportunity) against routing rules
     * and return the first matching territory.
     */
    public function routeEntity(string $entityType, array $entityData): ?Territory
    {
        // Always scope to the authenticated user's organisation — never trust
        // caller-supplied organization_id for security.
        $orgId = auth()->user()->organization_id;

        $rulesQuery = TerritoryRoutingRule::active()
            ->forEntityType($entityType)
            ->byPriority()
            ->where('organization_id', $orgId)
            ->with('territory');

        $rules = $rulesQuery->get();

        foreach ($rules as $rule) {
            if ($rule->matchesEntity($entityData) && $rule->territory?->isActive()) {
                return $rule->territory;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * A lead of the organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findLead(int $organizationId, int $leadId): Lead
    {
        return Lead::query()->where('organization_id', $organizationId)->findOrFail($leadId);
    }

    /**
     * Auto-assign a lead to a territory based on routing rules,
     * then assign the territory owner as the lead's handler.
     */
    public function autoAssignLead(Lead $lead, int $userId): ?TerritoryAssignment
    {
        $entityData = [
            'organization_id' => $lead->organization_id,
            'country_code'    => $lead->country_code,
            'state'           => $lead->state,
            'postal_code'     => $lead->postal_code,
            'city'            => $lead->city,
        ];

        $territory = $this->routeEntity(TerritoryRoutingRule::ENTITY_LEAD, $entityData);

        if ($territory === null) {
            return null;
        }

        return DB::transaction(function () use ($territory, $lead, $userId) {
            // Re-fetch with a row lock to prevent TOCTOU race conditions
            $territory = Territory::lockForUpdate()->findOrFail($territory->id);
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);

            $owner = $territory->getOwner();

            if ($owner === null) {
                return null;
            }

            // Update lead assignment
            $lead->assigned_to = $owner->user_id;
            $lead->save();

            // Return the territory assignment record for the owner
            return TerritoryAssignment::where('organization_id', $territory->organization_id)
                ->where('territory_id', $territory->id)
                ->where('employee_id', $owner->id)
                ->where('role', TerritoryAssignment::ROLE_OWNER)
                ->active()
                ->with(['territory', $this->employeeReference()])
                ->latest('effective_from')
                ->first();
        });
    }

    /**
     * Aggregate performance data for a territory over a date range.
     */
    public function getTerritoryPerformance(Territory $territory, string $from, string $to): array
    {
        $orgId = $territory->organization_id;

        // Find employees assigned to this territory during the period
        $employeeIds = TerritoryAssignment::where('territory_id', $territory->id)
            ->where('effective_from', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
            })
            ->pluck('employee_id');

        // Map employees → users
        $userIds = Employee::whereIn('id', $employeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        // Leads created by assigned employees in the period
        $leads = Lead::where('organization_id', $orgId)
            ->whereIn('assigned_to', $userIds)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        // Opportunities
        $opportunities = Opportunity::where('organization_id', $orgId)
            ->whereIn('assigned_to', $userIds)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        $wonDeals        = $opportunities->where('status', Opportunity::STATUS_WON);
        $pipelineValue   = (string) ($opportunities->where('status', Opportunity::STATUS_OPEN)->sum('amount') ?? 0);
        $wonValue        = (string) ($wonDeals->sum('amount') ?? 0);

        return [
            'territory'      => [
                'id'   => $territory->id,
                'name' => $territory->name,
                'code' => $territory->code,
            ],
            'period'         => ['from' => $from, 'to' => $to],
            'leads'          => [
                'total'     => $leads->count(),
                'converted' => $leads->where('status', Lead::STATUS_CONVERTED)->count(),
                'lost'      => $leads->where('status', Lead::STATUS_LOST)->count(),
            ],
            'opportunities'  => [
                'total'          => $opportunities->count(),
                'won'            => $wonDeals->count(),
                'won_value'      => $wonValue,
                'pipeline_value' => $pipelineValue,
            ],
            'assigned_employees' => $employeeIds->count(),
        ];
    }

    /**
     * Return per-salesperson territory and pipeline workload for the organisation.
     *
     * Leads and opportunities are counted and summed per user in two grouped
     * queries, so the query count does not grow with the size of the team.
     */
    public function getTeamWorkload(int $orgId): array
    {
        $assignments = TerritoryAssignment::where('organization_id', $orgId)
            ->active()
            ->with(['employee:id,user_id,first_name,last_name', 'territory'])
            ->get()
            ->groupBy('employee_id');

        $userIds = $assignments
            ->map(fn ($employeeAssignments) => $employeeAssignments->first()->employee?->user_id)
            ->filter()
            ->unique()
            ->values();

        $openLeads = Lead::where('organization_id', $orgId)
            ->whereIn('assigned_to', $userIds)
            ->open()
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as lead_count')
            ->pluck('lead_count', 'assigned_to');

        $opportunityTotals = Opportunity::where('organization_id', $orgId)
            ->whereIn('assigned_to', $userIds)
            ->groupBy('assigned_to', 'status')
            ->selectRaw('assigned_to, status, COUNT(*) as opportunity_count, SUM(amount) as amount_total')
            ->get()
            ->groupBy('assigned_to');

        $result = [];

        foreach ($assignments as $employeeId => $employeeAssignments) {
            $employee = $employeeAssignments->first()->employee;

            if ($employee === null || $employee->user_id === null) {
                continue;
            }

            $userId      = $employee->user_id;
            $territories = $employeeAssignments->pluck('territory')->filter();
            $byStatus    = ($opportunityTotals->get($userId) ?? collect())->keyBy('status');

            $open = $byStatus->get(Opportunity::STATUS_OPEN);
            $wonOpportunities = $byStatus->get(Opportunity::STATUS_WON)?->amount_total ?? 0;
            $totalPipeline = $byStatus->reduce(
                fn (string $sum, $row) => bcadd($sum, (string) ($row->amount_total ?? 0), 4),
                '0'
            );

            $quotaAttainment = bccomp((string) $totalPipeline, '0', 4) > 0
                ? bcmul(bcdiv((string) $wonOpportunities, (string) $totalPipeline, 6), '100', 4)
                : '0.0000';

            $result[] = [
                'employee_id'        => $employeeId,
                'employee_name'      => trim($employee->first_name . ' ' . $employee->last_name),
                'territories'        => $territories->map(fn($t) => [
                    'id'   => $t->id,
                    'name' => $t->name,
                    'code' => $t->code,
                ])->values(),
                'open_leads'         => (int) ($openLeads->get($userId) ?? 0),
                'open_opportunities' => (int) ($open?->opportunity_count ?? 0),
                'pipeline_value'     => (float) ($open?->amount_total ?? 0),
                'quota_attainment'   => $quotaAttainment,
            ];
        }

        // Sort by most open leads descending
        usort($result, fn($a, $b) => $b['open_leads'] <=> $a['open_leads']);

        return $result;
    }

    /**
     * The employee relation limited to reference columns: the full employee
     * carries decrypted identity and bank numbers.
     */
    private function employeeReference(): string
    {
        return 'employee:'.implode(',', Employee::REFERENCE_COLUMNS);
    }
}
