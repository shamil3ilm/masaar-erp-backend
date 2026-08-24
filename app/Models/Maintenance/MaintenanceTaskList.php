<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceTaskList extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = ['uuid', 'organization_id', 'task_list_number', 'description'];

    public function operations(): HasMany
    {
        return $this->hasMany(TaskListOperation::class, 'task_list_id')->orderBy('operation_number');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(CounterBasedPlan::class, 'task_list_id');
    }
}
