<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\HR\Leave\LeaveEncashment;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    /**
     * Process accruals for all employees in an organization.
     */
    public function processAccruals(int $organizationId, ?string $accrualDate = null): int
    {
        $accrualDate = $accrualDate ? new \DateTimeImmutable($accrualDate) : new \DateTimeImmutable;
        $processed = 0;

        $policies = LeavePolicy::where('organization_id', $organizationId)
            ->active()
            ->with(['leaveTypes' => fn ($q) => $q->active()])
            ->get();

        $employees = Employee::where('organization_id', $organizationId)
            ->where('employment_status', 'active')
            ->get();

        foreach ($employees as $employee) {
            foreach ($policies as $policy) {
                foreach ($policy->leaveTypes as $leaveType) {
                    if (! $leaveType->isApplicableToEmployee($employee)) {
                        continue;
                    }

                    if ($leaveType->accrual_type === LeaveType::ACCRUAL_NONE) {
                        continue;
                    }

                    $tier = $this->getApplicableTier($leaveType, $employee);
                    if (! $tier) {
                        continue;
                    }

                    if ($this->processEmployeeAccrual($employee, $leaveType, $tier, $accrualDate)) {
                        $processed++;
                    }
                }
            }
        }

        return $processed;
    }

    /**
     * Add days to, or take days from, an employee's leave balance.
     */
    public function adjustBalance(array $data): LeaveAdjustment
    {
        return DB::transaction(function () use ($data) {
            $balance = LeaveBalance::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('year', $data['year'] ?? now()->year)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $balance->closing_balance;

            $days = (string) $data['days'];
            if ($data['adjustment_type'] === LeaveAdjustment::TYPE_DEDUCT) {
                $days = bcsub('0', $days, 2);
            }

            $balance->adjustment = bcadd((string) $balance->adjustment, $days, 2);
            $balance->recalculateClosingBalance();
            $balance->save();

            return LeaveAdjustment::create([
                'organization_id' => $data['organization_id'],
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'leave_balance_id' => $balance->id,
                'adjustment_type' => $data['adjustment_type'],
                'days' => $data['days'],
                'balance_before' => $before,
                'balance_after' => $balance->closing_balance,
                'reason' => $data['reason'],
                'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                'approved_by' => $data['approved_by'] ?? null,
                'approved_at' => ! empty($data['approved_by']) ? now() : null,
                'created_by' => $data['created_by'],
            ]);
        });
    }

    /**
     * Request leave encashment. The days leave the balance when it is approved.
     */
    public function encashLeave(array $data): LeaveEncashment
    {
        return DB::transaction(function () use ($data) {
            $balance = LeaveBalance::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('year', $data['year'] ?? now()->year)
                ->with('leaveTier')
                ->firstOrFail();

            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            if (! $leaveType->is_encashable) {
                throw new \InvalidArgumentException('This leave type is not encashable.');
            }

            $requestedDays = (float) $data['requested_days'];
            if ($requestedDays > $balance->getAvailableBalance()) {
                throw new \InvalidArgumentException('Insufficient leave balance for encashment.');
            }

            $tier = $balance->leaveTier;
            if ($tier && $tier->max_encashable_days && $requestedDays > $tier->max_encashable_days) {
                throw new \InvalidArgumentException("Maximum encashable days is {$tier->max_encashable_days}.");
            }

            $encashmentRate = $tier?->encashment_rate ?? 100;

            return LeaveEncashment::create([
                'organization_id' => $data['organization_id'],
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'leave_balance_id' => $balance->id,
                'requested_days' => $requestedDays,
                'daily_rate' => $data['daily_rate'],
                'encashment_rate' => $encashmentRate,
                'amount' => $this->encashmentAmount((string) $requestedDays, (string) $data['daily_rate'], (string) $encashmentRate),
                'status' => LeaveEncashment::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'],
            ]);
        });
    }

    /**
     * Approve an encashment and take its days out of the balance.
     */
    public function approveEncashment(LeaveEncashment $encashment, ?float $approvedDays, ?string $notes, int $approvedBy): LeaveEncashment
    {
        return DB::transaction(function () use ($encashment, $approvedDays, $notes, $approvedBy) {
            $encashment = LeaveEncashment::whereKey($encashment->id)->lockForUpdate()->firstOrFail();

            if ($encashment->status !== LeaveEncashment::STATUS_PENDING) {
                throw new \InvalidArgumentException('Only pending encashments can be approved.');
            }

            $days = $approvedDays ?? (float) $encashment->requested_days;
            if ($days > (float) $encashment->requested_days) {
                throw new \InvalidArgumentException('Approved days cannot exceed the days requested.');
            }

            $balance = LeaveBalance::whereKey($encashment->leave_balance_id)->lockForUpdate()->firstOrFail();
            if ($days > $balance->getAvailableBalance()) {
                throw new \InvalidArgumentException('Insufficient leave balance for encashment.');
            }

            $balance->encashed = bcadd((string) $balance->encashed, (string) $days, 2);
            $balance->recalculateClosingBalance();
            $balance->save();

            $encashment->update([
                'approved_days' => $days,
                'amount' => $this->encashmentAmount((string) $days, (string) $encashment->daily_rate, (string) $encashment->encashment_rate),
                'status' => LeaveEncashment::STATUS_APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'notes' => $notes ?? $encashment->notes,
            ]);

            return $encashment->fresh();
        });
    }

    /**
     * A balance's accruals, latest first. Accruals carry no organization of
     * their own, so the balance must come from a tenant-scoped lookup.
     */
    public function accrualsFor(LeaveBalance $balance): \Illuminate\Database\Eloquent\Collection
    {
        return LeaveAccrual::where('leave_balance_id', $balance->id)
            ->orderByDesc('accrual_date')
            ->get();
    }

    /**
     * Adjustments of the current organization, newest first: all of them, or
     * one page when a page size is given.
     *
     * @param  array{employee_id?: mixed, leave_type_id?: mixed}  $filters  empty values are ignored
     */
    public function listAdjustments(array $filters, ?int $perPage): \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = LeaveAdjustment::with(['employee', 'leaveType', 'creator', 'approver'])
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->forEmployee((int) $id))
            ->when($filters['leave_type_id'] ?? null, fn ($q, $id) => $q->where('leave_type_id', $id))
            ->orderByDesc('created_at');

        return $perPage === null ? $query->get() : $query->paginate($perPage);
    }

    /**
     * Encashments of the current organization, newest first: all of them, or
     * one page when a page size is given.
     *
     * @param  array{employee_id?: mixed, status?: mixed}  $filters  empty values are ignored
     */
    public function listEncashments(array $filters, ?int $perPage): \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = LeaveEncashment::with(['employee', 'leaveType', 'creator', 'approver'])
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->forEmployee((int) $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at');

        return $perPage === null ? $query->get() : $query->paginate($perPage);
    }

    /**
     * Get the current balance for an employee and leave type.
     */
    public function getBalance(int $employeeId, int $leaveTypeId, ?int $year = null): ?LeaveBalance
    {
        $year = $year ?? now()->year;

        return LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->with(['leaveType', 'leaveTier'])
            ->first();
    }

    /**
     * The highest-priority active tier the employee's service qualifies for.
     */
    protected function getApplicableTier(LeaveType $leaveType, Employee $employee): ?LeaveTier
    {
        return $leaveType->leaveTiers()
            ->active()
            ->forServiceMonths($employee->getTenureInMonths() ?? 0)
            ->byPriority()
            ->first();
    }

    /**
     * Credit one employee's balance for one leave type from its tier.
     *
     * A yearly tier credits its whole entitlement once, when the year's balance
     * is created. A monthly tier credits a month's share at most once a month.
     */
    protected function processEmployeeAccrual(
        Employee $employee,
        LeaveType $leaveType,
        LeaveTier $tier,
        \DateTimeInterface $accrualDate
    ): bool {
        $year = (int) $accrualDate->format('Y');

        return DB::transaction(function () use ($employee, $leaveType, $tier, $year, $accrualDate) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($tier->entitlement_period !== LeaveTier::ENTITLEMENT_MONTHLY) {
                if ($balance !== null) {
                    return false;
                }

                $balance = $this->newBalance($employee, $leaveType, $tier, $year);
                $this->credit($balance, 'entitled', (string) $tier->entitled_days, $accrualDate, LeaveAccrual::TYPE_YEARLY);

                return true;
            }

            $balance ??= $this->newBalance($employee, $leaveType, $tier, $year);

            if ($balance->last_accrual_date !== null && $balance->last_accrual_date->format('Y-m') >= $accrualDate->format('Y-m')) {
                return false;
            }

            $days = $tier->monthly_accrual_rate ?? bcdiv((string) $tier->entitled_days, '12', 2);
            $this->credit($balance, 'accrued', (string) $days, $accrualDate, LeaveAccrual::TYPE_MONTHLY);

            return true;
        });
    }

    private function newBalance(Employee $employee, LeaveType $leaveType, LeaveTier $tier, int $year): LeaveBalance
    {
        return LeaveBalance::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_tier_id' => $tier->id,
            'year' => $year,
        ]);
    }

    private function credit(LeaveBalance $balance, string $column, string $days, \DateTimeInterface $date, string $type): void
    {
        LeaveAccrual::create([
            'leave_balance_id' => $balance->id,
            'employee_id' => $balance->employee_id,
            'accrual_date' => $date->format('Y-m-d'),
            'accrual_type' => $type,
            'days' => $days,
            'description' => $type === LeaveAccrual::TYPE_YEARLY ? 'Yearly entitlement' : 'Monthly accrual',
        ]);

        $balance->{$column} = bcadd((string) ($balance->{$column} ?? '0'), $days, 2);
        $balance->last_accrual_date = $date;
        $balance->recalculateClosingBalance();
        $balance->save();
    }

    private function encashmentAmount(string $days, string $dailyRate, string $ratePercent): string
    {
        return bcmul(bcmul($days, $dailyRate, 4), bcdiv($ratePercent, '100', 4), 2);
    }
}
