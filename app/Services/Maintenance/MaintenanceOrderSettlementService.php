<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Accounting\JournalEntry;
use App\Models\HR\Employee;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenanceOrderCostLine;
use App\Models\Maintenance\MaintenanceOrderSettlement;
use App\Models\Sales\Contact;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Records a maintenance order's actual costs and settles them to receivers.
 *
 * Settlement moves costs off the maintenance clearing account onto the
 * receivers in one posted journal entry: an asset receiver capitalizes its
 * share, any other receiver expenses it. A settlement covers only the costs no
 * earlier settlement covered and runs on the locked order, so a repeated or
 * concurrent request cannot post the same costs twice. The settlement rows and
 * their entry are written together or not at all.
 */
class MaintenanceOrderSettlementService
{
    /** The accounting setting naming the account a receiver's share is debited to. */
    private const DEBIT_ACCOUNT_KEYS = [
        MaintenanceOrderSettlement::RECEIVER_COST_CENTER => 'maintenance_expense_account_id',
        MaintenanceOrderSettlement::RECEIVER_ORDER => 'maintenance_expense_account_id',
        MaintenanceOrderSettlement::RECEIVER_WBS => 'maintenance_expense_account_id',
        MaintenanceOrderSettlement::RECEIVER_ASSET => 'maintenance_capitalization_account_id',
    ];

    /** The accounting setting naming the account the settled costs are credited from. */
    private const CLEARING_ACCOUNT_KEY = 'maintenance_clearing_account_id';

    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * The organization's maintenance order; another organization's id is not found.
     */
    public function findOrderOrFail(int $organizationId, int $orderId): MaintenanceOrder
    {
        return MaintenanceOrder::forOrganization($organizationId)->findOrFail($orderId);
    }

    public function paginateCostLines(MaintenanceOrder $order, int $perPage): LengthAwarePaginator
    {
        return $this->costLinesOf($order)
            ->with($this->costLineReferences())
            ->orderBy('posting_date')
            ->paginate($perPage);
    }

    /**
     * Record a cost line against a maintenance order. Without a total, the
     * total is the quantity times the unit cost.
     */
    public function recordCost(MaintenanceOrder $order, array $data): MaintenanceOrderCostLine
    {
        $totalCost = $data['total_cost']
            ?? ((float) ($data['quantity'] ?? 1) * (float) ($data['unit_cost'] ?? 0));

        $line = MaintenanceOrderCostLine::create([
            'organization_id' => $order->organization_id,
            'maintenance_order_id' => $order->id,
            'cost_element_id' => $data['cost_element_id'] ?? null,
            'cost_type' => $data['cost_type'],
            'quantity' => $data['quantity'] ?? null,
            'unit_cost' => $data['unit_cost'] ?? null,
            'total_cost' => $totalCost,
            'currency_code' => $data['currency_code'],
            'posting_date' => $data['posting_date'] ?? now()->toDateString(),
            'vendor_id' => $data['vendor_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
        ]);

        return $line->load($this->costLineReferences());
    }

    /**
     * The order's costs by cost type, and their total.
     *
     * @return array{by_type: array<string, float>, total: float}
     */
    public function getTotalCost(MaintenanceOrder $order): array
    {
        $byType = [];

        foreach ($this->costLinesOf($order)->get(['cost_type', 'total_cost']) as $line) {
            $byType[$line->cost_type] = ($byType[$line->cost_type] ?? 0.0) + (float) $line->total_cost;
        }

        return [
            'by_type' => $byType,
            'total' => round(array_sum($byType), 4),
        ];
    }

    /**
     * Settle the order's unsettled costs to the receivers in $rules.
     *
     * Each rule holds receiver_type (cost_center|asset|order|wbs), receiver_id
     * and percentage; the percentages add up to 100. The last receiver takes
     * what rounding leaves, so the shares always add up to the settled amount.
     *
     * @param  list<array{receiver_type: string, receiver_id: int, percentage: float|int|string}>  $rules
     * @return Collection<int, MaintenanceOrderSettlement>
     *
     * @throws InvalidArgumentException when nothing is left to settle, an account is not mapped or the entry cannot be posted
     */
    public function settle(MaintenanceOrder $order, array $rules, int $userId): Collection
    {
        $rules = array_values($rules);

        return $order->lockForTransition(function (MaintenanceOrder $order) use ($rules, $userId): Collection {
            $amount = $this->unsettledAmount($order);

            if (bccomp($amount, '0', 4) <= 0) {
                throw new InvalidArgumentException('The maintenance order has no costs left to settle.');
            }

            $shares = $this->shares($amount, $rules);
            $entry = $this->postSettlementEntry($order, $rules, $shares, $amount, $userId);
            $ruleType = count($rules) === 1 ? MaintenanceOrderSettlement::RULE_FULL : MaintenanceOrderSettlement::RULE_PARTIAL;

            return collect($rules)->map(fn (array $rule, int $index): MaintenanceOrderSettlement => MaintenanceOrderSettlement::create([
                'organization_id' => $order->organization_id,
                'maintenance_order_id' => $order->id,
                'settlement_rule_type' => $ruleType,
                'receiver_type' => $rule['receiver_type'],
                'receiver_id' => $rule['receiver_id'],
                'percentage' => (float) $rule['percentage'],
                'settled_amount' => $shares[$index],
                'settlement_date' => $entry->entry_date->toDateString(),
                'fiscal_year' => (int) $entry->entry_date->format('Y'),
                'period' => (int) $entry->entry_date->format('n'),
                'journal_entry_id' => $entry->id,
                'created_by' => $userId,
            ]));
        });
    }

    /**
     * Orders of the organization that have cost lines but no settlement.
     */
    public function getUnsettledOrders(int $organizationId): Collection
    {
        $settledOrderIds = MaintenanceOrderSettlement::where('organization_id', $organizationId)
            ->pluck('maintenance_order_id')
            ->unique();

        return MaintenanceOrderCostLine::where('organization_id', $organizationId)
            ->whereNotIn('maintenance_order_id', $settledOrderIds)
            ->selectRaw('maintenance_order_id, SUM(total_cost) as total_cost, COUNT(*) as line_count')
            ->groupBy('maintenance_order_id')
            ->get();
    }

    public function getSettlementHistory(MaintenanceOrder $order): Collection
    {
        return MaintenanceOrderSettlement::forOrganization($order->organization_id)
            ->where('maintenance_order_id', $order->id)
            ->with(['journalEntry', 'creator'])
            ->orderBy('settlement_date')
            ->get();
    }

    /**
     * Debit each receiver's share and credit the settled amount to the
     * clearing account. Every account is resolved before anything is written.
     *
     * @param  list<array<string, mixed>>  $rules
     * @param  list<string>  $shares
     */
    private function postSettlementEntry(MaintenanceOrder $order, array $rules, array $shares, string $amount, int $userId): JournalEntry
    {
        $organizationId = $order->organization_id;
        $description = "Settlement of maintenance order {$order->order_number}";
        $lines = [];

        foreach ($rules as $index => $rule) {
            $lines[] = [
                'account_id' => $this->mappedAccountId($organizationId, self::DEBIT_ACCOUNT_KEYS[$rule['receiver_type']]),
                'description' => "{$description} to {$rule['receiver_type']} #{$rule['receiver_id']}",
                'debit' => $shares[$index],
                'credit' => 0,
                'cost_center_id' => $rule['receiver_type'] === MaintenanceOrderSettlement::RECEIVER_COST_CENTER
                    ? (int) $rule['receiver_id']
                    : null,
            ];
        }

        $lines[] = [
            'account_id' => $this->mappedAccountId($organizationId, self::CLEARING_ACCOUNT_KEY),
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
        ];

        return $this->journalService->createAndPost([
            'organization_id' => $organizationId,
            'entry_date' => now()->toDateString(),
            'reference' => $order->order_number,
            'description' => $description,
            'source_type' => MaintenanceOrder::class,
            'source_id' => $order->id,
            'created_by' => $userId,
        ], $lines);
    }

    private function mappedAccountId(int $organizationId, string $key): int
    {
        $account = $this->accountResolver->mapped($organizationId, $key);

        if ($account === null) {
            throw new InvalidArgumentException(
                "Maintenance settlement needs the accounting setting {$key} to name one of the organization's accounts."
            );
        }

        return $account->id;
    }

    /**
     * Each rule's share of $amount; the last rule takes the remainder.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return list<string>
     */
    private function shares(string $amount, array $rules): array
    {
        $shares = [];
        $allocated = '0';
        $last = count($rules) - 1;

        foreach ($rules as $index => $rule) {
            $share = $index === $last
                ? bcsub($amount, $allocated, 4)
                : bcdiv(bcmul($amount, $this->decimal($rule['percentage']), 8), '100', 4);

            $allocated = bcadd($allocated, $share, 4);
            $shares[] = $share;
        }

        return $shares;
    }

    /** The order's costs that no settlement has covered yet. */
    private function unsettledAmount(MaintenanceOrder $order): string
    {
        $costs = $this->costLinesOf($order)->sum('total_cost');
        $settled = MaintenanceOrderSettlement::forOrganization($order->organization_id)
            ->where('maintenance_order_id', $order->id)
            ->sum('settled_amount');

        return bcsub($this->decimal($costs), $this->decimal($settled), 4);
    }

    private function costLinesOf(MaintenanceOrder $order): Builder
    {
        return MaintenanceOrderCostLine::forOrganization($order->organization_id)
            ->where('maintenance_order_id', $order->id);
    }

    /** @return list<string> */
    private function costLineReferences(): array
    {
        return [
            'costElement',
            'vendor:'.implode(',', Contact::REFERENCE_COLUMNS),
            'employee:'.implode(',', Employee::REFERENCE_COLUMNS),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
