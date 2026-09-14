<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\CalculatesLineTotals;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Tax\TaxCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use CalculatesLineTotals, HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'variant_id',
        'description',
        'quantity',
        'quantity_received',
        'quantity_billed',
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
        'subtotal',
        'total',
        'warehouse_id',
        'line_order',
        'account_assignment_type',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_billed' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_value' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'total' => 'decimal:4',
            'line_order' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getRemainingToReceive(): float
    {
        return max(0, (float) bcsub((string) $this->quantity, (string) $this->quantity_received, 4));
    }

    public function getRemainingToBill(): float
    {
        return max(0, (float) bcsub((string) $this->quantity_received, (string) $this->quantity_billed, 4));
    }

    public function isFullyReceived(): bool
    {
        return bccomp((string) $this->quantity_received, (string) $this->quantity, 4) >= 0;
    }

    public function isFullyBilled(): bool
    {
        return bccomp((string) $this->quantity_billed, (string) $this->quantity, 4) >= 0;
    }
}
