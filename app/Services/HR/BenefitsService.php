<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\BenefitChange;
use App\Models\HR\BenefitType;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeBenefit;
use Illuminate\Support\Facades\DB;

class BenefitsService
{
    /**
     * Benefit types of the current organization by category, then name.
     *
     * @param  array{category?: mixed, active_only?: bool}  $filters  an empty category is ignored
     */
    public function listTypes(array $filters, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return BenefitType::query()
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->byCategory($v))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->orderBy('category')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * A benefit type of the current organization; the tenant scope turns
     * another organization's id into a not-found.
     */
    public function findType(int $id): BenefitType
    {
        return BenefitType::findOrFail($id);
    }

    /**
     * Creates an active benefit type for the organization.
     */
    public function createType(array $data, int $organizationId): BenefitType
    {
        return BenefitType::create(array_merge($data, [
            'organization_id' => $organizationId,
            'is_active' => true,
        ]));
    }

    public function updateType(BenefitType $benefitType, array $data): BenefitType
    {
        $benefitType->update($data);

        return $benefitType->fresh();
    }

    /**
     * An employee's benefits with their type, latest start first.
     *
     * @param  array{status?: mixed, benefit_type_id?: mixed}  $filters  empty values are ignored
     */
    public function listEmployeeBenefits(Employee $employee, array $filters, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return EmployeeBenefit::forEmployee($employee->id)
            ->with('benefitType')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['benefit_type_id'] ?? null, fn ($q, $v) => $q->where('benefit_type_id', $v))
            ->orderByDesc('start_date')
            ->paginate($perPage);
    }

    /**
     * A benefit's recorded changes, newest first, with who made each.
     */
    public function changesOf(EmployeeBenefit $benefit): \Illuminate\Database\Eloquent\Collection
    {
        return $benefit->changes()->with('changedBy')->orderByDesc('changed_at')->get();
    }

    /**
     * Enroll an employee in a benefit.
     */
    public function enrollBenefit(Employee $employee, BenefitType $benefitType, array $data): EmployeeBenefit
    {
        $existing = EmployeeBenefit::where('employee_id', $employee->id)
            ->where('benefit_type_id', $benefitType->id)
            ->where('status', EmployeeBenefit::STATUS_ACTIVE)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Employee is already enrolled in this benefit.');
        }

        return DB::transaction(function () use ($employee, $benefitType, $data) {
            $benefit = EmployeeBenefit::create([
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'benefit_type_id' => $benefitType->id,
                'amount' => $data['amount'] ?? $benefitType->default_amount,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => $data['end_date'] ?? null,
                'status' => EmployeeBenefit::STATUS_ACTIVE,
                'policy_number' => $data['policy_number'] ?? null,
                'provider_name' => $data['provider_name'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->recordChange($benefit, 'enrolled', null, $benefit->toArray());

            return $benefit->fresh(['benefitType']);
        });
    }

    /**
     * Update an employee benefit.
     */
    public function updateBenefit(EmployeeBenefit $benefit, array $data): EmployeeBenefit
    {
        if (!$benefit->isActive()) {
            throw new \InvalidArgumentException('Only active benefits can be updated.');
        }

        return DB::transaction(function () use ($benefit, $data) {
            $oldValues = $benefit->only(['amount', 'end_date', 'policy_number', 'provider_name', 'notes']);

            $benefit->update([
                'amount' => $data['amount'] ?? $benefit->amount,
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $benefit->end_date,
                'policy_number' => $data['policy_number'] ?? $benefit->policy_number,
                'provider_name' => $data['provider_name'] ?? $benefit->provider_name,
                'metadata' => $data['metadata'] ?? $benefit->metadata,
                'notes' => $data['notes'] ?? $benefit->notes,
            ]);

            $this->recordChange($benefit, 'updated', $oldValues, $benefit->fresh()->only(array_keys($oldValues)));

            return $benefit->fresh(['benefitType']);
        });
    }

    /**
     * Terminate an employee benefit.
     */
    public function terminateBenefit(EmployeeBenefit $benefit, ?string $endDate = null, ?string $reason = null): EmployeeBenefit
    {
        if ($benefit->status === EmployeeBenefit::STATUS_TERMINATED) {
            throw new \InvalidArgumentException('Benefit is already terminated.');
        }

        return DB::transaction(function () use ($benefit, $endDate, $reason) {
            $oldValues = ['status' => $benefit->status, 'end_date' => $benefit->end_date];

            $benefit->update([
                'status' => EmployeeBenefit::STATUS_TERMINATED,
                'end_date' => $endDate ?? now()->toDateString(),
            ]);

            $this->recordChange($benefit, 'terminated', $oldValues, [
                'status' => EmployeeBenefit::STATUS_TERMINATED,
                'end_date' => $benefit->end_date,
            ], $reason);

            return $benefit->fresh();
        });
    }

    /**
     * Suspend an employee benefit.
     */
    public function suspendBenefit(EmployeeBenefit $benefit, ?string $reason = null): EmployeeBenefit
    {
        if (!$benefit->isActive()) {
            throw new \InvalidArgumentException('Only active benefits can be suspended.');
        }

        return DB::transaction(function () use ($benefit, $reason) {
            $oldValues = ['status' => $benefit->status];
            $benefit->update(['status' => EmployeeBenefit::STATUS_SUSPENDED]);
            $this->recordChange($benefit, 'suspended', $oldValues, ['status' => EmployeeBenefit::STATUS_SUSPENDED], $reason);

            return $benefit->fresh();
        });
    }

    private function recordChange(
        EmployeeBenefit $benefit,
        string $changeType,
        ?array $oldValues,
        ?array $newValues,
        ?string $reason = null
    ): void {
        BenefitChange::create([
            'employee_benefit_id' => $benefit->id,
            'employee_id' => $benefit->employee_id,
            'change_type' => $changeType,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }
}
