<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FxForward;
use App\Models\Accounting\FxHedgeRelation;
use App\Models\Accounting\FxValuation;
use App\Models\Accounting\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FX Derivative & Hedge Accounting — SAP TRM / IFRS 9.
 *
 * Covers:
 *  - FX forward contract lifecycle (book, mature, cancel)
 *  - Hedge designation and de-designation
 *  - Period-end mark-to-market (MTM) valuation with fair-value/cash-flow hedge split
 *  - Gain/loss journal entry posting
 *
 * Fair-value hedge: full fair-value change through P&L.
 * Cash-flow hedge:  effective portion → OCI; ineffective portion → P&L.
 */
class FxDerivativeService
{
    /** The scale the rate columns hold. */
    private const RATE_SCALE = 8;

    /** The scale the fair-value and gain/loss columns hold. */
    private const AMOUNT_SCALE = 4;

    /** Nothing, at the amount scale. */
    private const ZERO = '0.0000';

    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    // ----------------------------------------------------------------
    // Forward lifecycle
    // ----------------------------------------------------------------

    /**
     * An organization's forwards, latest trade date first, with their hedge
     * relation and latest valuation. Each filter applies only when non-empty.
     */
    public function listForwards(int $organizationId, mixed $status, mixed $buyCurrency, int $perPage = 20): LengthAwarePaginator
    {
        return FxForward::where('organization_id', $organizationId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($buyCurrency, fn ($q) => $q->where('buy_currency', $buyCurrency))
            ->with(['hedgeRelation', 'latestValuation'])
            ->orderByDesc('trade_date')
            ->paginate($perPage);
    }

    /**
     * The forward's hedge relation that is still designated.
     */
    public function findDesignatedHedge(FxForward $forward): FxHedgeRelation
    {
        return FxHedgeRelation::where('fx_forward_id', $forward->id)
            ->where('status', 'designated')
            ->firstOrFail();
    }

    public function bookForward(int $organizationId, array $data, int $createdBy): FxForward
    {
        $contractNumber = 'FWD-' . Carbon::now()->format('Y') . '-' . strtoupper(substr(uniqid(), -6));

        return FxForward::create([
            'organization_id'                 => $organizationId,
            'contract_number'                 => $contractNumber,
            'counterparty_bank'               => $data['counterparty_bank'] ?? null,
            'buy_currency'                    => strtoupper($data['buy_currency']),
            'sell_currency'                   => strtoupper($data['sell_currency']),
            'notional_amount'                 => $data['notional_amount'],
            'forward_rate'                    => $data['forward_rate'],
            'trade_date'                      => $data['trade_date'],
            'maturity_date'                   => $data['maturity_date'],
            'purpose'                         => $data['purpose'] ?? 'hedge',
            'status'                          => 'active',
            'derivative_asset_account_id'     => $data['derivative_asset_account_id'] ?? null,
            'unrealised_gain_loss_account_id' => $data['unrealised_gain_loss_account_id'] ?? null,
            'realised_gain_loss_account_id'   => $data['realised_gain_loss_account_id'] ?? null,
            'created_by'                      => $createdBy,
        ]);
    }

    public function designateHedge(FxForward $forward, array $data): FxHedgeRelation
    {
        return FxHedgeRelation::create([
            'organization_id'       => $forward->organization_id,
            'fx_forward_id'         => $forward->id,
            'hedge_type'            => $data['hedge_type'],         // fair_value | cash_flow
            'hedged_item_type'      => $data['hedged_item_type'],
            'hedged_item_id'        => $data['hedged_item_id'] ?? null,
            'hedged_item_description' => $data['hedged_item_description'] ?? null,
            'hedge_ratio'           => $data['hedge_ratio'] ?? 1.0,
            'designation_date'      => $data['designation_date'],
            'status'                => 'designated',
        ]);
    }

    public function dedesignateHedge(FxHedgeRelation $relation, string $dedesignationDate): FxHedgeRelation
    {
        $relation->update([
            'status'              => 'dedesignated',
            'dedesignation_date'  => $dedesignationDate,
        ]);

        return $relation;
    }

    // ----------------------------------------------------------------
    // Period-end MTM valuation
    // ----------------------------------------------------------------

    /**
     * Record a mark-to-market valuation for a forward at the given spot rate.
     *
     * Fair value = (spot_rate - forward_rate) × notional  [simplified linear MTM]
     *
     * For cash-flow hedges: splits into effective/ineffective portions.
     *
     * The valuation and the entry that carries it are one act: a forward
     * carried in the general ledger is journalled, and a mapping it needs and
     * does not have refuses the valuation rather than recording a fair value
     * the ledger never hears about.
     */
    public function recordValuation(
        FxForward $forward,
        Carbon $valuationDate,
        float|string $spotRate,
    ): FxValuation {
        return DB::transaction(function () use ($forward, $valuationDate, $spotRate): FxValuation {
            $spot = self::rate($spotRate);
            $fairValue = self::amount(bcmul(
                bcsub($spot, (string) $forward->forward_rate, self::RATE_SCALE),
                (string) $forward->notional_amount,
                self::AMOUNT_SCALE,
            ));

            $previousValuation = $forward->valuations()->latest('valuation_date')->first();
            $previousFairValue = $previousValuation ? self::amount((string) $previousValuation->fair_value) : self::ZERO;
            $fairValueChange   = bcsub($fairValue, $previousFairValue, self::AMOUNT_SCALE);

            // Hedge effectiveness split (simplified: ratio × change = effective)
            $hedgeRelation      = $forward->hedgeRelation;
            $effectivePortion   = self::ZERO;
            $ineffectivePortion = $fairValueChange;

            if ($hedgeRelation && $hedgeRelation->hedge_type === 'cash_flow') {
                $effectivePortion   = bcmul($fairValueChange, (string) $hedgeRelation->hedge_ratio, self::AMOUNT_SCALE);
                $ineffectivePortion = bcsub($fairValueChange, $effectivePortion, self::AMOUNT_SCALE);
            }

            $valuation = FxValuation::create([
                'fx_forward_id'       => $forward->id,
                'valuation_date'      => $valuationDate,
                'spot_rate'           => $spot,
                'fair_value'          => $fairValue,
                'fair_value_change'   => $fairValueChange,
                'effective_portion'   => $effectivePortion,
                'ineffective_portion' => $ineffectivePortion,
            ]);

            // A forward with no derivative balance account is not carried in
            // the general ledger, so its valuation has nothing to book
            // against; a fair value that did not move books nothing either.
            if ($forward->derivative_asset_account_id === null
                || bccomp($fairValueChange, self::ZERO, self::AMOUNT_SCALE) === 0) {
                return $valuation;
            }

            $entry = $this->postValuationJournalEntry($forward, $valuation, $fairValueChange);
            $valuation->update(['journal_entry_id' => $entry->id]);

            return $valuation->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Settlement / maturity
    // ----------------------------------------------------------------

    /**
     * Settle a forward at maturity using the actual spot rate.
     */
    public function settle(FxForward $forward, float|string $settlementRate, Carbon $settlementDate): FxForward
    {
        return DB::transaction(function () use ($forward, $settlementRate, $settlementDate): FxForward {
            $rate = self::rate($settlementRate);
            $gainLoss = self::amount(bcmul(
                bcsub($rate, (string) $forward->forward_rate, self::RATE_SCALE),
                (string) $forward->notional_amount,
                self::AMOUNT_SCALE,
            ));

            $forward->update([
                'status'               => 'exercised',
                'settlement_rate'      => $rate,
                'settlement_gain_loss' => $gainLoss,
                'settled_at'           => $settlementDate,
            ]);

            // A forward with no derivative balance account is not carried in
            // the general ledger, so its settlement has nothing to book
            // against. One that is carried there is always journalled.
            if ($forward->derivative_asset_account_id !== null
                && bccomp($gainLoss, self::ZERO, self::AMOUNT_SCALE) !== 0) {
                $this->postRealisedGainLoss($forward, $gainLoss, $settlementDate);
            }

            return $forward->fresh('valuations');
        });
    }

    // ----------------------------------------------------------------

    /**
     * Book the period's fair-value movement against the derivative balance.
     *
     * The sign gives the direction: a rise raises the balance and is credited
     * to the result account, a fall lowers it and is debited there. The result
     * account is the one the contract names, or the account the organization
     * mapped for that side — fx_unrealised_gain_account_id or
     * fx_unrealised_loss_account_id. Without either the entry would be
     * one-sided, so nothing is valued.
     *
     * @throws InvalidArgumentException when no result account is configured
     */
    private function postValuationJournalEntry(FxForward $forward, FxValuation $valuation, string $fairValueChange): JournalEntry
    {
        $organizationId = (int) $forward->organization_id;
        $isGain = bccomp($fairValueChange, self::ZERO, self::AMOUNT_SCALE) > 0;
        $side = $isGain ? 'gain' : 'loss';
        $mappingKey = "fx_unrealised_{$side}_account_id";

        $resultAccountId = $forward->unrealised_gain_loss_account_id
            ?? $this->accountResolver->mapped($organizationId, $mappingKey)?->id;

        if ($resultAccountId === null) {
            throw new InvalidArgumentException(
                "FX forward {$forward->contract_number} was valued at an unrealised {$side}, but no "
                . "{$mappingKey} is mapped in the organization's accounting settings."
            );
        }

        $amount = $isGain ? $fairValueChange : bcsub(self::ZERO, $fairValueChange, self::AMOUNT_SCALE);
        $zero = self::ZERO;

        return $this->journalService->createAndPost([
            'organization_id' => $organizationId,
            'entry_date'      => $valuation->valuation_date,
            'reference'       => $forward->contract_number,
            'description'     => "FX forward MTM: {$forward->contract_number} @ {$valuation->valuation_date}",
            'source_type'     => FxValuation::class,
            'source_id'       => $valuation->id,
        ], [
            [
                'account_id'  => $forward->derivative_asset_account_id,
                'debit'       => $isGain ? $amount : $zero,
                'credit'      => $isGain ? $zero : $amount,
                'description' => "FX forward mark-to-market — {$forward->contract_number}",
                'line_order'  => 0,
            ],
            [
                'account_id'  => $resultAccountId,
                'debit'       => $isGain ? $zero : $amount,
                'credit'      => $isGain ? $amount : $zero,
                'description' => "Unrealised FX {$side} — {$forward->contract_number}",
                'line_order'  => 1,
            ],
        ]);
    }

    /**
     * Book the settlement's realised result against the derivative balance.
     *
     * The sign gives the direction: a gain raises the balance and is credited
     * to the result account, a loss lowers it and is debited there. The result
     * account is the one the contract names, or the account the organization
     * mapped for that side — fx_gain_account_id or fx_loss_account_id.
     * Without either the entry would be one-sided, so nothing is settled.
     *
     * @throws InvalidArgumentException when no result account is configured
     */
    private function postRealisedGainLoss(FxForward $forward, string $gainLoss, Carbon $date): void
    {
        $organizationId = (int) $forward->organization_id;
        $isGain = bccomp($gainLoss, self::ZERO, self::AMOUNT_SCALE) > 0;
        $side = $isGain ? 'gain' : 'loss';
        $mappingKey = "fx_{$side}_account_id";

        $resultAccountId = $forward->realised_gain_loss_account_id
            ?? $this->accountResolver->mapped($organizationId, $mappingKey)?->id;

        if ($resultAccountId === null) {
            throw new InvalidArgumentException(
                "FX forward {$forward->contract_number} settled at a realised {$side}, but no "
                . "{$mappingKey} is mapped in the organization's accounting settings."
            );
        }

        $amount = $isGain ? $gainLoss : bcsub(self::ZERO, $gainLoss, self::AMOUNT_SCALE);
        $zero = self::ZERO;

        $this->journalService->createAndPost([
            'organization_id' => $organizationId,
            'entry_date'      => $date->toDateString(),
            'reference'       => $forward->contract_number,
            'description'     => "FX forward settled: {$forward->contract_number} — realised {$side}",
            'source_type'     => FxForward::class,
            'source_id'       => $forward->id,
        ], [
            [
                'account_id'  => $forward->derivative_asset_account_id,
                'debit'       => $isGain ? $amount : $zero,
                'credit'      => $isGain ? $zero : $amount,
                'description' => "FX forward settlement — {$forward->contract_number}",
                'line_order'  => 0,
            ],
            [
                'account_id'  => $resultAccountId,
                'debit'       => $isGain ? $zero : $amount,
                'credit'      => $isGain ? $amount : $zero,
                'description' => "Realised FX {$side} — {$forward->contract_number}",
                'line_order'  => 1,
            ],
        ]);
    }

    /**
     * A rate as a decimal string at the scale the rate columns hold.
     *
     * A rate reaches the service as a request value, which is a float once a
     * JSON body is decoded. A float is written out at that scale rather than
     * cast, so an exponent form never reaches the arithmetic.
     */
    private static function rate(float|string $rate): string
    {
        if (is_string($rate) && preg_match('/^-?\d+(\.\d+)?$/', $rate) === 1) {
            return bcadd($rate, '0', self::RATE_SCALE);
        }

        return number_format((float) $rate, self::RATE_SCALE, '.', '');
    }

    /** An amount at the scale the fair-value and gain/loss columns hold. */
    private static function amount(string $amount): string
    {
        return bcadd($amount, '0', self::AMOUNT_SCALE);
    }
}
