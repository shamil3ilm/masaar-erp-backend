<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Accounting\Account;
use App\Models\Budget\Budget;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Get Profit & Loss statement.
     *
     * Single DB aggregate query (one JOIN + GROUP BY) instead of N+1 per account.
     */
    public function getProfitAndLoss(Carbon $startDate, Carbon $endDate): array
    {
        $orgId = auth()->user()->organization_id;

        // One query: sum debits/credits per account for income + expense accounts.
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereIn('a.account_type', ['income', 'expense'])
            // The account's current flags are deliberately not filtered on: a
            // posting only ever reaches a postable account, and an account
            // marked inactive or turned into a header afterwards still has to
            // carry the history the trial balance shows against it.
            ->whereDate('je.entry_date', '>=', $startDate)
            ->whereDate('je.entry_date', '<=', $endDate)
            ->select([
                'a.id as account_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.account_type',
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
            ])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type')
            ->get();

        $totalIncome = 0;
        $incomeBreakdown = [];
        $totalExpenses = 0;
        $expenseBreakdown = [];

        foreach ($rows as $row) {
            if ($row->account_type === 'income') {
                $balance = bcsub((string) $row->total_credit, (string) $row->total_debit, 4);
                if (bccomp($balance, '0', 4) !== 0) {
                    $incomeBreakdown[] = [
                        'account_id' => $row->account_id,
                        'account_code' => $row->account_code,
                        'account_name' => $row->account_name,
                        'amount' => (float) $balance,
                    ];
                    $totalIncome = bcadd((string) $totalIncome, (string) $balance, 4);
                }
            } else {
                $balance = bcsub((string) $row->total_debit, (string) $row->total_credit, 4);
                if (bccomp($balance, '0', 4) !== 0) {
                    $expenseBreakdown[] = [
                        'account_id' => $row->account_id,
                        'account_code' => $row->account_code,
                        'account_name' => $row->account_name,
                        'amount' => (float) $balance,
                    ];
                    $totalExpenses = bcadd((string) $totalExpenses, (string) $balance, 4);
                }
            }
        }

        $netProfit = bcsub((string) $totalIncome, (string) $totalExpenses, 4);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'income' => [
                'total' => (float) $totalIncome,
                'breakdown' => $incomeBreakdown,
            ],
            'expenses' => [
                'total' => (float) $totalExpenses,
                'breakdown' => $expenseBreakdown,
            ],
            'net_profit' => (float) $netProfit,
            'profit_margin' => bccomp((string) $totalIncome, '0', 4) > 0
                ? (float) bcmul(bcdiv((string) $netProfit, (string) $totalIncome, 8), '100', 4)
                : 0,
        ];
    }

    /**
     * Get Balance Sheet.
     *
     * Single DB aggregate query (one JOIN + GROUP BY) instead of N+1 per account.
     */
    public function getBalanceSheet(Carbon $asOfDate): array
    {
        $orgId = auth()->user()->organization_id;

        $bsTypes = ['asset', 'liability', 'equity'];

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereIn('a.account_type', $bsTypes)
            // As in the P&L: an account's current flags do not decide whether
            // its history counts, or a chart tidied up after the fact would
            // leave the sheet unbalanced against the trial balance.
            ->whereDate('je.entry_date', '<=', $asOfDate)
            ->select([
                'a.id as account_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.account_type',
                'a.sub_type',
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
            ])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type', 'a.sub_type')
            ->get();

        $assets = ['current' => [], 'fixed' => [], 'other' => []];
        $liabilities = ['current' => [], 'long_term' => []];
        $equity = [];

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($rows as $row) {
            $balance = match ($row->account_type) {
                'asset' => bcsub((string) $row->total_debit, (string) $row->total_credit, 4),
                'liability', 'equity' => bcsub((string) $row->total_credit, (string) $row->total_debit, 4),
                default => '0',
            };

            if (bccomp($balance, '0', 4) === 0) {
                continue;
            }

            $item = [
                'account_id' => $row->account_id,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'amount' => (float) $balance,
            ];

            switch ($row->account_type) {
                case 'asset':
                    if (in_array($row->sub_type, ['bank', 'cash', 'receivable', 'inventory'])) {
                        $assets['current'][] = $item;
                    } elseif ($row->sub_type === 'fixed_asset') {
                        $assets['fixed'][] = $item;
                    } else {
                        $assets['other'][] = $item;
                    }
                    $totalAssets = bcadd((string) $totalAssets, (string) $balance, 4);
                    break;

                case 'liability':
                    // The three sub-types a liability account can carry that
                    // are settled within the year. 'other_liability' is the
                    // only one left, and holds the long-term borrowings.
                    if (in_array($row->sub_type, ['payable', 'credit_card', 'tax_payable'])) {
                        $liabilities['current'][] = $item;
                    } else {
                        $liabilities['long_term'][] = $item;
                    }
                    $totalLiabilities = bcadd((string) $totalLiabilities, (string) $balance, 4);
                    break;

                case 'equity':
                    $equity[] = $item;
                    $totalEquity = bcadd((string) $totalEquity, (string) $balance, 4);
                    break;
            }
        }

        // Add current period net income to retained earnings
        $currentYearStart = $asOfDate->copy()->startOfYear();
        $pnl = $this->getProfitAndLoss($currentYearStart, $asOfDate);
        $retainedEarnings = (float) $pnl['net_profit'];

        $equity[] = [
            'account_code' => 'RE',
            'account_name' => 'Current Year Earnings',
            'amount' => $retainedEarnings,
        ];
        $totalEquity = bcadd((string) $totalEquity, (string) $retainedEarnings, 4);

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'assets' => [
                'current_assets' => $assets['current'],
                'fixed_assets' => $assets['fixed'],
                'other_assets' => $assets['other'],
                'total' => (float) $totalAssets,
            ],
            'liabilities' => [
                'current_liabilities' => $liabilities['current'],
                'long_term_liabilities' => $liabilities['long_term'],
                'total' => (float) $totalLiabilities,
            ],
            'equity' => [
                'items' => $equity,
                'total' => (float) $totalEquity,
            ],
            'total_liabilities_and_equity' => (float) bcadd((string) $totalLiabilities, (string) $totalEquity, 4),
            'is_balanced' => bccomp((string) $totalAssets, bcadd((string) $totalLiabilities, (string) $totalEquity, 4), 2) === 0,
        ];
    }

    /**
     * Get Cash Flow statement.
     */
    public function getCashFlow(Carbon $startDate, Carbon $endDate): array
    {
        $orgId = auth()->user()->organization_id;

        // Get bank/cash account IDs
        $cashAccountIds = Account::where('organization_id', $orgId)
            ->where('account_type', 'asset')
            ->whereIn('sub_type', ['bank', 'cash'])
            ->pluck('id');

        $movementsInPeriod = fn () => DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '>=', $startDate)
            ->whereDate('je.entry_date', '<=', $endDate);

        // The totals come off every movement, grouped in the database. The
        // listing below is capped, and summing that capped page instead would
        // silently understate a busy period's cash.
        $totalsBySource = $movementsInPeriod()
            ->groupBy('je.source_type')
            ->selectRaw('je.source_type as source_type, COALESCE(SUM(jel.base_debit), 0) - COALESCE(SUM(jel.base_credit), 0) as net')
            ->get();

        $cashFlow = ['operating' => Decimal::zero(4), 'investing' => Decimal::zero(4), 'financing' => Decimal::zero(4)];

        foreach ($totalsBySource as $group) {
            $activity = $this->cashFlowActivity($group->source_type);
            $cashFlow[$activity] = bcadd($cashFlow[$activity], Decimal::at($group->net, 4), 4);
        }

        // Join journal_entries directly — avoids N+1 from eager-loading.
        $cashMovements = $movementsInPeriod()
            ->select([
                'je.entry_date',
                'je.reference',
                'je.source_type',
                'je.description as entry_description',
                'jel.description as line_description',
                'jel.base_debit as debit',
                'jel.base_credit as credit',
            ])
            ->orderBy('je.entry_date')
            ->limit(2000)
            ->get();

        $activities = ['operating' => [], 'investing' => [], 'financing' => []];

        foreach ($cashMovements as $line) {
            $activities[$this->cashFlowActivity($line->source_type)][] = [
                'date' => $line->entry_date,
                'reference' => $line->reference,
                'description' => $line->line_description ?? $line->entry_description,
                'amount' => (float) bcsub(Decimal::at($line->debit, 4), Decimal::at($line->credit, 4), 4),
            ];
        }

        // Get opening balance via single aggregate — no N+1.
        $openingBalance = Decimal::at(DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<', $startDate)
            ->selectRaw('COALESCE(SUM(jel.base_debit), 0) - COALESCE(SUM(jel.base_credit), 0) as balance')
            ->first()
            ->balance ?? 0, 4);

        $netCashChange = bcadd(
            bcadd($cashFlow['operating'], $cashFlow['investing'], 4),
            $cashFlow['financing'],
            4
        );

        $closingBalance = bcadd($openingBalance, $netCashChange, 4);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'opening_balance' => (float) $openingBalance,
            'operating_activities' => [
                'items' => $activities['operating'],
                'total' => (float) $cashFlow['operating'],
            ],
            'investing_activities' => [
                'items' => $activities['investing'],
                'total' => (float) $cashFlow['investing'],
            ],
            'financing_activities' => [
                'items' => $activities['financing'],
                'total' => (float) $cashFlow['financing'],
            ],
            'net_cash_change' => (float) $netCashChange,
            'closing_balance' => (float) $closingBalance,
        ];
    }

    /**
     * The cash flow section a movement belongs to, read from the document that
     * produced its journal entry.
     *
     * Anything the classification does not recognise — a manual entry, an
     * opening balance, a capital injection — is treated as financing.
     */
    private function cashFlowActivity(?string $sourceType): string
    {
        $sourceType ??= '';

        return match (true) {
            str_contains($sourceType, 'Invoice'),
            str_contains($sourceType, 'Bill'),
            str_contains($sourceType, 'Payment') => 'operating',
            str_contains($sourceType, 'Asset'),
            str_contains($sourceType, 'Depreciation') => 'investing',
            default => 'financing',
        };
    }

    /**
     * Get Accounts Receivable Aging report.
     */
    public function getReceivableAging(?string $asOfDate = null): array
    {
        $today = $asOfDate ? Carbon::parse($asOfDate) : now();
        $orgId = auth()->user()->organization_id;

        [$bucketExpr, $bucketDates] = $this->agingBucketExpression($today);

        $baseQuery = Invoice::where('invoices.organization_id', $orgId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('amount_due', '>', 0);

        // Summary via single GROUP BY query
        $bucketRows = (clone $baseQuery)
            ->selectRaw(
                "{$bucketExpr} as bucket, SUM(invoices.amount_due * invoices.exchange_rate) as total",
                $bucketDates
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        // Detail: top 200, longest overdue first — no full table scan
        $details = (clone $baseQuery)
            ->join('contacts as ar_cust', 'ar_cust.id', '=', 'invoices.customer_id', 'left')
            ->selectRaw('
                invoices.id as invoice_id, invoices.invoice_number,
                invoices.customer_id, invoices.customer_name,
                COALESCE(ar_cust.company_name, invoices.customer_name) as customer_display,
                invoices.invoice_date, invoices.due_date,
                invoices.currency_code,
                invoices.total, invoices.amount_due,
                (invoices.amount_due * invoices.exchange_rate) as base_amount_due
            ')
            ->orderBy('invoices.due_date')
            ->limit(200)
            ->get()
            ->map(fn ($row) => $this->withAging($row, $today))
            ->toArray();

        return [
            'as_of_date' => $today->format('Y-m-d'),
            'summary' => $this->agingSummary($bucketRows),
            'details' => $details,
        ];
    }

    /**
     * Get Accounts Payable Aging report.
     */
    public function getPayableAging(?string $asOfDate = null): array
    {
        $today = $asOfDate ? Carbon::parse($asOfDate) : now();
        $orgId = auth()->user()->organization_id;

        [$bucketExpr, $bucketDates] = $this->agingBucketExpression($today);

        $apBase = Bill::where('bills.organization_id', $orgId)
            ->whereIn('status', ['approved', 'partial', 'overdue'])
            ->where('amount_due', '>', 0);

        // Summary via single GROUP BY query
        $apBuckets = (clone $apBase)
            ->selectRaw(
                "{$bucketExpr} as bucket, SUM(bills.amount_due * bills.exchange_rate) as total",
                $bucketDates
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        // Detail: top 200, longest overdue first
        $apDetails = (clone $apBase)
            ->join('contacts as ap_sup', 'ap_sup.id', '=', 'bills.supplier_id', 'left')
            ->selectRaw('
                bills.id as bill_id, bills.bill_number,
                bills.supplier_id, bills.supplier_name,
                COALESCE(ap_sup.company_name, bills.supplier_name) as supplier_display,
                bills.bill_date, bills.due_date,
                bills.currency_code,
                bills.total, bills.amount_due,
                (bills.amount_due * bills.exchange_rate) as base_amount_due
            ')
            ->orderBy('bills.due_date')
            ->limit(200)
            ->get()
            ->map(fn ($row) => $this->withAging($row, $today))
            ->toArray();

        return [
            'as_of_date' => $today->format('Y-m-d'),
            'summary' => $this->agingSummary($apBuckets),
            'details' => $apDetails,
        ];
    }

    /**
     * The ageing summary for the amounts already grouped into buckets.
     *
     * The bucket figures are base-currency amounts due, so a chart carrying
     * documents in several currencies still adds up to one figure, and the
     * total is summed as decimals rather than through float addition.
     *
     * @param  Collection<string, mixed>  $buckets
     */
    private function agingSummary(Collection $buckets): array
    {
        $total = Decimal::zero(4);

        foreach ($buckets as $amount) {
            $total = bcadd($total, Decimal::at($amount, 4), 4);
        }

        return [
            'current' => (float) Decimal::at($buckets['current'] ?? 0, 4),
            '1_30_days' => (float) Decimal::at($buckets['1_30'] ?? 0, 4),
            '31_60_days' => (float) Decimal::at($buckets['31_60'] ?? 0, 4),
            '61_90_days' => (float) Decimal::at($buckets['61_90'] ?? 0, 4),
            'over_90_days' => (float) Decimal::at($buckets['over_90'] ?? 0, 4),
            'total' => (float) $total,
        ];
    }

    /**
     * The aging bucket a due_date falls in, as SQL plus its bindings.
     *
     * Comparing due_date against four dates rather than subtracting it from
     * today keeps the expression to standard SQL, which every driver runs.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function agingBucketExpression(Carbon $today): array
    {
        $sql = "CASE
            WHEN due_date >= ? THEN 'current'
            WHEN due_date >= ? THEN '1_30'
            WHEN due_date >= ? THEN '31_60'
            WHEN due_date >= ? THEN '61_90'
            ELSE 'over_90'
        END";

        return [$sql, [
            $today->toDateString(),
            $today->copy()->subDays(30)->toDateString(),
            $today->copy()->subDays(60)->toDateString(),
            $today->copy()->subDays(90)->toDateString(),
        ]];
    }

    /**
     * Add days_overdue and aging_bucket to one detail row.
     */
    private function withAging(object $row, Carbon $today): array
    {
        $due = $row->due_date ? Carbon::parse($row->due_date) : null;
        $daysOverdue = $due ? (int) max(0, $due->startOfDay()->diffInDays($today->copy()->startOfDay(), false)) : 0;

        return array_merge((array) $row->getAttributes(), [
            'days_overdue' => $daysOverdue,
            'aging_bucket' => $this->getAgingBucket($daysOverdue),
        ]);
    }

    /**
     * Get aging bucket for days overdue.
     */
    protected function getAgingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => 'over_90',
        };
    }

    /**
     * Get Trial Balance.
     *
     * Single aggregate query (GROUP BY account) instead of loading all accounts
     * and their journal lines into PHP memory.
     */
    public function getTrialBalance(Carbon $asOfDate): array
    {
        $orgId = auth()->user()->organization_id;

        // One query: sum debits/credits per account up to $asOfDate. The base
        // columns hold every entry converted at its own rate, so a chart
        // carrying foreign-currency entries still adds up, and the figures
        // reconcile against the balance sheet and the P&L, which read the same
        // columns.
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->join('chart_of_accounts as coa', 'jel.account_id', '=', 'coa.id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<=', $asOfDate)
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.account_type', 'coa.sub_type')
            ->orderBy('coa.code')
            ->select([
                'coa.id as account_id',
                'coa.code',
                'coa.name',
                'coa.account_type',
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
            ])
            ->get();

        $lines = [];
        $totalDebit = Decimal::zero(4);
        $totalCredit = Decimal::zero(4);

        foreach ($rows as $row) {
            $debit = Decimal::at($row->total_debit, 4);
            $credit = Decimal::at($row->total_credit, 4);

            if (bccomp($debit, '0', 4) === 0 && bccomp($credit, '0', 4) === 0) {
                continue;
            }

            // A trial balance states the net of an account on the side it falls:
            // debits over credits is a debit balance whatever the account type,
            // so the two columns sum to the same figure on a balanced ledger.
            // Netting by the account's normal side instead would post every
            // normal balance, income and liabilities included, to the debit
            // column.
            $balance = bcsub($debit, $credit, 4);

            $balanceDebit = bccomp($balance, '0', 4) > 0 ? $balance : Decimal::zero(4);
            $balanceCredit = bccomp($balance, '0', 4) < 0 ? bcsub('0', $balance, 4) : Decimal::zero(4);

            $lines[] = [
                'account_id' => $row->account_id,
                'account_code' => $row->code,
                'account_name' => $row->name,
                'account_type' => $row->account_type,
                'debit' => (float) $balanceDebit,
                'credit' => (float) $balanceCredit,
            ];

            $totalDebit = bcadd($totalDebit, $balanceDebit, 4);
            $totalCredit = bcadd($totalCredit, $balanceCredit, 4);
        }

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'lines' => $lines,
            'totals' => [
                'debit' => (float) $totalDebit,
                'credit' => (float) $totalCredit,
            ],
            'is_balanced' => bccomp($totalDebit, $totalCredit, 2) === 0,
        ];
    }

    /**
     * Actual vs Budget variance report.
     *
     * Returns all active budgets for the organisation with per-line variance
     * (budgeted, committed, actual, variance_amount, variance_pct, is_over_budget).
     *
     * Optional filters:
     *   budget_type: annual|quarterly|project|department
     *   period_start / period_end: ISO date strings for budget period overlap
     *
     * @param  array{budget_type?: string, period_start?: string, period_end?: string}  $filters
     */
    public function getActualVsBudget(array $filters = []): array
    {
        $orgId = auth()->user()->organization_id;

        $query = Budget::where('organization_id', $orgId)
            ->whereIn('status', [Budget::STATUS_ACTIVE, Budget::STATUS_APPROVED, Budget::STATUS_CLOSED])
            ->with(['lines.account', 'fiscalYear']);

        if (! empty($filters['budget_type'])) {
            $query->where('budget_type', $filters['budget_type']);
        }

        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            $query->forPeriod($filters['period_start'], $filters['period_end']);
        }

        $result = [];

        // chunkById processes 50 budgets at a time — avoids loading all lines into memory at once
        $query->chunkById(50, function ($budgets) use (&$result) {
            foreach ($budgets as $budget) {
                $totalBudgeted = '0.00';
                $totalCommitted = '0.00';
                $totalActual = '0.00';

                $lines = $budget->lines->map(function ($line) use (&$totalBudgeted, &$totalCommitted, &$totalActual) {
                    $budgeted = (string) $line->total_amount;
                    $committed = (string) $line->committed_amount;
                    $actual = (string) $line->actual_amount;
                    $variance = bcsub($budgeted, $actual, 2);
                    $variancePct = bccomp($budgeted, '0', 2) > 0
                        ? round((float) bcdiv($variance, $budgeted, 6) * 100, 2)
                        : 0.0;

                    $totalBudgeted = bcadd($totalBudgeted, $budgeted, 2);
                    $totalCommitted = bcadd($totalCommitted, $committed, 2);
                    $totalActual = bcadd($totalActual, $actual, 2);

                    return [
                        'account_code' => $line->account?->code,
                        'account_name' => $line->account?->name,
                        'budget_amount' => (float) $budgeted,
                        'committed' => (float) $committed,
                        'actual' => (float) $actual,
                        'available' => (float) bcsub(bcsub($budgeted, $committed, 2), $actual, 2),
                        'variance_amount' => (float) $variance,
                        'variance_pct' => $variancePct,
                        'is_over_budget' => bccomp($actual, $budgeted, 2) > 0,
                    ];
                });

                $totalVariance = bcsub($totalBudgeted, $totalActual, 2);
                $totalVariancePct = bccomp($totalBudgeted, '0', 2) > 0
                    ? round((float) bcdiv($totalVariance, $totalBudgeted, 6) * 100, 2)
                    : 0.0;

                $result[] = [
                    'budget_id' => $budget->id,
                    'budget_uuid' => $budget->uuid,
                    'name' => $budget->name,
                    'budget_type' => $budget->budget_type,
                    'status' => $budget->status,
                    'period_start' => $budget->period_start?->format('Y-m-d'),
                    'period_end' => $budget->period_end?->format('Y-m-d'),
                    'fiscal_year' => $budget->fiscalYear?->name,
                    'total_budgeted' => (float) $totalBudgeted,
                    'total_committed' => (float) $totalCommitted,
                    'total_actual' => (float) $totalActual,
                    'total_variance' => (float) $totalVariance,
                    'total_variance_pct' => $totalVariancePct,
                    'utilization_pct' => $budget->getUtilizationPercent(),
                    'lines' => $lines,
                ];
            }
        });

        // Sort descending by period_start after chunking (chunkById uses id ordering internally)
        usort($result, fn ($a, $b) => strcmp((string) $b['period_start'], (string) $a['period_start']));

        return [
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'budgets' => $result,
        ];
    }
}
