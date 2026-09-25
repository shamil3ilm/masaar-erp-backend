<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FxForward;
use App\Models\Accounting\FxHedgeRelation;
use App\Models\Accounting\FxValuation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
     */
    public function recordValuation(
        FxForward $forward,
        Carbon $valuationDate,
        float $spotRate,
    ): FxValuation {
        return DB::transaction(function () use ($forward, $valuationDate, $spotRate): FxValuation {
            $fairValue = round(((float) $spotRate - (float) $forward->forward_rate) * (float) $forward->notional_amount, 4);

            $previousValuation = $forward->valuations()->latest('valuation_date')->first();
            $previousFairValue = $previousValuation ? (float) $previousValuation->fair_value : 0.0;
            $fairValueChange   = round($fairValue - $previousFairValue, 4);

            // Hedge effectiveness split (simplified: ratio × change = effective)
            $hedgeRelation     = $forward->hedgeRelation;
            $effectivePortion  = 0.0;
            $ineffectivePortion = $fairValueChange;

            if ($hedgeRelation && $hedgeRelation->hedge_type === 'cash_flow') {
                $effectivePortion   = round($fairValueChange * (float) $hedgeRelation->hedge_ratio, 4);
                $ineffectivePortion = round($fairValueChange - $effectivePortion, 4);
            }

            $valuation = FxValuation::create([
                'fx_forward_id'       => $forward->id,
                'valuation_date'      => $valuationDate,
                'spot_rate'           => $spotRate,
                'fair_value'          => $fairValue,
                'fair_value_change'   => $fairValueChange,
                'effective_portion'   => $effectivePortion,
                'ineffective_portion' => $ineffectivePortion,
            ]);

            // Post journal entry if accounts configured
            if ($forward->derivative_asset_account_id && $forward->unrealised_gain_loss_account_id && $fairValueChange !== 0.0) {
                $this->postValuationJournalEntry($forward, $valuation);
            }

            return $valuation;
        });
    }

    // ----------------------------------------------------------------
    // Settlement / maturity
    // ----------------------------------------------------------------

    /**
     * Settle a forward at maturity using the actual spot rate.
     */
    public function settle(FxForward $forward, float $settlementRate, Carbon $settlementDate): FxForward
    {
        return DB::transaction(function () use ($forward, $settlementRate, $settlementDate): FxForward {
            $gainLoss = round(($settlementRate - (float) $forward->forward_rate) * (float) $forward->notional_amount, 4);

            $forward->update([
                'status'               => 'exercised',
                'settlement_rate'      => $settlementRate,
                'settlement_gain_loss' => $gainLoss,
                'settled_at'           => $settlementDate,
            ]);

            // A forward with no derivative balance account is not carried in
            // the general ledger, so its settlement has nothing to book
            // against. One that is carried there is always journalled.
            if ($forward->derivative_asset_account_id && $gainLoss !== 0.0) {
                $this->postRealisedGainLoss($forward, $gainLoss, $settlementDate);
            }

            return $forward->fresh('valuations');
        });
    }

    // ----------------------------------------------------------------

    private function postValuationJournalEntry(FxForward $forward, FxValuation $valuation): void
    {
        $amount = abs((float) $valuation->fair_value_change);
        $isGain = (float) $valuation->fair_value_change >= 0;

        $this->journalService->createEntry([
            'organization_id' => $forward->organization_id,
            'entry_date'      => $valuation->valuation_date,
            'description'     => "FX forward MTM: {$forward->contract_number} @ {$valuation->valuation_date}",
            'source_type'     => FxValuation::class,
            'source_id'       => $valuation->id,
        ], [
            [
                'account_id' => $forward->derivative_asset_account_id,
                'debit'      => $isGain ? $amount : 0,
                'credit'     => $isGain ? 0 : $amount,
            ],
            [
                'account_id' => $forward->unrealised_gain_loss_account_id,
                'debit'      => $isGain ? 0 : $amount,
                'credit'     => $isGain ? $amount : 0,
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
     * @throws RuntimeException when no result account is configured
     */
    private function postRealisedGainLoss(FxForward $forward, float $gainLoss, Carbon $date): void
    {
        $organizationId = (int) $forward->organization_id;
        $isGain = $gainLoss >= 0;
        $side = $isGain ? 'gain' : 'loss';
        $mappingKey = "fx_{$side}_account_id";

        $resultAccountId = $forward->realised_gain_loss_account_id
            ?? $this->accountResolver->mapped($organizationId, $mappingKey)?->id;

        if ($resultAccountId === null) {
            throw new RuntimeException(
                "FX forward {$forward->contract_number} settled at a realised {$side}, but no "
                . "{$mappingKey} is mapped in the organization's accounting settings."
            );
        }

        $amount = number_format(abs($gainLoss), 4, '.', '');
        $zero = '0.0000';

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
}
