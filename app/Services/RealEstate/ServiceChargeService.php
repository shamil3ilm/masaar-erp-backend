<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\RentalContract;
use App\Models\RealEstate\ServiceChargeAllocation;
use App\Models\RealEstate\ServiceChargeSettlement;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Annual service charge settlement: spreads a property's actual running costs
 * across its tenants by lettable area and compares that to what they were
 * billed on account during the year.
 */
class ServiceChargeService
{
    private const MONTHS_PER_YEAR = '12';

    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    public function createServiceChargeSettlement(int $organizationId, array $data): ServiceChargeSettlement
    {
        $settlementNumber = $this->numberGenerator->generate('RE-SET', null, $organizationId);

        $costItems = $data['cost_items'] ?? [];
        unset($data['cost_items']);

        return DB::transaction(function () use ($organizationId, $data, $costItems, $settlementNumber) {
            $settlement = ServiceChargeSettlement::create(array_merge($data, [
                'organization_id'   => $organizationId,
                'settlement_number' => $settlementNumber,
                'status'            => 'draft',
            ]));

            foreach ($costItems as $item) {
                $settlement->costItems()->create($item);
            }

            return $settlement->load('costItems');
        });
    }

    /**
     * Allocate the settlement's costs across the property's active contracts.
     *
     * Re-running the calculation replaces the previous allocations.
     */
    public function calculateSettlement(ServiceChargeSettlement $settlement): ServiceChargeSettlement
    {
        if ($settlement->status !== 'draft') {
            throw new InvalidArgumentException('Only draft settlements can be calculated.');
        }

        return DB::transaction(function () use ($settlement) {
            $property  = $settlement->property;
            $costItems = $settlement->costItems;

            foreach ($costItems as $item) {
                if ((float) $item->lettable_area_sqm > 0) {
                    $item->update([
                        'cost_per_sqm' => bcdiv((string) $item->actual_cost, (string) $item->lettable_area_sqm, 6),
                    ]);
                }
            }

            $totalActualCosts = $costItems->sum('actual_cost');

            $contracts = RentalContract::where('organization_id', $settlement->organization_id)
                ->where('status', 'active')
                ->whereHas('rentalUnit.building', fn ($q) => $q->where('property_id', $property->id))
                ->with([
                    'rentalUnit',
                    'conditions' => fn ($q) => $q->where('condition_type', 'service_charge')->where('is_active', true),
                ])
                ->get();

            $totalArea   = $contracts->sum(fn ($c) => (float) $c->rentalUnit?->area_sqm ?? 0);
            $totalBilled = '0.0000';

            $settlement->allocations()->delete();

            foreach ($contracts as $contract) {
                $unitArea      = (float) $contract->rentalUnit?->area_sqm ?? 0;
                $allocationPct = $totalArea > 0 ? round($unitArea / $totalArea * 100, 4) : 0;
                $actualAmount  = bcmul((string) $totalActualCosts, bcdiv((string) $allocationPct, '100', 6), 4);

                // Billed on account: the monthly service charge conditions over the year.
                $annualBilled = bcmul((string) $contract->conditions->sum('amount'), self::MONTHS_PER_YEAR, 4);

                $totalBilled = bcadd($totalBilled, $annualBilled, 4);

                ServiceChargeAllocation::create([
                    'settlement_id'     => $settlement->id,
                    'contract_id'       => $contract->id,
                    'unit_area_sqm'     => $unitArea,
                    'allocation_pct'    => $allocationPct,
                    'actual_amount'     => $actualAmount,
                    'billed_amount'     => $annualBilled,
                    'adjustment_amount' => bcsub((string) $actualAmount, $annualBilled, 4),
                ]);
            }

            $settlement->update([
                'status'                  => 'calculated',
                'total_actual_costs'      => $totalActualCosts,
                'total_billed_to_tenants' => $totalBilled,
                'total_adjustment'        => bcsub((string) $totalActualCosts, $totalBilled, 4),
            ]);

            return $settlement->fresh(['costItems', 'allocations.contract']);
        });
    }
}
