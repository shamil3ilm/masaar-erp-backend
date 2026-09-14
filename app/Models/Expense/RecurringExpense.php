<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpense extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    // Frequencies, as the recurring expense endpoint accepts them
    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_QUARTERLY = 'quarterly';

    public const FREQUENCY_YEARLY = 'yearly';

    public const FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_QUARTERLY,
        self::FREQUENCY_YEARLY,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_occurrence' => 'date',
            'is_active' => 'boolean',
            'frequency_interval' => 'integer',
            'occurrences_count' => 'integer',
            'max_occurrences' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recurring expenses that are ready to be raised.
     *
     * Active, started, not finished, and due on or before today.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_occurrence')
            ->whereDate('next_occurrence', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()));
    }

    /**
     * Move past the occurrence just raised.
     *
     * The template stops once it reaches its maximum number of occurrences,
     * or when its next date falls after its end date. A month-end date moves
     * to the last day of a shorter month rather than into the one after.
     */
    public function advanceNextOccurrence(): void
    {
        $interval = max(1, (int) $this->frequency_interval);
        $next = $this->next_occurrence->copy();

        $next = match ($this->frequency) {
            self::FREQUENCY_DAILY => $next->addDays($interval),
            self::FREQUENCY_WEEKLY => $next->addWeeks($interval),
            self::FREQUENCY_MONTHLY => $next->addMonthsNoOverflow($interval),
            self::FREQUENCY_QUARTERLY => $next->addMonthsNoOverflow(3 * $interval),
            self::FREQUENCY_YEARLY => $next->addYearsNoOverflow($interval),
            default => throw new \UnexpectedValueException("Unknown recurring expense frequency: {$this->frequency}"),
        };

        $this->occurrences_count = (int) $this->occurrences_count + 1;
        $this->next_occurrence = $next;

        $reachedMaximum = $this->max_occurrences !== null && $this->occurrences_count >= $this->max_occurrences;
        $pastEnd = $this->end_date !== null && $next->gt($this->end_date);

        if ($reachedMaximum || $pastEnd) {
            $this->is_active = false;
        }

        $this->save();
    }
}
