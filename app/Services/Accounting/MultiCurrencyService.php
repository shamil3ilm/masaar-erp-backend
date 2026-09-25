<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CurrencyRevaluation;
use App\Models\Accounting\CurrencyRevaluationItem;
use App\Models\Accounting\ExchangeRate;
use App\Models\Accounting\ForexGainLossEntry;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\OrganizationCurrency;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MultiCurrencyService
{
    /** The scale the revaluation amount columns hold. */
    private const SCALE = 4;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly NumberGeneratorService $numberGenerator,
        private readonly AccountResolver $accountResolver,
    ) {}
    /**
     * Add a currency to an organization.
     */
    public function addCurrency(array $data): OrganizationCurrency
    {
        return DB::transaction(function () use ($data) {
            // Check if already exists
            $existing = OrganizationCurrency::withoutGlobalScopes()
                ->where('organization_id', $data['organization_id'])
                ->where('currency_code', $data['currency_code'])
                ->first();

            if ($existing) {
                // Reactivate if was deactivated
                $existing->update(['is_active' => true]);
                return $existing->fresh();
            }

            return OrganizationCurrency::create($data);
        });
    }

    /**
     * Remove (deactivate) a currency from an organization.
     */
    public function removeCurrency(int $organizationId, string $currencyCode): OrganizationCurrency
    {
        $orgCurrency = OrganizationCurrency::where('organization_id', $organizationId)->where('currency_code', $currencyCode)->firstOrFail();

        if ($orgCurrency->is_base_currency) {
            throw new InvalidArgumentException('Cannot remove the base currency.');
        }

        $orgCurrency->update(['is_active' => false]);

        return $orgCurrency->fresh();
    }

    /**
     * Get all currencies for an organization.
     */
    public function getOrgCurrencies(int $organizationId, bool $activeOnly = true): Collection
    {
        $query = OrganizationCurrency::with(['currency', 'exchangeGainAccount', 'exchangeLossAccount'])
            ->where('organization_id', $organizationId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Whether the organization already holds the currency as an active one.
     * A deactivated currency does not count; adding it again reactivates it.
     */
    public function hasActiveCurrency(int $organizationId, string $currencyCode): bool
    {
        return OrganizationCurrency::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Newest revaluation date first. Filters are passed as the keys the caller
     * received, so a filter present with an empty value still applies; the
     * date range applies only when both ends are present.
     *
     * @param  array{status?: mixed, currency_code?: mixed, start_date?: mixed, end_date?: mixed}  $filters
     */
    public function listRevaluations(array $filters, int $perPage): LengthAwarePaginator
    {
        return CurrencyRevaluation::with(['createdBy:id,name'])
            ->orderByDesc('revaluation_date')
            ->orderByDesc('id')
            ->when(array_key_exists('status', $filters), fn ($q) => $q->forStatus($filters['status']))
            ->when(array_key_exists('currency_code', $filters), fn ($q) => $q->forCurrency($filters['currency_code']))
            ->when(
                array_key_exists('start_date', $filters) && array_key_exists('end_date', $filters),
                fn ($q) => $q->forDateRange($filters['start_date'], $filters['end_date'])
            )
            ->paginate($perPage);
    }

    /**
     * Create and calculate a currency revaluation.
     */
    public function revalue(array $data, array $accounts): CurrencyRevaluation
    {
        return DB::transaction(function () use ($data, $accounts) {
            $revaluation = CurrencyRevaluation::create([
                ...$data,
                'revaluation_number' => $data['revaluation_number']
                    ?? $this->numberGenerator->generate('REVAL', '{prefix}-{year}-{number:6}', $data['organization_id']),
            ]);

            foreach ($accounts as $accountData) {
                $foreignBalance = (string) $accountData['foreign_currency_balance'];
                $oldBaseAmount = bcmul($foreignBalance, (string) $data['old_rate'], 4);
                $newBaseAmount = bcmul($foreignBalance, (string) $data['new_rate'], 4);
                $gainLoss = bcsub($newBaseAmount, $oldBaseAmount, 4);

                $revaluation->items()->create([
                    'account_id' => $accountData['account_id'],
                    'account_type' => $accountData['account_type'],
                    'foreign_currency_balance' => $foreignBalance,
                    'old_base_amount' => $oldBaseAmount,
                    'new_base_amount' => $newBaseAmount,
                    'gain_loss_amount' => $gainLoss,
                    'contact_id' => $accountData['contact_id'] ?? null,
                ]);
            }

            $revaluation->recalculateTotals();

            return $revaluation->fresh(['items', 'items.account']);
        });
    }

    /**
     * Post a revaluation: record its sub-ledger entries and book the net
     * unrealised gain or loss in the general ledger.
     *
     * The offset account is the one the caller names, or the account the
     * organization mapped for the side the net says — fx_unrealised_gain_account_id
     * or fx_unrealised_loss_account_id. Unrealised movement is reported apart
     * from realised, so it has its own mapping rather than sharing the realised
     * fx_gain_account_id / fx_loss_account_id. Without a mapping the offset
     * would land wherever an account name happened to match, so nothing is
     * posted at all.
     *
     * @throws InvalidArgumentException when the revaluation cannot be posted or
     *                                  no offset account is configured
     */
    public function postRevaluation(CurrencyRevaluation $revaluation, ?int $gainLossAccountId = null): CurrencyRevaluation
    {
        if (!$revaluation->canPost()) {
            throw new InvalidArgumentException('Revaluation cannot be posted. Ensure it is in draft status with items.');
        }

        return DB::transaction(function () use ($revaluation, $gainLossAccountId) {
            // Record forex gain/loss entries for each item (sub-ledger reporting)
            foreach ($revaluation->items as $item) {
                if (bccomp((string) $item->gain_loss_amount, '0', self::SCALE) !== 0) {
                    ForexGainLossEntry::create([
                        'organization_id' => $revaluation->organization_id,
                        'entry_type' => ForexGainLossEntry::TYPE_UNREALIZED,
                        'transaction_type' => ForexGainLossEntry::TRANSACTION_REVALUATION,
                        'source_type' => CurrencyRevaluation::class,
                        'source_id' => $revaluation->id,
                        'foreign_currency' => $revaluation->currency_code,
                        'base_currency' => $revaluation->base_currency,
                        'foreign_amount' => $item->foreign_currency_balance,
                        'original_rate' => $revaluation->old_rate,
                        'settlement_rate' => $revaluation->new_rate,
                        'gain_loss_amount' => $item->gain_loss_amount,
                        'account_id' => $item->account_id,
                        'transaction_date' => $revaluation->revaluation_date,
                    ]);
                }
            }

            $netGainLoss = $this->netGainLoss($revaluation);

            $changes = ['status' => CurrencyRevaluation::STATUS_POSTED];

            if ($gainLossAccountId !== null) {
                $changes['gain_loss_account_id'] = $gainLossAccountId;
            }

            // A revaluation that nets to nothing moves no balance, so there is
            // nothing to book and no offset account to find.
            if (bccomp($netGainLoss, '0', self::SCALE) !== 0) {
                $changes['journal_entry_id'] = $this->postRevaluationEntry(
                    $revaluation,
                    $netGainLoss,
                    $gainLossAccountId ?? $revaluation->gain_loss_account_id,
                )->id;
            }

            $revaluation->update($changes);

            return $revaluation->fresh(['items']);
        });
    }

    /**
     * The revaluation's net gain or loss, as the sum of its items.
     */
    private function netGainLoss(CurrencyRevaluation $revaluation): string
    {
        $net = bcadd('0', '0', self::SCALE);

        foreach ($revaluation->items as $item) {
            $net = bcadd($net, (string) $item->gain_loss_amount, self::SCALE);
        }

        return $net;
    }

    /**
     * Book the revaluation in the general ledger: each item's adjustment
     * against its own account, and the net against the unrealised offset.
     *
     * @throws InvalidArgumentException when no offset account is configured
     */
    private function postRevaluationEntry(
        CurrencyRevaluation $revaluation,
        string $netGainLoss,
        ?int $offsetAccountId,
    ): JournalEntry {
        $organizationId = (int) $revaluation->organization_id;
        $isGain = bccomp($netGainLoss, '0', self::SCALE) > 0;
        $mappingKey = $isGain ? 'fx_unrealised_gain_account_id' : 'fx_unrealised_loss_account_id';

        $offsetAccountId ??= $this->accountResolver->mapped($organizationId, $mappingKey)?->id;

        if ($offsetAccountId === null) {
            throw new InvalidArgumentException(
                "Revaluation {$revaluation->revaluation_number} nets to an unrealised "
                . ($isGain ? 'gain' : 'loss') . ", but no {$mappingKey} is mapped in the "
                . "organization's accounting settings."
            );
        }

        $zero = bcadd('0', '0', self::SCALE);
        $journalLines = [];

        foreach ($revaluation->items as $item) {
            $gainLoss = bcadd((string) $item->gain_loss_amount, '0', self::SCALE);

            if (bccomp($gainLoss, '0', self::SCALE) === 0) {
                continue;
            }

            // The item's own account carries the adjustment: a gain raises the
            // balance and is debited there, a loss lowers it and is credited.
            $itemIsGain = bccomp($gainLoss, '0', self::SCALE) > 0;
            $amount = $itemIsGain ? $gainLoss : bcsub('0', $gainLoss, self::SCALE);

            $journalLines[] = [
                'account_id' => $item->account_id,
                'description' => "Forex revaluation: {$revaluation->currency_code}",
                'debit' => $itemIsGain ? $amount : $zero,
                'credit' => $itemIsGain ? $zero : $amount,
                'line_order' => count($journalLines),
            ];
        }

        $netAmount = $isGain ? $netGainLoss : bcsub('0', $netGainLoss, self::SCALE);

        $journalLines[] = [
            'account_id' => $offsetAccountId,
            'description' => "Forex revaluation offset: {$revaluation->currency_code}",
            'debit' => $isGain ? $zero : $netAmount,
            'credit' => $isGain ? $netAmount : $zero,
            'line_order' => count($journalLines),
        ];

        return $this->journalService->createAndPost(
            [
                'organization_id' => $organizationId,
                'entry_date' => $revaluation->revaluation_date,
                'reference' => 'FOREX-REVAL-' . $revaluation->id,
                'description' => "Currency revaluation: {$revaluation->currency_code} "
                    . "({$revaluation->old_rate} → {$revaluation->new_rate})",
                'source_type' => CurrencyRevaluation::class,
                'source_id' => $revaluation->id,
            ],
            $journalLines
        );
    }

    /**
     * Reverse a posted revaluation.
     */
    public function reverseRevaluation(CurrencyRevaluation $revaluation): CurrencyRevaluation
    {
        if (!$revaluation->canReverse()) {
            throw new InvalidArgumentException('Only posted revaluations can be reversed.');
        }

        return DB::transaction(function () use ($revaluation) {
            // Delete associated forex entries
            ForexGainLossEntry::where('source_type', CurrencyRevaluation::class)
                ->where('source_id', $revaluation->id)
                ->delete();

            $revaluation->update([
                'status' => CurrencyRevaluation::STATUS_REVERSED,
            ]);

            if ($revaluation->journal_entry_id !== null) {
                $journalEntry = JournalEntry::find($revaluation->journal_entry_id);
                if ($journalEntry !== null) {
                    $this->journalService->reverseEntry($journalEntry, 'Currency revaluation reversed');
                }
            }

            return $revaluation->fresh();
        });
    }

    /**
     * Record a realized forex gain/loss (on payment/settlement).
     */
    public function recordForexGainLoss(array $data): ForexGainLossEntry
    {
        return DB::transaction(function () use ($data) {
            $data['entry_type'] = $data['entry_type'] ?? ForexGainLossEntry::TYPE_REALIZED;

            $foreignAmount = (string) $data['foreign_amount'];
            $originalRate = (string) $data['original_rate'];
            $settlementRate = (string) $data['settlement_rate'];

            $data['gain_loss_amount'] = $data['gain_loss_amount']
                ?? bcmul($foreignAmount, bcsub($settlementRate, $originalRate, 8), 4);

            return ForexGainLossEntry::create($data);
        });
    }

    /**
     * Get exchange gain/loss report.
     */
    public function getExchangeGainLossReport(
        int $organizationId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $currency = null,
        ?string $entryType = null
    ): array {
        $query = ForexGainLossEntry::withoutGlobalScopes()
            ->where('organization_id', $organizationId);

        if ($fromDate && $toDate) {
            $query->whereBetween('transaction_date', [$fromDate, $toDate]);
        }

        if ($currency) {
            $query->where('foreign_currency', $currency);
        }

        if ($entryType) {
            $query->where('entry_type', $entryType);
        }

        $entries = $query->orderBy('transaction_date')->get();

        $totalGains = $entries->where('gain_loss_amount', '>', 0)->sum('gain_loss_amount');
        $totalLosses = $entries->where('gain_loss_amount', '<', 0)->sum('gain_loss_amount');

        $totalGainsStr = bcadd((string) $totalGains, '0', 4);
        $totalLossesStr = bcadd((string) $totalLosses, '0', 4);
        // Absolute value: strip leading minus if present
        $totalLossesAbs = bccomp($totalLossesStr, '0', 4) < 0
            ? bcsub('0', $totalLossesStr, 4)
            : $totalLossesStr;

        return [
            'entries' => $entries,
            'summary' => [
                'total_gains' => (float) $totalGainsStr,
                'total_losses' => (float) $totalLossesAbs,
                'net_gain_loss' => (float) bcadd($totalGainsStr, $totalLossesStr, 4),
                'count' => $entries->count(),
            ],
        ];
    }

    /**
     * Auto-run period-end FX revaluation (SAP F.05 equivalent).
     *
     * Scans all GL accounts configured with a foreign currency and computes
     * their current foreign-currency balance from posted journal entry lines.
     * Builds the accounts array automatically and calls revalue().
     *
     * @param  int     $organizationId
     * @param  string  $revaluationDate  ISO date (e.g. "2026-03-31")
     * @param  string  $baseCurrency     Base currency code (e.g. "SAR")
     * @param  bool    $autoPost         If true, immediately post after creating
     * @return CurrencyRevaluation
     */
    public function autoRun(
        int $organizationId,
        string $revaluationDate,
        string $baseCurrency = 'SAR',
        bool $autoPost = false,
        ?int $createdBy = null,
    ): CurrencyRevaluation {
        // 1. Find all GL accounts with a configured foreign currency (currency_code != base currency).
        $foreignAccounts = Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNotNull('currency_code')
            ->where('currency_code', '!=', $baseCurrency)
            ->where('is_active', true)
            ->where('is_header', false)
            ->select(['id', 'account_type', 'currency_code'])
            ->get();

        if ($foreignAccounts->isEmpty()) {
            throw new InvalidArgumentException('No foreign-currency GL accounts found for this organization.');
        }

        // 2. Group by currency so we do one revaluation per currency.
        $byCurrency = $foreignAccounts->groupBy('currency_code');

        $revaluations = [];

        foreach ($byCurrency as $currencyCode => $accounts) {
            // 3. Get the current exchange rate for this currency.
            $newRate = ExchangeRate::getRate($organizationId, $currencyCode, $baseCurrency, $revaluationDate);
            if ($newRate === null) {
                // Skip currencies with no rate configured — don't fail the whole run.
                continue;
            }

            // 4. Get the previous rate (most recent posted revaluation for this currency).
            $lastRevaluation = CurrencyRevaluation::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('currency_code', $currencyCode)
                ->where('base_currency', $baseCurrency)
                ->where('status', CurrencyRevaluation::STATUS_POSTED)
                ->orderByDesc('revaluation_date')
                ->first();

            $oldRate = $lastRevaluation ? (float) $lastRevaluation->new_rate : 0;

            // 5. Compute foreign-currency balance for each account from posted journal lines.
            $accountsData = [];
            foreach ($accounts as $account) {
                $balance = DB::table('journal_entry_lines as jel')
                    ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                    ->where('je.organization_id', $organizationId)
                    ->where('jel.account_id', $account->id)
                    ->where('je.status', 'posted')
                    ->selectRaw('COALESCE(SUM(jel.debit), 0) - COALESCE(SUM(jel.credit), 0) as net_balance')
                    ->value('net_balance') ?? 0;

                // Only include accounts with a non-zero balance.
                if (abs((float) $balance) < 0.00005) {
                    continue;
                }

                $accountType = match ($account->account_type) {
                    'asset'     => 'asset',
                    'liability' => 'liability',
                    default     => 'asset',
                };

                $accountsData[] = [
                    'account_id'               => $account->id,
                    'account_type'             => $accountType,
                    'foreign_currency_balance' => (float) $balance,
                ];
            }

            if (empty($accountsData)) {
                continue;
            }

            $revaluationData = [
                'organization_id'    => $organizationId,
                'revaluation_date'   => $revaluationDate,
                'currency_code'      => $currencyCode,
                'base_currency'      => $baseCurrency,
                'old_rate'           => $oldRate,
                'new_rate'           => $newRate,
                'notes'              => "Auto-run F.05 period-end revaluation for {$currencyCode}",
                'created_by'         => $createdBy,
            ];

            $revaluation = $this->revalue($revaluationData, $accountsData);

            if ($autoPost) {
                $revaluation = $this->postRevaluation($revaluation);
            }

            $revaluations[] = $revaluation;
        }

        if (empty($revaluations)) {
            throw new InvalidArgumentException('No accounts with non-zero foreign-currency balances found to revalue.');
        }

        // Return the first (or only) revaluation; caller iterates the result if multi-currency.
        return $revaluations[0];
    }

    /**
     * Convert an amount from one currency to another using organization exchange rates.
     */
    public function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        int $organizationId,
        ?string $date = null
    ): ?float {
        return ExchangeRate::convert($amount, $fromCurrency, $toCurrency, $organizationId, $date);
    }
}
