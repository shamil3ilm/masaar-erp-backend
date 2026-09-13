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

class LeaveAdjustment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_ADD = 'add';
    public const TYPE_DEDUCT = 'deduct';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'effective_date' => 'date',
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
