<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Portfolio extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'portfolios';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type',
        'currency_code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'portfolio_id');
    }
}
