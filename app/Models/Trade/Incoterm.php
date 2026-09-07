<?php

declare(strict_types=1);

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incoterm extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForVersion(Builder $query, string $version): Builder
    {
        return $query->where('version', $version);
    }

    public function scopeLatestVersion(Builder $query): Builder
    {
        return $query->where('version', static::max('version'));
    }

    /**
     * transport_modes is a json array of the modes a term allows.
     */
    public function scopeForSeaTransport(Builder $query): Builder
    {
        return $query->whereJsonContains('transport_modes', 'sea');
    }

    public function scopeForAllTransport(Builder $query): Builder
    {
        return $query;
    }
}
