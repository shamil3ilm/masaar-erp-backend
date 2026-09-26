<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCenterBudgetLine extends Model
{
    protected $fillable = [
        'cost_center_budget_id',
        'period',
        'cost_element_id',
        'budgeted_amount',
        'committed_amount',
        'actual_amount',
    ];

    protected function casts(): array
    {
        return [
            'period'           => 'integer',
            'budgeted_amount'  => 'decimal:4',
            'committed_amount' => 'decimal:4',
            'actual_amount'    => 'decimal:4',
            'available_amount' => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function budget(): BelongsTo
    {
        return $this->belongsTo(CostCenterBudget::class, 'cost_center_budget_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function getAvailableAmount(): float
    {
        return $this->budgeted_amount - $this->committed_amount - $this->actual_amount;
    }
}
