<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\ConsolidatedBalance;
use App\Models\Accounting\ConsolidationEntity;
use App\Models\Accounting\ConsolidationGroup;
use App\Models\Accounting\ConsolidationPeriod;
use App\Models\Accounting\CopaLineItem;
use App\Models\Accounting\EliminationEntry;
use App\Models\Accounting\ExchangeRate;
use App\Models\Accounting\InterCompanyTransfer;
use App\Models\Core\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsolidationService
{
    public function __construct(
        private AccountBalanceService $accountBalanceService
    ) {}

    // -------------------------------------------------------------------------
    // Lookups and listings
    // -------------------------------------------------------------------------

    /**
     * Page through groups with their entities and period counts, newest first.
     */
    public function paginateGroups(bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return ConsolidationGroup::with(['entities.entityOrganization', 'createdBy:id,name'])
            ->withCount('periods')
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * One of the organisation's groups, or null when the id is missing or foreign.
     */
    public function findGroup(int $id): ?ConsolidationGroup
    {
        return ConsolidationGroup::find($id);
    }

    /**
     * One of the organisation's groups with its entities, periods and creator.
     */
    public function findGroupWithDetails(int $id): ?ConsolidationGroup
    {
        return ConsolidationGroup::with([
            'entities.entityOrganization',
            'periods',
            'createdBy:id,name',
        ])->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(ConsolidationGroup $group, array $data): ConsolidationGroup
    {
        $group->update($data);

        return $group->fresh(['entities', 'createdBy:id,name']);
    }

    /**
     * Delete a group. A group with a completed period backs issued
     * consolidated statements and is kept.
     */
    public function deleteGroup(ConsolidationGroup $group): void
    {
        if ($group->periods()->where('status', ConsolidationPeriod::STATUS_COMPLETED)->exists()) {
            throw new \InvalidArgumentException('Cannot delete a group that has completed periods.');
        }

        $group->delete();
    }

    /**
     * An entity of one of the organisation's groups, or null.
     *
     * Entities carry no organization_id, so the organisation scope is reached
     * through the group: an entity of another organisation's group is null.
     */
    public function findEntity(int $id): ?ConsolidationEntity
    {
        return ConsolidationEntity::whereHas('group')->find($id);
    }

    public function removeEntity(ConsolidationEntity $entity): void
    {
        $entity->delete();
    }

    /**
     * Page through periods, latest start first. A group or status filter
     * applies only when its value is truthy.
     */
    public function paginatePeriods(mixed $groupId, mixed $status, int $perPage): LengthAwarePaginator
    {
        return ConsolidationPeriod::with(['group:id,name,currency_code', 'createdBy:id,name'])
            ->withCount(['eliminationEntries', 'consolidatedBalances'])
            ->when($groupId, fn ($q, $id) => $q->where('consolidation_group_id', $id))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('period_start')
            ->paginate($perPage);
    }

    /**
     * One of the organisation's periods, or null when the id is missing or foreign.
     */
    public function findPeriod(int $id): ?ConsolidationPeriod
    {
        return ConsolidationPeriod::find($id);
    }

    /**
     * One of the organisation's periods with its group, entities, fiscal year,
     * creator and elimination/balance counts.
     */
    public function findPeriodWithDetails(int $id): ?ConsolidationPeriod
    {
        return ConsolidationPeriod::with([
            'group.entities.entityOrganization',
            'fiscalYear',
            'createdBy:id,name',
        ])
            ->withCount(['eliminationEntries', 'consolidatedBalances'])
            ->find($id);
    }

    public function countCollectedBalances(ConsolidationPeriod $period): int
    {
        return $period->consolidatedBalances()->count();
    }

    /**
     * Page through a period's elimination entries, newest first. An entry
     * type filter applies only when its value is truthy.
     */
    public function paginateEliminations(ConsolidationPeriod $period, mixed $entryType, int $perPage): LengthAwarePaginator
    {
        return EliminationEntry::where('consolidation_period_id', $period->id)
            ->with([
                'debitAccount:id,code,name',
                'creditAccount:id,code,name',
                'createdBy:id,name',
            ])
            ->when($entryType, fn ($q, $t) => $q->where('entry_type', $t))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Create a consolidation group along with its initial entities.
     *
     * @param  array<string, mixed>   $data
     * @param  array<int, array<string, mixed>>  $entities
     */
    public function createGroup(array $data, array $entities, int $userId): ConsolidationGroup
    {
        return DB::transaction(function () use ($data, $entities, $userId) {
            $data['created_by'] = $userId;

            $group = ConsolidationGroup::create($data);

            foreach ($entities as $entityData) {
                $this->addEntity($group, $entityData, $userId);
            }

            return $group->fresh(['entities.entityOrganization', 'createdBy:id,name']);
        });
    }

    /**
     * Add an entity (subsidiary organization) to a consolidation group.
     *
     * @param  array<string, mixed>  $data
     */
    public function addEntity(ConsolidationGroup $group, array $data, int $userId): ConsolidationEntity
    {
        return DB::transaction(function () use ($group, $data) {
            $this->assertInGroupOf((int) $group->organization_id, (int) $data['entity_organization_id']);

            $entity = ConsolidationEntity::create([
                'consolidation_group_id'   => $group->id,
                'entity_organization_id'   => $data['entity_organization_id'],
                'name'                     => $data['name'],
                'ownership_percent'        => $data['ownership_percent'] ?? 100.00,
                'consolidation_method'     => $data['consolidation_method'] ?? ConsolidationEntity::METHOD_FULL,
                'local_currency'           => $data['local_currency'] ?? null,
            ]);

            $totalOwnership = $group->entities()->sum('ownership_percent');
            if (bccomp((string) $totalOwnership, '100', 2) > 0) {
                throw new \App\Exceptions\ApiException(
                    "Total ownership percentage ({$totalOwnership}%) exceeds 100%."
                );
            }

            return $entity;
        });
    }

    /**
     * Create a consolidation period for a group.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPeriod(array $data, int $userId): ConsolidationPeriod
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;

            return ConsolidationPeriod::create($data);
        });
    }

    /**
     * Refuses an organization outside $organizationId's parent-subsidiary group.
     *
     * Consolidation reads an entity's ledger and reports it under the group's
     * figures, so the request rule is not the only place membership is
     * checked: a caller reaching the service another way must not pull a
     * tenant outside the group into a consolidation.
     */
    private function assertInGroupOf(int $organizationId, int $candidateOrganizationId): void
    {
        if (! in_array($candidateOrganizationId, Organization::groupIds($organizationId), true)) {
            throw new BusinessRuleException(
                "That organization is outside this organization's group.",
                'ORGANIZATION_OUTSIDE_GROUP'
            );
        }
    }

    /**
     * Collect and convert account balances from each entity in the period.
     *
     * For each entity:
     *   1. Retrieve all active accounts in the entity's organization.
     *   2. Fetch the closing balance for each account as of the period end date.
     *   3. Convert from the entity's local currency to the group's reporting currency.
     *   4. Apply the entity's ownership multiplier (for proportional consolidation).
     *   5. Upsert ConsolidatedBalance records.
     */
    public function collectEntityBalances(ConsolidationPeriod $period, int $userId): void
    {
        DB::transaction(function () use ($period) {
            $period->update(['status' => ConsolidationPeriod::STATUS_IN_PROGRESS]);

            $group = $period->group()->with('entities.entityOrganization')->first();

            if ($group === null) {
                throw new \RuntimeException('Consolidation group not found for this period.');
            }

            foreach ($group->entities as $entity) {
                $this->collectBalancesForEntity($period, $entity, $group->currency_code);
            }
        });
    }

    /**
     * Create an elimination entry for a consolidation period.
     *
     * @param  array<string, mixed>  $data
     */
    public function createEliminationEntry(array $data, int $userId): EliminationEntry
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;

            return EliminationEntry::create($data);
        });
    }

    /**
     * Generate the consolidated financial report after eliminations.
     *
     * Returns:
     *   - balance_sheet: assets, liabilities, equity with consolidated amounts
     *   - income_statement: income and expense accounts
     *   - eliminations_summary: total eliminated amounts by entry type
     *
     * @return array<string, mixed>
     */
    public function generateConsolidatedReport(ConsolidationPeriod $period): array
    {
        $period->loadMissing([
            'group',
            'eliminationEntries.debitAccount',
            'eliminationEntries.creditAccount',
        ]);

        // Aggregate consolidated balances by account
        $balanceRows = ConsolidatedBalance::where('consolidation_period_id', $period->id)
            ->with('account:id,code,name,account_type,sub_type')
            ->get();

        // Group by account_id and sum consolidated_amount across entities
        $accountTotals = $balanceRows->groupBy('account_id')->map(function ($rows) {
            $total = '0';
            foreach ($rows as $r) {
                $total = bcadd($total, (string) $r->consolidated_amount, 4);
            }
            return [
                'account_id'           => $rows->first()->account_id,
                'account'              => $rows->first()->account,
                'consolidated_amount'  => $total,
            ];
        })->values();

        // Build elimination adjustments keyed by account_id
        $eliminationAdjustments = [];
        foreach ($period->eliminationEntries as $entry) {
            $elimAmt = (string) $entry->amount;

            // Debit side reduces credit-normal accounts (liabilities, equity, income)
            $eliminationAdjustments[$entry->debit_account_id] = bcsub(
                $eliminationAdjustments[$entry->debit_account_id] ?? '0',
                $elimAmt,
                4
            );

            // Credit side reduces debit-normal accounts (assets, expenses)
            $eliminationAdjustments[$entry->credit_account_id] = bcadd(
                $eliminationAdjustments[$entry->credit_account_id] ?? '0',
                $elimAmt,
                4
            );
        }

        // Apply eliminations and segregate by type
        $balanceSheet     = ['assets' => [], 'liabilities' => [], 'equity' => []];
        $incomeStatement  = ['income' => [], 'expenses' => []];

        foreach ($accountTotals as $row) {
            $account    = $row['account'];
            $amount     = (string) $row['consolidated_amount'];
            $adjustment = $eliminationAdjustments[$account->id] ?? '0';
            $netAmount  = bcadd($amount, (string) $adjustment, 4);

            $entry = [
                'account_id'          => $account->id,
                'account_code'        => $account->code,
                'account_name'        => $account->name,
                'consolidated_amount' => $netAmount,
                'eliminated_amount'   => $adjustment,
                'gross_amount'        => $amount,
            ];

            match ($account->account_type) {
                Account::TYPE_ASSET     => $balanceSheet['assets'][]       = $entry,
                Account::TYPE_LIABILITY => $balanceSheet['liabilities'][]   = $entry,
                Account::TYPE_EQUITY    => $balanceSheet['equity'][]        = $entry,
                Account::TYPE_INCOME    => $incomeStatement['income'][]     = $entry,
                Account::TYPE_EXPENSE   => $incomeStatement['expenses'][]   = $entry,
                default                 => null,
            };
        }

        // Totals using bcmath
        $totalAssets      = array_reduce(array_column($balanceSheet['assets'], 'consolidated_amount'), fn ($c, $v) => bcadd($c, (string) $v, 4), '0');
        $totalLiabilities = array_reduce(array_column($balanceSheet['liabilities'], 'consolidated_amount'), fn ($c, $v) => bcadd($c, (string) $v, 4), '0');
        $totalEquity      = array_reduce(array_column($balanceSheet['equity'], 'consolidated_amount'), fn ($c, $v) => bcadd($c, (string) $v, 4), '0');
        $totalIncome      = array_reduce(array_column($incomeStatement['income'], 'consolidated_amount'), fn ($c, $v) => bcadd($c, (string) $v, 4), '0');
        $totalExpenses    = array_reduce(array_column($incomeStatement['expenses'], 'consolidated_amount'), fn ($c, $v) => bcadd($c, (string) $v, 4), '0');

        // Elimination summary
        $eliminationsSummary = $period->eliminationEntries
            ->groupBy('entry_type')
            ->map(fn ($entries) => [
                'entry_type' => $entries->first()->entry_type,
                'count'      => $entries->count(),
                'total'      => $entries->reduce(fn ($c, $e) => bcadd($c, (string) $e->amount, 4), '0'),
            ])->values()->all();

        return [
            'period' => [
                'id'           => $period->id,
                'uuid'         => $period->uuid,
                'period_start' => $period->period_start?->toDateString(),
                'period_end'   => $period->period_end?->toDateString(),
                'currency'     => $period->group?->currency_code,
                'status'       => $period->status,
            ],
            'balance_sheet'       => array_merge($balanceSheet, [
                'total_assets'      => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity'      => $totalEquity,
                'net_assets'        => bcsub($totalAssets, $totalLiabilities, 4),
            ]),
            'income_statement'    => array_merge($incomeStatement, [
                'total_income'   => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_income'     => bcsub($totalIncome, $totalExpenses, 4),
            ]),
            'eliminations_summary' => $eliminationsSummary,
        ];
    }

    /**
     * Mark a consolidation period as completed.
     */
    public function completePeriod(ConsolidationPeriod $period, int $userId): ConsolidationPeriod
    {
        if ($period->isCompleted()) {
            throw new \InvalidArgumentException('Consolidation period is already completed.');
        }

        if (!$period->consolidatedBalances()->exists()) {
            throw new \InvalidArgumentException(
                'Cannot complete period: no consolidated balances collected. Run "Collect Balances" first.'
            );
        }

        $period->update(['status' => ConsolidationPeriod::STATUS_COMPLETED]);

        return $period->fresh();
    }

    // -------------------------------------------------------------------------
    // Elimination Entries — IC Profit Center Consolidation
    // -------------------------------------------------------------------------

    /**
     * Auto-generate elimination entries for a consolidation period.
     *
     * 1. Eliminates inter-company transfer receivables/payables.
     * 2. Eliminates intra-group revenue/expense from CO-PA line items tagged as IC.
     *
     * @return EliminationEntry[]
     */
    public function generateEliminationEntries(ConsolidationPeriod $period, int $userId): array
    {
        return DB::transaction(function () use ($period, $userId): array {
            $period->loadMissing('group.entities');
            $group    = $period->group;
            $created  = [];

            if ($group === null) {
                throw new \RuntimeException('Consolidation group not found for this period.');
            }

            // Resolve entity organization IDs in this group
            $entityOrgIds = $group->entities->pluck('entity_organization_id')->all();

            // -----------------------------------------------------------
            // Step 1: Eliminate inter-company transfers
            // -----------------------------------------------------------
            // Use the period_end date range to scope transfers
            $periodFrom = $period->period_start?->toDateString() ?? now()->startOfYear()->toDateString();
            $periodTo   = $period->period_end?->toDateString()   ?? now()->endOfYear()->toDateString();

            $icTransfers = InterCompanyTransfer::withoutGlobalScopes()
                ->whereIn('organization_id', $entityOrgIds)
                ->whereIn('to_organization_id', $entityOrgIds)
                ->whereBetween('transfer_date', [$periodFrom, $periodTo])
                ->where('status', InterCompanyTransfer::STATUS_COMPLETED)
                ->get();

            foreach ($icTransfers as $transfer) {
                $sellingOrgName = "Org#{$transfer->organization_id}";
                $buyingOrgName  = "Org#{$transfer->to_organization_id}";

                // Resolve AR account for selling org and AP account for buying org
                $arAccountId = $this->resolveIcArAccount((int) $transfer->organization_id);
                $apAccountId = $this->resolveIcApAccount((int) $transfer->to_organization_id);

                if ($arAccountId === null || $apAccountId === null) {
                    // Cannot build entry without account mapping — skip silently
                    continue;
                }

                $entry = EliminationEntry::create([
                    'organization_id'         => $period->organization_id,
                    'consolidation_period_id' => $period->id,
                    'entry_type'              => 'intercompany',
                    'debit_account_id'        => $arAccountId,
                    'credit_account_id'       => $apAccountId,
                    'amount'                  => $transfer->amount ?? 0,
                    'currency_code'           => $transfer->currency_code ?? $group->currency_code,
                    'description'             => "IC elimination: {$sellingOrgName} → {$buyingOrgName}",
                    'created_by'              => $userId,
                ]);

                $created[] = $entry;
            }

            // -----------------------------------------------------------
            // Step 2: Eliminate intra-group COPA revenue/expense pairs
            // -----------------------------------------------------------
            // Load CO-PA line items for IC source types within the group's fiscal context
            $copaItems = CopaLineItem::withoutGlobalScopes()
                ->whereIn('organization_id', $entityOrgIds)
                ->where('source_document_type', 'intercompany_sale')
                ->get();

            foreach ($copaItems as $item) {
                $revenue = (float) ($item->revenue ?? 0);

                if ($revenue == 0) {
                    continue;
                }

                // Revenue account for the selling entity — use profit center GL
                $revenueAccountId = $this->resolveCopaRevenueAccount($item);
                $expenseAccountId = $this->resolveCopaExpenseAccount($item);

                if ($revenueAccountId === null || $expenseAccountId === null) {
                    continue;
                }

                $entry = EliminationEntry::create([
                    'organization_id'         => $period->organization_id,
                    'consolidation_period_id' => $period->id,
                    'entry_type'              => 'intercompany',
                    'debit_account_id'        => $revenueAccountId,
                    'credit_account_id'       => $expenseAccountId,
                    'amount'                  => $revenue,
                    'currency_code'           => $item->currency_code ?? $group->currency_code,
                    'description'             => "COPA IC revenue elimination: org#{$item->organization_id}",
                    'created_by'              => $userId,
                ]);

                $created[] = $entry;
            }

            return $created;
        });
    }

    /**
     * Return existing elimination entries for a consolidation period.
     */
    public function getEliminationEntries(ConsolidationPeriod $period): Collection
    {
        return EliminationEntry::where('consolidation_period_id', $period->id)
            ->with([
                'debitAccount:id,code,name',
                'creditAccount:id,code,name',
                'createdBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Attempt to resolve the AR (accounts receivable) GL account for an org's IC transactions.
     * Looks for an Account of sub_type 'accounts_receivable' or 'intercompany_receivable'.
     */
    private function resolveIcArAccount(int $orgId): ?int
    {
        $account = Account::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereIn('sub_type', ['accounts_receivable', 'intercompany_receivable', 'trade_receivable'])
            ->value('id');

        return $account !== null ? (int) $account : null;
    }

    /**
     * Attempt to resolve the AP (accounts payable) GL account for an org's IC transactions.
     */
    private function resolveIcApAccount(int $orgId): ?int
    {
        $account = Account::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereIn('sub_type', ['accounts_payable', 'intercompany_payable', 'trade_payable'])
            ->value('id');

        return $account !== null ? (int) $account : null;
    }

    /**
     * Resolve the revenue GL account for a COPA line item (via profit center GL or income account).
     */
    private function resolveCopaRevenueAccount(CopaLineItem $item): ?int
    {
        if ($item->profit_center_id !== null) {
            $gl = \App\Models\Accounting\ProfitCenter::withoutGlobalScopes()
                ->where('id', $item->profit_center_id)
                ->value('gl_account_id');

            if ($gl !== null) {
                return (int) $gl;
            }
        }

        // Fall back to the first income account in the org
        return Account::withoutGlobalScope('organization')
            ->where('organization_id', $item->organization_id)
            ->where('account_type', Account::TYPE_INCOME)
            ->where('is_active', true)
            ->value('id');
    }

    /**
     * Resolve the expense GL account for a COPA line item (via cost center GL or expense account).
     */
    private function resolveCopaExpenseAccount(CopaLineItem $item): ?int
    {
        if ($item->cost_center_id !== null) {
            $gl = \App\Models\Accounting\CostCenter::withoutGlobalScopes()
                ->where('id', $item->cost_center_id)
                ->value('gl_account_id');

            if ($gl !== null) {
                return (int) $gl;
            }
        }

        // Fall back to the first expense account in the org
        return Account::withoutGlobalScope('organization')
            ->where('organization_id', $item->organization_id)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->where('is_active', true)
            ->value('id');
    }

    /**
     * Derive fiscal year integer from the period's period_end date (or fiscal year relation).
     */
    private function resolveFiscalYear(ConsolidationPeriod $period): int
    {
        if ($period->fiscal_year_id !== null) {
            $startDate = \App\Models\Accounting\FiscalYear::withoutGlobalScopes()
                ->where('id', $period->fiscal_year_id)
                ->value('start_date');

            if ($startDate !== null) {
                return (int) date('Y', strtotime($startDate));
            }
        }

        return (int) ($period->period_end?->year ?? now()->year);
    }

    /**
     * Derive the fiscal period (1–12) from the period's period_end month.
     */
    private function resolvePeriod(ConsolidationPeriod $period): int
    {
        return (int) ($period->period_end?->month ?? now()->month);
    }

    /**
     * Fetch account balances for one entity and store them as ConsolidatedBalance rows.
     */
    private function collectBalancesForEntity(
        ConsolidationPeriod $period,
        ConsolidationEntity $entity,
        string $groupCurrency
    ): void {
        $orgId       = $entity->entity_organization_id;
        $asOfDate    = $period->period_end->toDateString();
        $multiplier  = $entity->getOwnershipMultiplier();

        // Determine the exchange rate from the entity's local currency to the group currency
        $localCurrency = $entity->local_currency;
        $exchangeRate  = 1.0;

        if ($localCurrency && $localCurrency !== $groupCurrency) {
            $rateRecord = ExchangeRate::withoutGlobalScope('organization')
                ->where('from_currency', $localCurrency)
                ->where('to_currency', $groupCurrency)
                ->where('rate_date', '<=', $asOfDate)
                ->orderByDesc('rate_date')
                ->value('rate');

            if (!$rateRecord) {
                throw new \App\Exceptions\ApiException(
                    "Exchange rate not found for {$localCurrency} on {$asOfDate}. Configure rates before running consolidation."
                );
            }

            $exchangeRate = (float) $rateRecord;
        }

        // Get all active leaf accounts in the entity's organization
        $accounts = Account::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_header', false)
            ->where('is_active', true)
            ->get();

        foreach ($accounts as $account) {
            $balance = $this->accountBalanceService->getAccountBalance(
                $account->id,
                null,
                $asOfDate,
                false
            );

            $closingBalance = (float) ($balance['closing_balance'] ?? 0.0);

            if (bccomp((string)$closingBalance, '0', 4) === 0) {
                continue;
            }

            $localAmount       = $closingBalance;
            $consolidatedAmt   = bcmul(
                bcmul((string) $localAmount, (string) $exchangeRate, 4),
                (string) $multiplier,
                4
            );

            ConsolidatedBalance::updateOrCreate(
                [
                    'consolidation_period_id' => $period->id,
                    'account_id'              => $account->id,
                    'entity_organization_id'  => $orgId,
                ],
                [
                    'local_amount'        => round($localAmount, 4),
                    'exchange_rate'       => round($exchangeRate, 6),
                    'consolidated_amount' => $consolidatedAmt,
                ]
            );
        }
    }
}
