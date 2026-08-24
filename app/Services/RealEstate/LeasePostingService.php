<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\Accounting\Account;
use App\Models\RealEstate\LeaseContract;
use App\Models\RealEstate\PostingRun;
use App\Models\RealEstate\PostingRunItem;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Periodic rent posting: turns the active contracts' conditions into a posting
 * run for a given month, and books the total to the general ledger.
 */
class LeasePostingService
{
    private const VAT_RATE = '0.15';

    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Calculate what a posting run would produce, without writing anything.
     *
     * @return array<string, mixed>
     */
    public function simulatePostingRun(int $organizationId, string $type, int $year, int $month): array
    {
        $items       = [];
        $totalAmount = '0.0000';
        $postingDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        LeaseContract::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->with(['activeConditions', 'rentalUnit'])
            ->chunkById(100, function ($contracts) use ($type, &$items, &$totalAmount) {
                foreach ($contracts as $contract) {
                    foreach ($contract->activeConditions as $condition) {
                        if ($type !== 'all' && $condition->condition_type !== $type) {
                            continue;
                        }

                        $amount      = $condition->computeAmount((float) $contract->rentalUnit?->area_sqm ?? 0);
                        $taxAmount   = $condition->is_taxable ? bcmul($amount, self::VAT_RATE, 4) : '0.0000';
                        $totalLine   = bcadd($amount, $taxAmount, 4);
                        $totalAmount = bcadd($totalAmount, $totalLine, 4);

                        $items[] = [
                            'contract_number' => $contract->contract_number,
                            'unit_code'       => $contract->rentalUnit?->code,
                            'condition_type'  => $condition->condition_type,
                            'amount'          => $amount,
                            'tax_amount'      => $taxAmount,
                            'total_amount'    => $totalLine,
                        ];
                    }
                }
            });

        return [
            'organization_id'      => $organizationId,
            'type'                 => $type,
            'period_year'          => $year,
            'period_month'         => $month,
            'posting_date'         => $postingDate,
            'contracts_to_process' => count(array_unique(array_column($items, 'contract_number'))),
            'total_amount'         => $totalAmount,
            'items'                => $items,
        ];
    }

    /**
     * Post the run for real. One posted run per type and period is allowed.
     *
     * @throws RuntimeException when the period has already been posted.
     */
    public function executePostingRun(int $organizationId, string $type, int $year, int $month): PostingRun
    {
        $existing = PostingRun::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'posted')
            ->first();

        if ($existing) {
            throw new RuntimeException("Posting run for {$type} {$year}/{$month} already exists (#{$existing->run_number}).");
        }

        return DB::transaction(function () use ($organizationId, $type, $year, $month) {
            $runNumber   = $this->numberGenerator->generate('RE-RUN', null, $organizationId);
            $postingDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $run = PostingRun::create([
                'organization_id' => $organizationId,
                'run_number'      => $runNumber,
                'type'            => $type,
                'posting_date'    => $postingDate,
                'period_year'     => $year,
                'period_month'    => $month,
                'status'          => 'draft',
                'currency_code'   => 'SAR',
            ]);

            $totalAmount        = '0.0000';
            $contractsProcessed = 0;

            LeaseContract::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->with(['activeConditions', 'rentalUnit'])
                ->chunkById(100, function ($contracts) use ($run, $type, &$totalAmount, &$contractsProcessed) {
                    foreach ($contracts as $contract) {
                        $contractHasItems = false;

                        foreach ($contract->activeConditions as $condition) {
                            if ($type !== 'all' && $condition->condition_type !== $type) {
                                continue;
                            }

                            $amount    = $condition->computeAmount((float) $contract->rentalUnit?->area_sqm ?? 0);
                            $taxAmount = $condition->is_taxable ? bcmul($amount, self::VAT_RATE, 4) : '0.0000';
                            $totalLine = bcadd($amount, $taxAmount, 4);

                            PostingRunItem::create([
                                'posting_run_id' => $run->id,
                                'contract_id'    => $contract->id,
                                'condition_id'   => $condition->id,
                                'condition_type' => $condition->condition_type,
                                'amount'         => $amount,
                                'tax_amount'     => $taxAmount,
                                'total_amount'   => $totalLine,
                                'status'         => 'posted',
                            ]);

                            $totalAmount      = bcadd($totalAmount, $totalLine, 4);
                            $contractHasItems = true;
                        }

                        if ($contractHasItems) {
                            $contractsProcessed++;
                        }
                    }
                });

            $run->update([
                'status'              => 'posted',
                'contracts_processed' => $contractsProcessed,
                'total_amount'        => $totalAmount,
                'executed_by'         => Auth::id(),
                'executed_at'         => now(),
            ]);

            $this->postRunToGeneralLedger($run, $organizationId, $postingDate, $totalAmount);

            return $run->load('items');
        });
    }

    /**
     * Book the run as debit receivables / credit rental income.
     *
     * The posting run itself stands on its own, so a missing account or a
     * journal failure is logged rather than rolled back.
     */
    private function postRunToGeneralLedger(PostingRun $run, int $organizationId, string $postingDate, string $totalAmount): void
    {
        if (bccomp($totalAmount, '0', 4) <= 0) {
            return;
        }

        $arAccount = Account::where('organization_id', $organizationId)
            ->whereIn('type', ['receivable', 'asset'])
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'receivable' THEN 0 ELSE 1 END")
            ->first();

        $incomeAccount = Account::where('organization_id', $organizationId)
            ->whereIn('type', ['income', 'revenue'])
            ->where('is_active', true)
            ->first();

        if ($arAccount === null || $incomeAccount === null) {
            Log::info('Rent posting run: GL accounts not configured, skipping journal entry', [
                'run_number' => $run->run_number,
                'ar_found'   => $arAccount !== null,
                'inc_found'  => $incomeAccount !== null,
            ]);

            return;
        }

        try {
            $this->journalService->createEntry(
                entryData: [
                    'organization_id'  => $organizationId,
                    'entry_date'       => $postingDate,
                    'reference_type'   => 're_posting_run',
                    'reference_id'     => $run->id,
                    'reference_number' => $run->run_number,
                    'description'      => "Rent posting run {$run->run_number}",
                    'currency_code'    => $run->currency_code ?? 'SAR',
                    'status'           => 'posted',
                    'created_by'       => Auth::id(),
                ],
                lines: [
                    [
                        'account_id'  => $arAccount->id,
                        'debit'       => $totalAmount,
                        'credit'      => 0,
                        'description' => "Rent receivable — {$run->run_number}",
                        'line_order'  => 0,
                    ],
                    [
                        'account_id'  => $incomeAccount->id,
                        'debit'       => 0,
                        'credit'      => $totalAmount,
                        'description' => "Rental income — {$run->run_number}",
                        'line_order'  => 1,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Rent posting run: GL journal entry failed', [
                'run_number' => $run->run_number,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
