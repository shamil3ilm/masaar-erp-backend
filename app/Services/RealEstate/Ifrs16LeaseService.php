<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\Ifrs16Schedule;
use App\Models\RealEstate\RentalContract;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * IFRS 16 lessee accounting: the right-of-use asset and lease liability
 * amortisation schedule for contracts the organization leases in.
 *
 * The liability is the present value of the remaining lease payments discounted
 * at the incremental borrowing rate; the right-of-use asset depreciates on a
 * straight line over the same term.
 */
class Ifrs16LeaseService
{
    /**
     * Build and persist the amortisation schedule, replacing any previous one.
     *
     * @param  RentalContract $contract         Must be a lease_in (lessee) contract.
     * @param  float         $ibrPercent       Annual incremental borrowing rate, e.g. 5.5 for 5.5%.
     * @param  string|null   $commencementDate Defaults to the contract start date.
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the contract cannot be measured.
     */
    public function generateSchedule(
        RentalContract $contract,
        float $ibrPercent,
        ?string $commencementDate = null
    ): array {
        if ($contract->contract_type !== 'lease_in') {
            throw new InvalidArgumentException('IFRS 16 applies to lessee contracts (contract_type = lease_in) only.');
        }

        $start = Carbon::parse($commencementDate ?? $contract->start_date);
        $end   = Carbon::parse($contract->end_date);

        if ($end->lte($start)) {
            throw new InvalidArgumentException('Contract end_date must be after the commencement date.');
        }

        $monthlyPayment = (float) ($contract->conditions()
            ->where('condition_type', 'base_rent')
            ->where('is_active', true)
            ->value('amount') ?? 0);

        if ($monthlyPayment <= 0) {
            throw new InvalidArgumentException('No active base_rent condition found on the contract.');
        }

        // Convert the annual rate to its monthly equivalent: (1 + annual)^(1/12) - 1.
        $monthlyIbr  = (1 + $ibrPercent / 100) ** (1 / 12) - 1;
        $totalMonths = (int) $start->diffInMonths($end);

        // Present value of an annuity: PV = PMT x [1 - (1 + r)^-n] / r.
        $pvFactor = $monthlyIbr > 0
            ? (1 - (1 + $monthlyIbr) ** -$totalMonths) / $monthlyIbr
            : (float) $totalMonths;

        $rouAsset            = round($monthlyPayment * $pvFactor, 4);
        $monthlyDepreciation = $totalMonths > 0 ? round($rouAsset / $totalMonths, 4) : 0.0;

        Ifrs16Schedule::where('contract_id', $contract->id)->delete();

        $rows             = [];
        $openingLiability = $rouAsset;
        $rouBookValue     = $rouAsset;
        $periodDate       = $start->copy()->startOfMonth();

        for ($period = 0; $period < $totalMonths; $period++) {
            $interest         = round($openingLiability * $monthlyIbr, 4);
            $principal        = round($monthlyPayment - $interest, 4);
            $closingLiability = round($openingLiability - $principal, 4);
            $rouBookValue     = round($rouBookValue - $monthlyDepreciation, 4);

            // Absorb rounding drift in the final period so both balances land on zero.
            if ($period === $totalMonths - 1) {
                $closingLiability = 0.0;
                $rouBookValue     = 0.0;
            }

            $rows[] = Ifrs16Schedule::create([
                'contract_id'         => $contract->id,
                'period_date'         => $periodDate->toDateString(),
                'opening_liability'   => $openingLiability,
                'interest_expense'    => $interest,
                'lease_payment'       => $monthlyPayment,
                'principal_reduction' => $principal,
                'closing_liability'   => $closingLiability,
                'rou_depreciation'    => $monthlyDepreciation,
                'rou_book_value'      => $rouBookValue,
                'gl_posted'           => false,
            ]);

            $openingLiability = $closingLiability;
            $periodDate->addMonth();
        }

        $contract->update([
            'ibr_percent'              => $ibrPercent,
            'rou_asset_amount'         => $rouAsset,
            'lease_liability_amount'   => $rouAsset,
            'ifrs16_commencement_date' => $start->toDateString(),
            'ifrs16_applied'           => true,
        ]);

        return [
            'rou_asset'       => $rouAsset,
            'total_months'    => $totalMonths,
            'monthly_payment' => $monthlyPayment,
            'ibr_percent'     => $ibrPercent,
            'rows'            => $rows,
        ];
    }

    /**
     * Return the stored schedule together with the contract's IFRS 16 summary.
     *
     * @return array<string, mixed>
     */
    public function getSchedule(RentalContract $contract): array
    {
        $rows = Ifrs16Schedule::where('contract_id', $contract->id)
            ->orderBy('period_date')
            ->get();

        return [
            'contract_id'              => $contract->id,
            'rou_asset_amount'         => (float) $contract->rou_asset_amount,
            'lease_liability_amount'   => (float) $contract->lease_liability_amount,
            'ibr_percent'              => (float) $contract->ibr_percent,
            'ifrs16_commencement_date' => $contract->ifrs16_commencement_date,
            'ifrs16_applied'           => (bool) $contract->ifrs16_applied,
            'schedule'                 => $rows->map(fn ($r) => [
                'period_date'         => $r->period_date,
                'opening_liability'   => (float) $r->opening_liability,
                'interest_expense'    => (float) $r->interest_expense,
                'lease_payment'       => (float) $r->lease_payment,
                'principal_reduction' => (float) $r->principal_reduction,
                'closing_liability'   => (float) $r->closing_liability,
                'rou_depreciation'    => (float) $r->rou_depreciation,
                'rou_book_value'      => (float) $r->rou_book_value,
                'gl_posted'           => $r->gl_posted,
            ])->values()->toArray(),
        ];
    }
}
