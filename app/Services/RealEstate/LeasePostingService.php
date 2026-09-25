<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\RealEstate\ContractCondition;
use App\Models\RealEstate\PostingRun;
use App\Models\RealEstate\PostingRunItem;
use App\Models\RealEstate\RentalContract;
use App\Models\Tax\TaxCategory;
use App\Models\Tax\TaxRate;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Tax\TaxCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Periodic rent posting: turns the active contracts' conditions into a posting
 * run for a given month, and books the total to the general ledger.
 *
 * A taxable condition is charged whatever the organization's tax scheme
 * charges, read through the tax calculator, so a Saudi lease carries Saudi VAT
 * and an Indian one GST. A taxable condition whose rate cannot be determined
 * stops the run instead of being posted untaxed.
 */
class LeasePostingService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly JournalService $journalService,
        private readonly TaxCalculatorService $taxCalculator,
    ) {}

    /**
     * Calculate what a posting run would produce, without writing anything.
     *
     * @return array<string, mixed>
     */
    public function simulatePostingRun(int $organizationId, string $type, int $year, int $month): array
    {
        $organization = Organization::findOrFail($organizationId);
        $items = [];
        $totalAmount = '0.0000';
        $postingDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        RentalContract::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->with(['activeConditions', 'rentalUnit'])
            ->chunkById(100, function ($contracts) use ($organization, $type, &$items, &$totalAmount) {
                foreach ($this->chargesFor($organization, $contracts, $type) as $charge) {
                    $totalAmount = bcadd($totalAmount, $charge['total'], 4);

                    $items[] = [
                        'contract_number' => $charge['contract']->contract_number,
                        'unit_code' => $charge['contract']->rentalUnit?->code,
                        'condition_type' => $charge['condition']->condition_type,
                        'amount' => $charge['amount'],
                        'tax_amount' => $charge['tax'],
                        'total_amount' => $charge['total'],
                    ];
                }
            });

        return [
            'organization_id' => $organizationId,
            'type' => $type,
            'period_year' => $year,
            'period_month' => $month,
            'posting_date' => $postingDate,
            'contracts_to_process' => count(array_unique(array_column($items, 'contract_number'))),
            'total_amount' => $totalAmount,
            'items' => $items,
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

        $organization = Organization::findOrFail($organizationId);

        return DB::transaction(function () use ($organization, $organizationId, $type, $year, $month) {
            $runNumber = $this->numberGenerator->generate('RE-RUN', null, $organizationId);
            $postingDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $run = PostingRun::create([
                'organization_id' => $organizationId,
                'run_number' => $runNumber,
                'type' => $type,
                'posting_date' => $postingDate,
                'period_year' => $year,
                'period_month' => $month,
                'status' => 'draft',
                'currency_code' => 'SAR',
            ]);

            $totalAmount = '0.0000';
            $contractsProcessed = 0;

            RentalContract::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->with(['activeConditions', 'rentalUnit'])
                ->chunkById(100, function ($contracts) use ($organization, $run, $type, &$totalAmount, &$contractsProcessed) {
                    $charged = [];

                    foreach ($this->chargesFor($organization, $contracts, $type) as $charge) {
                        PostingRunItem::create([
                            'posting_run_id' => $run->id,
                            'contract_id' => $charge['contract']->id,
                            'condition_id' => $charge['condition']->id,
                            'condition_type' => $charge['condition']->condition_type,
                            'amount' => $charge['amount'],
                            'tax_amount' => $charge['tax'],
                            'total_amount' => $charge['total'],
                            'status' => 'posted',
                        ]);

                        $totalAmount = bcadd($totalAmount, $charge['total'], 4);
                        $charged[$charge['contract']->id] = true;
                    }

                    $contractsProcessed += count($charged);
                });

            $run->update([
                'status' => 'posted',
                'contracts_processed' => $contractsProcessed,
                'total_amount' => $totalAmount,
                'executed_by' => Auth::id(),
                'executed_at' => now(),
            ]);

            $this->postRunToGeneralLedger($run, $organizationId, $postingDate, $totalAmount);

            return $run->load('items');
        });
    }

    /**
     * What the run charges for a chunk of contracts: every condition it
     * covers, the amount that condition charges and the tax on it.
     *
     * @param  iterable<RentalContract>  $contracts
     * @return list<array{contract: RentalContract, condition: ContractCondition, amount: string, tax: string, total: string}>
     */
    private function chargesFor(Organization $organization, iterable $contracts, string $type): array
    {
        $charges = [];

        foreach ($contracts as $contract) {
            foreach ($contract->activeConditions as $condition) {
                if ($type !== 'all' && $condition->condition_type !== $type) {
                    continue;
                }

                $charges[] = [
                    'contract' => $contract,
                    'condition' => $condition,
                    'amount' => $condition->computeAmount((float) $contract->rentalUnit?->area_sqm ?? 0),
                ];
            }
        }

        foreach ($this->taxOn($organization, $charges) as $index => $tax) {
            $charges[$index]['tax'] = $tax;
            $charges[$index]['total'] = bcadd($charges[$index]['amount'], $tax, 4);
        }

        return $charges;
    }

    /**
     * The tax on each charge, in the same order.
     *
     * The tax calculator applies the organization's tax scheme: VAT across the
     * GCC, CGST and SGST for India. A run stores one tax amount per condition
     * and an intra-state GST split adds up to the inter-state rate, so the
     * place of supply does not change what is posted.
     *
     * @param  list<array{condition: ContractCondition, amount: string}>  $charges
     * @return array<int, string>
     */
    private function taxOn(Organization $organization, array $charges): array
    {
        $taxes = array_fill(0, count($charges), bcadd('0', '0', 4));
        $taxable = array_filter($charges, static fn (array $charge): bool => (bool) $charge['condition']->is_taxable);

        if ($taxable === []) {
            return $taxes;
        }

        $rate = $this->standardRate($organization);
        $lines = [];

        foreach ($taxable as $index => $charge) {
            $lines[$index] = [
                'quantity' => '1',
                'unit_price' => $charge['amount'],
                'tax_code' => TaxCategory::CODE_STANDARD,
                'tax_rate' => $rate,
            ];
        }

        foreach ($this->taxCalculator->calculate($organization, $lines)->lines as $index => $line) {
            $taxes[$index] = bcadd((string) ($line['tax_amount'] ?? '0'), '0', 4);
        }

        return $taxes;
    }

    /**
     * The rate the organization charges on taxable rent.
     *
     * The rate it configured for the standard tax category in its own country
     * comes first. A VAT organization that configured none falls back to the
     * standard rate its scheme charges in that country, which is nothing in
     * the GCC states that levy no VAT. India's GST has no single standard rate
     * to fall back on, so an organization that configured none has no
     * determinable treatment for its rent.
     *
     * @throws RuntimeException when no rate can be determined
     */
    private function standardRate(Organization $organization): string
    {
        $categoryId = TaxCategory::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('code', TaxCategory::CODE_STANDARD)
            ->where('is_active', true)
            ->value('id');

        $configured = $categoryId === null ? null : TaxRate::where('tax_category_id', $categoryId)
            ->forCountry((string) $organization->country_code)
            ->effectiveOn()
            ->active()
            ->value('rate');

        if ($configured !== null) {
            return (string) $configured;
        }

        if ($organization->tax_scheme === 'VAT') {
            return (string) $organization->getStandardVatRate();
        }

        throw new RuntimeException(
            'Rent is taxable but no tax rate is configured for '
            . ($organization->country_code ?: 'the organization') . ' under its '
            . ($organization->tax_scheme ?: 'unset') . ' tax scheme.'
        );
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

        // There is no type column. receivable is a sub_type and asset is an
        // account_type, so the original filter matched neither and every lease
        // posting found no accounts. 'revenue' was not a value of either.
        $arAccount = Account::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('sub_type', Account::SUBTYPE_RECEIVABLE)
                    ->orWhere('account_type', Account::TYPE_ASSET);
            })
            ->orderByRaw('CASE WHEN sub_type = ? THEN 0 ELSE 1 END', [Account::SUBTYPE_RECEIVABLE])
            ->first();

        $incomeAccount = Account::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('account_type', Account::TYPE_INCOME)
            ->first();

        if ($arAccount === null || $incomeAccount === null) {
            Log::info('Rent posting run: GL accounts not configured, skipping journal entry', [
                'run_number' => $run->run_number,
                'ar_found' => $arAccount !== null,
                'inc_found' => $incomeAccount !== null,
            ]);

            return;
        }

        try {
            $this->journalService->createAndPost(
                entryData: [
                    'organization_id' => $organizationId,
                    'entry_date' => $postingDate,
                    'reference_type' => 're_posting_run',
                    'reference_id' => $run->id,
                    'reference_number' => $run->run_number,
                    'description' => "Rent posting run {$run->run_number}",
                    'currency_code' => $run->currency_code ?? 'SAR',
                    'created_by' => Auth::id(),
                ],
                lines: [
                    [
                        'account_id' => $arAccount->id,
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'description' => "Rent receivable — {$run->run_number}",
                        'line_order' => 0,
                    ],
                    [
                        'account_id' => $incomeAccount->id,
                        'debit' => 0,
                        'credit' => $totalAmount,
                        'description' => "Rental income — {$run->run_number}",
                        'line_order' => 1,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Rent posting run: GL journal entry failed', [
                'run_number' => $run->run_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
