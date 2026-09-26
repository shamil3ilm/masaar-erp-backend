<?php

declare(strict_types=1);

namespace App\Models\Sales;

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

class InvoiceLine extends Model
{
    use CalculatesLineTotals, HasFactory;

    protected $fillable = [
        'invoice_id',
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
        'tax_exemption_code',
        'tax_exemption_reason',
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
            'original_price' => 'decimal:4',
            'line_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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

    /**
     * Get tax breakdown.
     */
    public function getTaxBreakdown(): array
    {
        if ($this->igst_amount > 0) {
            return [
                'type' => 'igst',
                'igst' => ['rate' => $this->igst_rate, 'amount' => $this->igst_amount],
            ];
        }

        if ($this->cgst_amount > 0 || $this->sgst_amount > 0) {
            return [
                'type' => 'cgst_sgst',
                'cgst' => ['rate' => $this->cgst_rate, 'amount' => $this->cgst_amount],
                'sgst' => ['rate' => $this->sgst_rate, 'amount' => $this->sgst_amount],
            ];
        }

        return [
            'type' => 'vat',
            'vat' => ['rate' => $this->tax_rate, 'amount' => $this->tax_amount],
        ];
    }
}
