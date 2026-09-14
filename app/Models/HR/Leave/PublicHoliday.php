<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Core\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicHoliday extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Holidays that apply to a branch: those set for it and those set for the
     * whole organization, as Holiday::scopeForBranch reads them.
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_optional', false);
    }
}
