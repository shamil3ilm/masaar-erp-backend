<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FxForward extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'fx_forwards';

    protected $guarded = ['id'];

    /** The scale the notional and gain/loss columns hold. */
    private const AMOUNT_SCALE = 4;

    protected function casts(): array
    {
        return [
            'notional_amount'       => 'decimal:4',
            'forward_rate'          => 'decimal:8',
            'settlement_rate'       => 'decimal:8',
            'settlement_gain_loss'  => 'decimal:4',
            'trade_date'            => 'date',
            'maturity_date'         => 'date',
            'settled_at'            => 'date',
        ];
    }

    public function hedgeRelation(): HasOne
    {
        return $this->hasOne(FxHedgeRelation::class, 'fx_forward_id');
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(FxValuation::class, 'fx_forward_id')->orderBy('valuation_date');
    }

    public function latestValuation(): HasOne
    {
        return $this->hasOne(FxValuation::class, 'fx_forward_id')->latestOfMany('valuation_date');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Notional value in sell_currency (buy side notional × forward rate), as a
     * decimal string at the scale the amount columns hold.
     *
     * The product is truncated, the way the valuation and the settlement
     * truncate theirs, so the figure shown for a contract is the figure its
     * posting books rather than a rounding above it.
     */
    public function notionalInSellCurrency(): string
    {
        return bcmul((string) $this->notional_amount, (string) $this->forward_rate, self::AMOUNT_SCALE);
    }
}
