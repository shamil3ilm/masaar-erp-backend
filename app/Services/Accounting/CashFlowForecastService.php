<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowLine;
use App\Models\Accounting\CashFlowScenario;
use App\Models\Accounting\Loan;
use App\Models\Accounting\LoanSchedule;
use App\Models\Core\Organization;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CashFlowForecastService
{
    /**
     * Generate a new cash flow forecast for the given organization.
     *
     * Sources:
     * - Inflows:  outstanding invoice due amounts (by due date)
     * - Outflows: open purchase order totals (by expected delivery / creation date)
     * - Outflows: upcoming loan schedule payments
     * - Opening:  sum of bank account balances
     */
    public function generateForecast(
        Organization $organization,
        int $horizonDays = 90,
        ?CashFlowScenario $scenario = null,
        string $currencyCode = 'SAR'
    ): CashFlowForecast {
        return DB::transaction(function () use ($organization, $horizonDays, $scenario, $currencyCode) {
            $today = now()->toDateString();
            $horizon = now()->addDays($horizonDays)->toDateString();

            // Opening balance — sum of all bank transaction running totals.
            // Use the most recent bank transaction balance per account as a proxy.
            $openingBalance = BankTransaction::where('organization_id', $organization->id)
                ->selectRaw('SUM(balance) as total')
                ->whereIn('id', function ($sub) {
                    $sub->selectRaw('MAX(id)')
                        ->from('bank_transactions')
                        ->groupBy('bank_account_id');
                })
                ->value('total') ?? 0;

            $forecast = CashFlowForecast::create([
                'organization_id' => $organization->id,
                'forecast_date' => $today,
                'horizon_days' => $horizonDays,
                'currency_code' => $currencyCode,
                'total_opening_balance' => $openingBalance,
                'total_inflows' => 0,
                'total_outflows' => 0,
                'closing_balance' => 0,
                'scenario_id' => $scenario?->id,
                'generated_at' => now(),
            ]);

            $totalInflows = '0';
            $totalOutflows = '0';

            // --- Inflows from outstanding invoices ---
            $invoices = Invoice::where('organization_id', $organization->id)
                ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->where('amount_due', '>', 0)
                ->whereBetween('due_date', [$today, $horizon])
                ->get();

            foreach ($invoices as $invoice) {
                $confidence = $invoice->status === Invoice::STATUS_OVERDUE
                    ? CashFlowLine::CONFIDENCE_POSSIBLE
                    : CashFlowLine::CONFIDENCE_PROBABLE;

                CashFlowLine::create([
                    'forecast_id' => $forecast->id,
                    'expected_date' => $invoice->due_date->toDateString(),
                    'flow_type' => CashFlowLine::TYPE_INFLOW,
                    'source_type' => CashFlowLine::SOURCE_INVOICE,
                    'source_id' => $invoice->id,
                    'description' => "Invoice #{$invoice->invoice_number}",
                    'amount' => $invoice->amount_due,
                    'confidence' => $confidence,
                    'is_actual' => false,
                ]);

                $totalInflows = bcadd($totalInflows, (string) $invoice->amount_due, 4);
            }

            // --- Outflows from open purchase orders ---
            $purchaseOrders = PurchaseOrder::where('organization_id', $organization->id)
                ->whereIn('status', ['approved', 'partial'])
                // A purchase order carries no payment record, so the whole of it
                // is still to be paid.
                ->where('total', '>', 0)
                ->whereBetween('expected_delivery_date', [$today, $horizon])
                ->get();

            foreach ($purchaseOrders as $po) {
                $dueDate = $po->expected_delivery_date ?? $po->created_at->toDateString();

                CashFlowLine::create([
                    'forecast_id' => $forecast->id,
                    'expected_date' => $dueDate,
                    'flow_type' => CashFlowLine::TYPE_OUTFLOW,
                    'source_type' => CashFlowLine::SOURCE_PURCHASE_ORDER,
                    'source_id' => $po->id,
                    'description' => "PO #{$po->order_number}",
                    'amount' => $po->total,
                    'confidence' => CashFlowLine::CONFIDENCE_PROBABLE,
                    'is_actual' => false,
                ]);

                $totalOutflows = bcadd($totalOutflows, (string) $po->total, 4);
            }

            // --- Outflows from loan schedules ---
            $loanSchedules = LoanSchedule::whereHas('loan', function ($q) use ($organization) {
                $q->where('organization_id', $organization->id)
                    ->whereIn('status', ['active', 'disbursed']);
            })
                ->where('status', 'pending')
                ->whereBetween('due_date', [$today, $horizon])
                ->with('loan')
                ->get();

            foreach ($loanSchedules as $schedule) {
                CashFlowLine::create([
                    'forecast_id' => $forecast->id,
                    'expected_date' => $schedule->due_date->toDateString(),
                    'flow_type' => CashFlowLine::TYPE_OUTFLOW,
                    'source_type' => CashFlowLine::SOURCE_LOAN,
                    'source_id' => $schedule->id,
                    'description' => "Loan installment #{$schedule->installment_number}",
                    'amount' => $schedule->total_amount,
                    'confidence' => CashFlowLine::CONFIDENCE_CERTAIN,
                    'is_actual' => false,
                ]);

                $totalOutflows = bcadd($totalOutflows, (string) $schedule->total_amount, 4);
            }

            $closingBalance = bcadd(
                bcsub((string) $openingBalance, $totalOutflows, 4),
                $totalInflows,
                4
            );

            $forecast->update([
                'total_inflows' => $totalInflows,
                'total_outflows' => $totalOutflows,
                'closing_balance' => $closingBalance,
            ]);

            return $forecast->fresh(['lines']);
        });
    }

    /**
     * Refresh an existing forecast by deleting its lines and regenerating.
     */
    public function refreshForecast(CashFlowForecast $forecast): CashFlowForecast
    {
        return DB::transaction(function () use ($forecast) {
            $forecast->lines()->delete();

            $organization = $forecast->organization;
            $scenario = $forecast->scenario;

            $fresh = $this->generateForecast(
                $organization,
                $forecast->horizon_days,
                $scenario,
                $forecast->currency_code
            );

            $forecast->delete();

            return $fresh;
        });
    }

    /**
     * An organization's forecasts, latest first, filtered by a non-empty scenario id.
     */
    public function listForecasts(int $organizationId, mixed $scenarioId, int $perPage = 15): LengthAwarePaginator
    {
        return CashFlowForecast::where('organization_id', $organizationId)
            ->with(['scenario'])
            ->when($scenarioId, fn ($q, $v) => $q->where('scenario_id', $v))
            ->orderByDesc('forecast_date')
            ->paginate($perPage);
    }

    /**
     * A forecast's lines by expected date. Each filter applies only when its
     * value is non-empty.
     *
     * @param  array{flow_type?: mixed, confidence?: mixed, source_type?: mixed}  $filters
     */
    public function listLines(CashFlowForecast $forecast, array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return $forecast->lines()
            ->when($filters['flow_type'] ?? null, fn ($q, $v) => $q->where('flow_type', $v))
            ->when($filters['confidence'] ?? null, fn ($q, $v) => $q->where('confidence', $v))
            ->when($filters['source_type'] ?? null, fn ($q, $v) => $q->where('source_type', $v))
            ->orderBy('expected_date')
            ->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Scenarios
    // -------------------------------------------------------------------------

    public function findScenario(int|string $id): CashFlowScenario
    {
        return CashFlowScenario::findOrFail($id);
    }

    /**
     * An organization's scenarios, the base case first, then by name.
     */
    public function listScenarios(int $organizationId): Collection
    {
        return CashFlowScenario::where('organization_id', $organizationId)
            ->with(['creator'])
            ->orderByDesc('is_base_case')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a scenario. A new base case takes over from the organization's
     * current one, in the same transaction, so there is never more than one.
     */
    public function createScenario(int $organizationId, array $data, ?int $createdBy): CashFlowScenario
    {
        return DB::transaction(function () use ($organizationId, $data, $createdBy): CashFlowScenario {
            if (! empty($data['is_base_case'])) {
                CashFlowScenario::where('organization_id', $organizationId)
                    ->where('is_base_case', true)
                    ->update(['is_base_case' => false]);
            }

            return CashFlowScenario::create(array_merge($data, [
                'organization_id' => $organizationId,
                'created_by'      => $createdBy,
            ]));
        });
    }

    /**
     * Update a scenario. Making it the base case demotes the organization's
     * other base case in the same transaction.
     */
    public function updateScenario(CashFlowScenario $scenario, array $data): CashFlowScenario
    {
        return DB::transaction(function () use ($scenario, $data): CashFlowScenario {
            if (! empty($data['is_base_case'])) {
                CashFlowScenario::where('organization_id', $scenario->organization_id)
                    ->where('is_base_case', true)
                    ->where('id', '!=', $scenario->id)
                    ->update(['is_base_case' => false]);
            }

            $scenario->update($data);

            return $scenario->fresh();
        });
    }

    /**
     * Return a period-bucketed summary (30 / 60 / 90-day buckets).
     */
    public function getPeriodSummary(CashFlowForecast $forecast): array
    {
        $buckets = [30 => [], 60 => [], 90 => []];

        foreach (array_keys($buckets) as $days) {
            $from = now()->toDateString();
            $to = now()->addDays($days)->toDateString();

            $inflows = $forecast->lines()
                ->inflows()
                ->forDateRange($from, $to)
                ->sum('amount');

            $outflows = $forecast->lines()
                ->outflows()
                ->forDateRange($from, $to)
                ->sum('amount');

            $buckets[$days] = [
                'period_days' => $days,
                'inflows' => (float) $inflows,
                'outflows' => (float) $outflows,
                'net' => (float) bcsub((string) $inflows, (string) $outflows, 4),
            ];
        }

        return $buckets;
    }
}
