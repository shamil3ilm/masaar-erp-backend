<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A role with the permissions it grants. The permission list is empty when
 * it was not loaded; users_count appears only when it was counted.
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'permissions' => $this->relationLoaded('permissions')
                ? $this->permissions->map(fn ($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'module' => $permission->module,
                ])
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'users_count' => $this->when(
                array_key_exists('users_count', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('users_count')
            ),
        ];
    }
}
