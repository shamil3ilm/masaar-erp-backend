<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToOrganization, HasFactory;

    public const ACCRUAL_ANNUAL = 'annual';
    public const ACCRUAL_MONTHLY = 'monthly';
    public const ACCRUAL_QUARTERLY = 'quarterly';
    public const ACCRUAL_NONE = 'none';

    protected $fillable = [
        'organization_id',
        'leave_policy_id',
        'name',
        'code',
        'description',
        'annual_quota',
        'is_paid',
        'is_encashable',
        'max_encashable_days',
        'carry_forward',
        'max_carry_forward_days',
        'min_days_notice',
        'max_consecutive_days',
        'requires_attachment',
        'attachment_required_after_days',
        'half_day_allowed',
        'requires_approval',
        'applicable_gender',
        'applicable_marital_status',
        'applicable_after_months',
        'employment_type_restriction',
        'requires_reason',
        'min_days_per_request',
        'max_days_per_request',
        'allowed_days_of_week',
        'blackout_dates',
        'count_holidays',
        'count_weekends',
        'accrual_type',
        'accrual_day',
        'prorate_on_joining',
        'prorate_on_exit',
        'color',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_quota' => 'decimal:2',
            'max_encashable_days' => 'decimal:2',
            'max_carry_forward_days' => 'decimal:2',
            'max_consecutive_days' => 'decimal:2',
            'min_days_per_request' => 'decimal:2',
            'max_days_per_request' => 'decimal:2',
            'is_paid' => 'boolean',
            'is_encashable' => 'boolean',
            'carry_forward' => 'boolean',
            'requires_attachment' => 'boolean',
            'requires_reason' => 'boolean',
            'half_day_allowed' => 'boolean',
            'requires_approval' => 'boolean',
            'count_holidays' => 'boolean',
            'count_weekends' => 'boolean',
            'prorate_on_joining' => 'boolean',
            'prorate_on_exit' => 'boolean',
            'allowed_days_of_week' => 'array',
            'blackout_dates' => 'array',
            'min_days_notice' => 'integer',
            'attachment_required_after_days' => 'integer',
            'applicable_after_months' => 'integer',
            'accrual_day' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    public function leaveTiers(): HasMany
    {
        return $this->hasMany(LeaveTier::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isApplicableToEmployee(Employee $employee): bool
    {
        // Check gender
        if ($this->applicable_gender !== 'all' && $employee->gender !== $this->applicable_gender) {
            return false;
        }

        // Check marital status
        if ($this->applicable_marital_status !== 'all' && $employee->marital_status !== $this->applicable_marital_status) {
            return false;
        }

        // Check employment type
        if ($this->employment_type_restriction && $employee->employment_type !== $this->employment_type_restriction) {
            return false;
        }

        // Check tenure
        if ($this->applicable_after_months > 0 && $employee->getTenureInMonths() < $this->applicable_after_months) {
            return false;
        }

        return true;
    }

    public function requiresAttachmentForDays(float $days): bool
    {
        if (!$this->requires_attachment) {
            return false;
        }

        if ($this->attachment_required_after_days <= 0) {
            return true;
        }

        return $days > $this->attachment_required_after_days;
    }

    public function getMonthlyAccrual(): float
    {
        return match ($this->accrual_type) {
            self::ACCRUAL_NONE => 0.0,
            self::ACCRUAL_MONTHLY => (float) $this->annual_quota / 12,
            self::ACCRUAL_QUARTERLY => (float) $this->annual_quota / 4,
            default => (float) $this->annual_quota, // Annual - credited at once
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeEncashable($query)
    {
        return $query->where('is_encashable', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
