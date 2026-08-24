<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskListOperation extends Model
{
    protected $table = 'task_list_operations';

    protected $fillable = [
        'task_list_id', 'operation_number', 'description',
        'work_center_id', 'planned_hours',
    ];

    protected $casts = ['planned_hours' => 'decimal:2'];

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTaskList::class, 'task_list_id');
    }
}
