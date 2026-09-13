<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\HR\Leave\LeaveTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'leave_type_id',
        'leave_tier_id',
        'year',
        'opening_balance',
        'entitled',
        'accrued',
        'taken',
        'adjustment',
        'encashed',
        'lapsed',
        'closing_balance',
        'last_accrual_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opening_balance' => 'decimal:2',
            'entitled' => 'decimal:2',
            'accrued' => 'decimal:2',
            'taken' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'encashed' => 'decimal:2',
            'lapsed' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'last_accrual_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveTier(): BelongsTo
    {
        return $this->belongsTo(LeaveTier::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveAdjustment::class);
    }

    /**
     * The opening balance and everything credited, less everything spent.
     */
    public function recalculateClosingBalance(): void
    {
        $credited = '0';
        foreach (['opening_balance', 'entitled', 'accrued', 'adjustment'] as $key) {
            $credited = bcadd($credited, (string) ($this->getAttribute($key) ?? '0'), 2);
        }

        $spent = '0';
        foreach (['taken', 'encashed', 'lapsed'] as $key) {
            $spent = bcadd($spent, (string) ($this->getAttribute($key) ?? '0'), 2);
        }

        $this->closing_balance = bcsub($credited, $spent, 2);
    }

    public function getAvailableBalance(): float
    {
        return max(0, (float) $this->closing_balance);
    }

    public function hasBalance(float $days): bool
    {
        return $this->getAvailableBalance() >= $days;
    }

    public function deductLeave(float $days): void
    {
        $this->taken = bcadd((string) $this->taken, (string) $days, 2);
        $this->recalculateClosingBalance();
        $this->save();
    }

    /**
     * Give back leave that was taken and then cancelled.
     */
    public function creditLeave(float $days): void
    {
        $this->taken = bcsub((string) $this->taken, (string) $days, 2);
        $this->recalculateClosingBalance();
        $this->save();
    }

    public function adjustBalance(float $days, string $reason = ''): void
    {
        $this->adjustment = bcadd((string) $this->adjustment, (string) $days, 2);
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . now()->format('Y-m-d') . ": {$reason}";
        }
        $this->recalculateClosingBalance();
        $this->save();
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForLeaveType($query, int $leaveTypeId)
    {
        return $query->where('leave_type_id', $leaveTypeId);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('closing_balance', '>', 0);
    }
}
