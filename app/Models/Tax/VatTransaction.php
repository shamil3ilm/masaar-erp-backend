<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasUuid;

class VatTransaction extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_CREDIT_NOTE = 'credit_note';
    public const TYPE_REFUND = 'refund';
    public const TYPE_RETURN = 'return';

    /** Reverse a sale, so their amounts are zero or negative. */
    public const REVERSAL_TYPES = [self::TYPE_CREDIT_NOTE, self::TYPE_REFUND, self::TYPE_RETURN];

    /** Count toward output VAT: sales and what reverses them. */
    public const OUTPUT_TYPES = [self::TYPE_SALE, ...self::REVERSAL_TYPES];

    public const TYPES = [self::TYPE_SALE, self::TYPE_PURCHASE, ...self::REVERSAL_TYPES];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tax_period'      => 'date',
            'taxable_amount'  => 'decimal:4',
            'vat_amount'      => 'decimal:4',
            'vat_rate'        => 'decimal:2',
            'is_exempt'       => 'boolean',
            'is_zero_rated'   => 'boolean',
        ];
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('tax_period', [$start, $end]);
    }

    public function scopeOutputTax($query)
    {
        return $query->whereIn('transaction_type', self::OUTPUT_TYPES);
    }

    public function scopeInputTax($query)
    {
        return $query->where('transaction_type', self::TYPE_PURCHASE);
    }
}
