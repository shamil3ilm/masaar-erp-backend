<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdvancePaymentApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'advance_payment_id',
        'applied_to_type',
        'applied_to_id',
        'applied_amount',
        'applied_date',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'applied_date'   => 'date',
            'applied_amount' => 'decimal:2',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function advancePayment(): BelongsTo
    {
        return $this->belongsTo(AdvancePayment::class);
    }

    /**
     * The invoice or bill the advance was applied to.
     */
    public function appliedTo(): MorphTo
    {
        return $this->morphTo();
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
