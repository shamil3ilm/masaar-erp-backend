<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\ActivityConfirmation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ActivityConfirmationService
{
    /** Relations returned with a recorded or reversed confirmation. */
    private const SUMMARY_RELATIONS = ['costCenter:id,code,name', 'activityType:id,code,name'];

    /**
     * Confirmations of one organization, newest confirmation date first.
     *
     * @param  array{cost_center_id?: ?int, activity_type_id?: ?int, fiscal_year?: ?int, period?: ?int, status?: mixed}  $filters
     *         A filter applies only when it is set and not null.
     */
    public function list(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ActivityConfirmation::with([
            ...self::SUMMARY_RELATIONS,
            'workOrder:id,order_number',
            'confirmedBy:id,name',
        ])
            ->where('organization_id', $organizationId)
            ->orderByDesc('confirmation_date');

        foreach (['cost_center_id', 'activity_type_id', 'fiscal_year', 'period', 'status'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Record the actual quantity of an activity performed at a cost centre.
     *
     * actual_cost is derived as confirmed_quantity * actual_rate when a rate is given.
     *
     * @param  array<string, mixed>  $data  Validated confirmation attributes.
     */
    public function record(array $data, int $organizationId, int $userId): ActivityConfirmation
    {
        $data['organization_id']     = $organizationId;
        $data['confirmation_number'] = 'CONF-' . strtoupper(Str::random(8));
        $data['confirmed_by']        = $userId;
        $data['status']              = ActivityConfirmation::STATUS_CONFIRMED;

        if (isset($data['actual_rate'])) {
            $data['actual_cost'] = round((float) $data['confirmed_quantity'] * (float) $data['actual_rate'], 4);
        }

        return ActivityConfirmation::create($data)->load(self::SUMMARY_RELATIONS);
    }

    /**
     * Reverse a confirmed record: create a mirror record with negated quantities
     * and cost, and mark the original as reversed and linked to it.
     *
     * Runs on the locked original, so two concurrent reversals cannot both pass
     * the status check and create two mirror records.
     *
     * @throws InvalidArgumentException when the original is no longer confirmed
     */
    public function reverse(ActivityConfirmation $confirmation, int $userId): ActivityConfirmation
    {
        $reversal = $confirmation->lockForTransition(function (ActivityConfirmation $original) use ($userId): ActivityConfirmation {
            if (! $original->isConfirmed()) {
                throw new InvalidArgumentException('Only confirmed records can be reversed.');
            }

            $reversal = ActivityConfirmation::create([
                'organization_id'     => $original->organization_id,
                'confirmation_number' => 'REV-' . strtoupper(Str::random(8)),
                'work_order_id'       => $original->work_order_id,
                'work_center_id'      => $original->work_center_id,
                'cost_center_id'      => $original->cost_center_id,
                'activity_type_id'    => $original->activity_type_id,
                'confirmed_quantity'  => -$original->confirmed_quantity,
                'planned_quantity'    => $original->planned_quantity
                    ? -$original->planned_quantity
                    : null,
                'uom'                 => $original->uom,
                'actual_rate'         => $original->actual_rate,
                'planned_rate'        => $original->planned_rate,
                'actual_cost'         => $original->actual_cost
                    ? -$original->actual_cost
                    : null,
                'fiscal_year'         => $original->fiscal_year,
                'period'              => $original->period,
                'confirmation_date'   => now()->toDateString(),
                'confirmed_by'        => $userId,
                'status'              => ActivityConfirmation::STATUS_CONFIRMED,
                'reversal_id'         => $original->id,
                'notes'               => 'Reversal of ' . $original->confirmation_number,
            ]);

            $original->update([
                'status'      => ActivityConfirmation::STATUS_REVERSED,
                'reversal_id' => $reversal->id,
            ]);

            return $reversal;
        });

        return $reversal->load(self::SUMMARY_RELATIONS);
    }
}
