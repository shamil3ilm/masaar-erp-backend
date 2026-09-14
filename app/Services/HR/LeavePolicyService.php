<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\Leave\LeaveTierApprover;
use App\Models\HR\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LeavePolicyService
{
    /**
     * Leave policies of the current organization by name: all of them, or one
     * page when a page size is given.
     */
    public function list(bool $activeOnly, ?int $perPage): Collection|LengthAwarePaginator
    {
        $query = LeavePolicy::query()
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('name');

        return $perPage === null ? $query->get() : $query->paginate($perPage);
    }

    /**
     * Create a new leave policy.
     */
    public function create(array $data): LeavePolicy
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['is_default'])) {
                LeavePolicy::where('organization_id', $data['organization_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return LeavePolicy::create($data);
        });
    }

    /**
     * Update an existing leave policy.
     */
    public function update(LeavePolicy $policy, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($policy, $data) {
            if (!empty($data['is_default']) && !$policy->is_default) {
                LeavePolicy::where('organization_id', $policy->organization_id)
                    ->where('id', '!=', $policy->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $policy->update($data);

            return $policy->fresh();
        });
    }

    public function delete(LeavePolicy $policy): void
    {
        $policy->delete();
    }

    /**
     * The policy's active leave types in display order, with their tiers.
     */
    public function activeLeaveTypes(LeavePolicy $policy): Collection
    {
        return $policy->leaveTypes()
            ->with('leaveTiers')
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Creates a leave type within the policy. Null values are left out, so a
     * column with a default keeps it.
     */
    public function createLeaveType(LeavePolicy $policy, array $data, int $organizationId): LeaveType
    {
        $data = array_filter($data, fn ($v) => $v !== null);
        $data['organization_id'] = $organizationId;
        $data['leave_policy_id'] = $policy->id;

        return LeaveType::create($data);
    }

    /**
     * Assign a tier to a leave type within a policy, with its approvers.
     */
    public function assignTier(LeaveType $leaveType, array $tierData): LeaveTier
    {
        return DB::transaction(function () use ($leaveType, $tierData) {
            // The approvers are rows of their own; the tier does not take them.
            $tier = LeaveTier::create(array_merge(Arr::except($tierData, ['approvers']), [
                'leave_type_id' => $leaveType->id,
            ]));

            if (!empty($tierData['approvers'])) {
                foreach ($tierData['approvers'] as $approverData) {
                    LeaveTierApprover::create(array_merge($approverData, [
                        'leave_tier_id' => $tier->id,
                    ]));
                }
            }

            return $tier->load('approvers');
        });
    }

    /**
     * A tier of the current organization. Tiers carry no organization of their
     * own, so the lookup goes through the tier's leave type, whose tenant scope
     * turns another organization's tier into a not-found.
     */
    public function findTier(int|string $id): LeaveTier
    {
        return LeaveTier::whereHas('leaveType')->findOrFail($id);
    }

    public function updateTier(LeaveTier $tier, array $data): LeaveTier
    {
        $tier->update($data);

        return $tier->fresh('approvers');
    }

    public function deleteTier(LeaveTier $tier): void
    {
        $tier->delete();
    }

    /**
     * Get the accrual schedule for a leave policy.
     */
    public function getAccrualSchedule(LeavePolicy $policy): array
    {
        $leaveTypes = $policy->leaveTypes()
            ->with(['leaveTiers' => function ($q) {
                $q->active()->byPriority();
            }])
            ->active()
            ->get();

        $schedule = [];

        foreach ($leaveTypes as $leaveType) {
            $typeSchedule = [
                'leave_type_id' => $leaveType->id,
                'name' => $leaveType->name,
                'code' => $leaveType->code,
                'accrual_type' => $leaveType->accrual_type,
                'accrual_day' => $leaveType->accrual_day,
                'tiers' => [],
            ];

            foreach ($leaveType->leaveTiers as $tier) {
                $typeSchedule['tiers'][] = [
                    'tier_id' => $tier->id,
                    'name' => $tier->name,
                    'entitled_days' => $tier->entitled_days,
                    'entitlement_period' => $tier->entitlement_period,
                    'monthly_accrual_rate' => $tier->monthly_accrual_rate,
                    'min_service_months' => $tier->min_service_months,
                    'max_service_months' => $tier->max_service_months,
                ];
            }

            $schedule[] = $typeSchedule;
        }

        return $schedule;
    }
}
