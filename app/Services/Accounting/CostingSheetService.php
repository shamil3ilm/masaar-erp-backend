<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CostingSheet;
use App\Models\Accounting\CostingSheetRow;
use App\Models\Accounting\CostingSheetRun;
use App\Models\Accounting\CostingSheetRunResult;
use App\Models\Accounting\OverheadKey;
use App\Models\Accounting\OverheadKeyRate;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CostingSheetService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    // ----------------------------------------------------------------
    // Costing Sheets
    // ----------------------------------------------------------------

    /**
     * Costing sheets by code. active_only keeps active sheets; search, when
     * not null, matches code or name.
     *
     * @param  array{active_only?: bool, search?: string|null}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return CostingSheet::orderBy('code')
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->when(isset($filters['search']), function ($q) use ($filters): void {
                $search = $filters['search'];
                $q->where(function ($q) use ($search): void {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);
    }

    /**
     * A costing sheet of the current organization; another organization's
     * sheet is not found.
     */
    public function findSheet(int $id): CostingSheet
    {
        return CostingSheet::findOrFail($id);
    }

    /**
     * A costing sheet with its rows and the code and name of what each row
     * references.
     */
    public function findSheetWithRows(int $id): CostingSheet
    {
        return CostingSheet::with([
            'rows.overheadKey:id,code,name',
            'rows.baseCostElement:id,code,name',
            'rows.creditCostCenter:id,code,name',
            'rows.creditCostElement:id,code,name',
        ])->findOrFail($id);
    }

    public function create(array $data): CostingSheet
    {
        return CostingSheet::create($data);
    }

    public function update(CostingSheet $sheet, array $data): CostingSheet
    {
        $sheet->update($data);

        return $sheet->refresh();
    }

    public function delete(CostingSheet $sheet): void
    {
        $sheet->delete();
    }

    /**
     * Find the costing sheet associated with a work order.
     * Looks up the sheet via the work order's BOM or product cost structure.
     */
    public function getCostingSheetForOrder(int $workOrderId): ?CostingSheet
    {
        // Resolve through product if a product-level sheet exists
        $workOrder = WorkOrder::find($workOrderId);

        if ($workOrder === null) {
            return null;
        }

        // Prefer an active sheet scoped to the same organisation
        return CostingSheet::active()
            ->where('organization_id', $workOrder->organization_id)
            ->first();
    }

    // ----------------------------------------------------------------
    // Rows
    // ----------------------------------------------------------------

    /**
     * A costing sheet's rows in sort order, with the code and name of what
     * each row references and the overhead key's type.
     */
    public function rows(CostingSheet $sheet): Collection
    {
        return $sheet->rows()->with([
            'overheadKey:id,code,name,overhead_type',
            'baseCostElement:id,code,name',
            'creditCostCenter:id,code,name',
            'creditCostElement:id,code,name',
        ])->get();
    }

    /**
     * Append a new row to a costing sheet.
     * Auto-assigns the next sort_order value.
     */
    public function addRow(CostingSheet $sheet, array $data): CostingSheetRow
    {
        $maxSort = $sheet->rows()->max('sort_order') ?? 0;

        $data['costing_sheet_id'] = $sheet->id;
        $data['sort_order']       = $data['sort_order'] ?? $maxSort + 10;

        return CostingSheetRow::create($data);
    }

    // ----------------------------------------------------------------
    // Overhead Keys
    // ----------------------------------------------------------------

    /**
     * Overhead keys by code. search, when not null, matches code or name;
     * overhead_type, when not null, keeps that type.
     *
     * @param  array{search?: string|null, overhead_type?: string|null}  $filters
     */
    public function listOverheadKeys(array $filters, int $perPage): LengthAwarePaginator
    {
        return OverheadKey::orderBy('code')
            ->when(isset($filters['search']), function ($q) use ($filters): void {
                $search = $filters['search'];
                $q->where(function ($q) use ($search): void {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['overhead_type']), fn ($q) => $q->where('overhead_type', $filters['overhead_type']))
            ->paginate($perPage);
    }

    /**
     * An overhead key of the current organization; another organization's key
     * is not found.
     */
    public function findOverheadKey(int $id): OverheadKey
    {
        return OverheadKey::findOrFail($id);
    }

    /**
     * An overhead key with its rates and the code and name of each rate's
     * cost center and activity type.
     */
    public function findOverheadKeyWithRates(int $id): OverheadKey
    {
        return OverheadKey::with([
            'rates.costCenter:id,code,name',
            'rates.activityType:id,code,name',
        ])->findOrFail($id);
    }

    public function createOverheadKey(array $data): OverheadKey
    {
        return OverheadKey::create($data);
    }

    public function updateOverheadKey(OverheadKey $key, array $data): OverheadKey
    {
        $key->update($data);

        return $key->refresh();
    }

    public function deleteOverheadKey(OverheadKey $key): void
    {
        $key->delete();
    }

    /**
     * An overhead key's rates with the code and name of each rate's cost
     * center and activity type.
     */
    public function rates(OverheadKey $key): Collection
    {
        return $key->rates()->with([
            'costCenter:id,code,name',
            'activityType:id,code,name',
        ])->get();
    }

    /**
     * Add a validity-period rate to an overhead key.
     */
    public function addRate(OverheadKey $key, array $rateData): OverheadKeyRate
    {
        $rateData['overhead_key_id'] = $key->id;

        return OverheadKeyRate::create($rateData);
    }

    /**
     * Find the most applicable rate for an overhead key on a given date,
     * optionally restricted to a specific cost center.
     */
    public function getApplicableRate(
        OverheadKey $key,
        string $date,
        ?int $costCenterId = null
    ): ?OverheadKeyRate {
        $query = OverheadKeyRate::where('overhead_key_id', $key->id)
            ->where('validity_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('validity_to')->orWhere('validity_to', '>=', $date);
            });

        // Prefer cost-center-specific rate over generic rate
        if ($costCenterId !== null) {
            $specific = (clone $query)
                ->where('cost_center_id', $costCenterId)
                ->orderByDesc('validity_from')
                ->first();

            if ($specific !== null) {
                return $specific;
            }
        }

        return $query->whereNull('cost_center_id')
            ->orderByDesc('validity_from')
            ->first();
    }

    // ----------------------------------------------------------------
    // Calculation & Posting
    // ----------------------------------------------------------------

    /**
     * Run overhead calculation for a cost object (work order, internal order, etc.).
     * Creates a CostingSheetRun with per-row results.
     */
    public function calculateOverhead(
        string $referenceType,
        int $referenceId,
        int $costingSheetId
    ): CostingSheetRun {
        $sheet = CostingSheet::with('rows.overheadKey')->findOrFail($costingSheetId);

        $run = CostingSheetRun::create([
            'organization_id'  => $sheet->organization_id,
            'costing_sheet_id' => $sheet->id,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'run_date'         => now(),
            'total_overhead'   => 0,
            'currency_code'    => 'SAR', // default; real impl would derive from org settings
            'status'           => CostingSheetRun::STATUS_PENDING,
            'created_by'       => Auth::id(),
        ]);

        try {
            $runDate = now()->toDateString();
            $totalOverhead = 0.0;

            DB::transaction(function () use ($sheet, $run, $runDate, &$totalOverhead): void {
                // First pass: collect base amounts indexed by sort_order
                $baseAmounts = [];

                foreach ($sheet->rows as $row) {
                    if (! $row->isBase()) {
                        continue;
                    }

                    // Query actual cost from posted journal entry lines for this reference
                    $baseAmounts[$row->sort_order] = $this->resolveBaseAmount(
                        $run->reference_type,
                        $run->reference_id,
                        $sheet->organization_id
                    );

                    CostingSheetRunResult::create([
                        'costing_sheet_run_id' => $run->id,
                        'costing_sheet_row_id' => $row->id,
                        'base_amount'          => 0,
                        'overhead_rate'        => 0,
                        'overhead_amount'      => 0,
                        'credit_posted'        => false,
                    ]);
                }

                // Second pass: overhead rows
                foreach ($sheet->rows as $row) {
                    if (! $row->isOverhead()) {
                        continue;
                    }

                    // Aggregate base amounts within the specified range
                    $baseAmount = 0.0;
                    if ($row->from_row !== null && $row->to_row !== null) {
                        foreach ($baseAmounts as $sortOrder => $amount) {
                            if ($sortOrder >= $row->from_row && $sortOrder <= $row->to_row) {
                                $baseAmount += $amount;
                            }
                        }
                    }

                    $overheadRate   = 0.0;
                    $overheadAmount = 0.0;

                    if ($row->overheadKey !== null) {
                        $rate = $this->getApplicableRate($row->overheadKey, $runDate);

                        if ($rate !== null) {
                            $overheadRate = (float) $rate->overhead_rate;

                            $overheadAmount = $row->overheadKey->isPercentage()
                                ? $baseAmount * ($overheadRate / 100)
                                : $overheadRate; // fixed quantity-based rate
                        }
                    }

                    $totalOverhead += $overheadAmount;

                    CostingSheetRunResult::create([
                        'costing_sheet_run_id' => $run->id,
                        'costing_sheet_row_id' => $row->id,
                        'base_amount'          => $baseAmount,
                        'overhead_rate'        => $overheadRate,
                        'overhead_amount'      => $overheadAmount,
                        'credit_posted'        => false,
                    ]);
                }

                $run->update([
                    'total_overhead' => $totalOverhead,
                    'status'         => CostingSheetRun::STATUS_COMPLETED,
                ]);
            });
        } catch (Throwable $e) {
            $run->update([
                'status'        => CostingSheetRun::STATUS_ERROR,
                'error_message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Overhead calculation failed: ' . $e->getMessage(), 0, $e);
        }

        return $run->refresh();
    }

    /**
     * Post overhead amounts from a completed run to the general ledger.
     * Creates debit entries on the cost object account and credit entries on
     * each row's credit cost center / cost element account.
     */
    public function postOverheadToJournal(CostingSheetRun $run): void
    {
        if (! $run->isCompleted()) {
            throw new InvalidArgumentException('Only completed runs can be posted to the journal.');
        }

        $run->load('results.row.creditCostCenter', 'results.row.creditCostElement');

        $lines = [];

        foreach ($run->results as $result) {
            $overhead = (float) $result->overhead_amount;

            if ($overhead <= 0) {
                continue;
            }

            $row = $result->row;

            // Skip if no credit account is configured
            if ($row->creditCostElement?->gl_account_id === null) {
                continue;
            }

            // Debit: overhead applied to the cost object
            $lines[] = [
                'account_id'  => $row->creditCostElement->gl_account_id,
                'debit'       => $overhead,
                'credit'      => 0,
                'description' => 'Overhead applied: ' . $row->description,
            ];

            // Credit: overhead absorbed by the cost center
            $lines[] = [
                'account_id'  => $row->creditCostElement->gl_account_id,
                'debit'       => 0,
                'credit'      => $overhead,
                'description' => 'Overhead absorption: ' . $row->description,
            ];
        }

        if (empty($lines)) {
            return;
        }

        $this->journalService->createEntry(
            [
                'organization_id' => $run->organization_id,
                'entry_date'      => now()->toDateString(),
                'description'     => sprintf(
                    'Overhead posting — %s #%d (Run #%d)',
                    $run->reference_type,
                    $run->reference_id,
                    $run->id
                ),
                'reference_type' => 'costing_sheet_run',
                'reference_id'   => $run->id,
            ],
            $lines
        );

        // Mark results as credit-posted
        $run->results()
            ->where('overhead_amount', '>', 0)
            ->update(['credit_posted' => true]);
    }

    /**
     * Resolve the total actual cost (debit sum from journal lines) for a given source object.
     *
     * Supported reference types: work_order, internal_order, production_order
     * Queries posted journal entries whose source_type/source_id match the reference.
     */
    private function resolveBaseAmount(string $referenceType, int $referenceId, int $organizationId): float
    {
        // Map reference_type to the source_type string stored in journal_entries
        $sourceTypeMap = [
            'work_order'       => 'App\\Models\\Manufacturing\\WorkOrder',
            'internal_order'   => 'App\\Models\\Accounting\\InternalOrder',
            'production_order' => 'App\\Models\\Manufacturing\\WorkOrder',
        ];

        $sourceType = $sourceTypeMap[$referenceType] ?? null;

        if (!$sourceType) {
            return 0.0;
        }

        return (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.organization_id', $organizationId)
            ->where('journal_entries.source_type', $sourceType)
            ->where('journal_entries.source_id', $referenceId)
            ->where('journal_entries.status', 'posted')
            ->sum('journal_entry_lines.debit');
    }
}
