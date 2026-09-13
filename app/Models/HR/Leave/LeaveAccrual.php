<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAccrual extends Model
{
    use HasFactory;

    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_YEARLY = 'yearly';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'days' => 'decimal:2',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
