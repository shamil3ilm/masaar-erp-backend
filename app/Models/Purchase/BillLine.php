<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\Account;
use App\Models\Concerns\CalculatesLineTotals;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Tax\TaxCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends Model
{
    use CalculatesLineTotals, HasFactory;

    protected $fillable = [
        'bill_id',
        'product_id',
        'variant_id',
        'description',
        'quantity',
        'unit_id',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_category_id',
        'tax_rate',
        'tax_amount',
        'tax_code',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'hsn_code',
        'subtotal',
        'total',
        'account_id',
        'warehouse_id',
        'line_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_value' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'cgst_rate' => 'decimal:4',
            'cgst_amount' => 'decimal:4',
            'sgst_rate' => 'decimal:4',
            'sgst_amount' => 'decimal:4',
            'igst_rate' => 'decimal:4',
            'igst_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'total' => 'decimal:4',
            'line_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (BillLine $line) {
            $bill = $line->bill;
            if ($bill && in_array($bill->status, [Bill::STATUS_DRAFT, Bill::STATUS_PENDING], true)) {
                $bill->recalculateTotals();
            }
        });

        static::deleted(function (BillLine $line) {
            $bill = $line->bill;
            if ($bill && in_array($bill->status, [Bill::STATUS_DRAFT, Bill::STATUS_PENDING], true)) {
                $bill->recalculateTotals();
            }
        });
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected function splitsGst(): bool
    {
        return true;
    }
}
