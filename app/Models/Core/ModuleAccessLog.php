<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAccessLog extends Model
{
    use HasFactory, BelongsToOrganization;

    /** The table records when access happened in accessed_at and has no created_at or updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'user_id', 'module_id', 'action', 'entity_type',
        'entity_id', 'was_allowed', 'denial_reason', 'ip_address', 'accessed_at',
    ];

    protected $casts = [
        'was_allowed' => 'boolean',
        'accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleDefinition::class, 'module_id');
    }
}
