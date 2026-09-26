<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeOrderItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'original_quantity' => 'decimal:4',
            'original_unit_price' => 'decimal:4',
            'price_difference' => 'decimal:2',
            'replacement_quantity' => 'decimal:4',
            'replacement_unit_price' => 'decimal:4',
        ];
    }
}