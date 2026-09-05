<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CO Reconciliation Run — SAP KALC.
 *
 * One run per CO cross-company posting (assessment / distribution / confirmation).
 * The run holds the generated FI reconciliation entries that bring FI into
 * balance with CO.
 */
class CostReconciliationRun extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'cost_reconciliation_runs';

    protected $guarded = ['id'];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'posted_at'    => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CostReconciliationEntry::class, 'reconciliation_run_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }
}
