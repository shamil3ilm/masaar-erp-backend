<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicy extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
