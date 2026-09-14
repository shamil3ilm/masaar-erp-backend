<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\Bill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebitNote extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'debit_note_number',
        'bill_id',
        'contact_id',
        'contact_name',
        'debit_note_date',
        'currency_code',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'total',
        'applied_amount',
        'available_amount',
        'reason_code',
        'reason',
        'status',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'debit_note_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'available_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DebitNoteItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function applyToBill(Bill $bill, float $amount): void
    {
        $amountToApply = min($amount, (float) $this->available_amount, (float) $bill->amount_due);

        $this->applied_amount = bcadd((string) $this->applied_amount, (string) $amountToApply, 2);
        $this->available_amount = bcsub((string) $this->available_amount, (string) $amountToApply, 2);

        if (bccomp((string) $this->available_amount, '0', 2) <= 0) {
            $this->status = self::STATUS_APPLIED;
        }

        $this->save();
    }

    public function hasAvailableBalance(): bool
    {
        return bccomp((string) $this->available_amount, '0', 2) > 0;
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_APPLIED])
            ->where('available_amount', '>', 0);
    }
}
