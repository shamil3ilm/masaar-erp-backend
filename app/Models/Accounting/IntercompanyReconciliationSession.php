<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntercompanyReconciliationSession extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'intercompany_reconciliation_sessions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period'           => 'integer',
            'items_count'      => 'integer',
            'matched_count'    => 'integer',
            'unmatched_count'  => 'integer',
            'matched_amount'   => 'decimal:4',
            'difference_amount' => 'decimal:4',
            'completed_at'     => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(IntercompanyReconciliationItem::class, 'session_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(IntercompanyReconciliationMatch::class, 'session_id');
    }
}
