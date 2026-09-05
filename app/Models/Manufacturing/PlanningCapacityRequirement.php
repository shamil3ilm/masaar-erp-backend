<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningCapacityRequirement extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'planning_simulation_id',
        'work_center_id',
        'calendar_date',
        'required_hours',
        'available_hours',
        'utilization_percentage',
    ];

    protected $casts = [
        'calendar_date'          => 'date',
        'required_hours'         => 'decimal:4',
        'available_hours'        => 'decimal:4',
        'utilization_percentage' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function simulation(): BelongsTo
    {
        return $this->belongsTo(PlanningSimulation::class, 'planning_simulation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }
}
