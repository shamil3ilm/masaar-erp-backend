<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CurrencyRevaluation extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    /** The scale the total and item amount columns hold. */
    private const SCALE = 4;

    protected $fillable = [
        'organization_id',
        'revaluation_number',
        'revaluation_date',
        'currency_code',
        'old_rate',
        'new_rate',
        'base_currency',
        'total_unrealized_gain',
        'total_unrealized_loss',
        'net_gain_loss',
        'gain_loss_account_id',
        'journal_entry_id',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'revaluation_date' => 'date',
            'old_rate' => 'decimal:8',
            'new_rate' => 'decimal:8',
            'total_unrealized_gain' => 'decimal:4',
            'total_unrealized_loss' => 'decimal:4',
            'net_gain_loss' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(CurrencyRevaluationItem::class, 'revaluation_id');
    }

    public function gainLossAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gain_loss_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', $currencyCode);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('revaluation_date', [$from, $to]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    public function canReverse(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Restate the stored totals from the items: the gains and the losses each
     * as a positive figure, and the net as their sum.
     *
     * The items are added in decimal strings at the scale the total columns
     * hold, so a total states every ten-thousandth the items carry rather than
     * what a float sum happens to land on, and agrees with the net the posting
     * books.
     */
    public function recalculateTotals(): void
    {
        $zero = bcadd('0', '0', self::SCALE);
        $gains = $zero;
        $losses = $zero;

        foreach ($this->items as $item) {
            $amount = self::amount($item->gain_loss_amount);

            if (bccomp($amount, $zero, self::SCALE) > 0) {
                $gains = bcadd($gains, $amount, self::SCALE);
            } else {
                $losses = bcadd($losses, $amount, self::SCALE);
            }
        }

        $this->total_unrealized_gain = $gains;
        $this->total_unrealized_loss = bcsub($zero, $losses, self::SCALE);
        $this->net_gain_loss = bcadd($gains, $losses, self::SCALE);

        $this->saveQuietly();
    }

    /** An item amount as a decimal string at the scale the amount columns hold. */
    private static function amount(float|int|string|null $amount): string
    {
        return Decimal::at($amount, self::SCALE);
    }
}
