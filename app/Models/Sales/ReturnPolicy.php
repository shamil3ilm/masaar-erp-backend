<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ReturnPolicy extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'return_window_days' => 'integer',
            'restocking_fee_percent' => 'decimal:2',
            'allow_exchange' => 'boolean',
            'allow_refund' => 'boolean',
            'allow_credit_note' => 'boolean',
            'require_receipt' => 'boolean',
            'require_original_packaging' => 'boolean',
            'require_approval' => 'boolean',
            'non_returnable_categories' => 'array',
            'condition_requirements' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Whether goods sold on $saleDate can still be returned today. The last
     * day of the window counts.
     */
    public function isWithinReturnWindow(\DateTimeInterface $saleDate): bool
    {
        $lastDay = Carbon::instance($saleDate)->startOfDay()->addDays($this->return_window_days);

        return now()->startOfDay()->lte($lastDay);
    }

    /**
     * The fee kept on a returned subtotal, to two decimal places.
     */
    public function calculateRestockingFee(float $subtotal): string
    {
        return bcdiv(bcmul((string) $subtotal, (string) $this->restocking_fee_percent, 4), '100', 2);
    }
}
