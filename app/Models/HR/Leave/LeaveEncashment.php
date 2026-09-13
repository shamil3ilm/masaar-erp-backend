<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEncashment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_days' => 'decimal:2',
            'approved_days' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'encashment_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
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

    public function balance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
