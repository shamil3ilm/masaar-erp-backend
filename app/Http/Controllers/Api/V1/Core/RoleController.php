<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Core\Role;
use App\Services\Core\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    /**
     * List roles with permissions, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = $this->roles->list(
            $request->user()->organization_id,
            [
                'search' => $request->input('search'),
                'is_system' => $request->has('is_system') ? $request->boolean('is_system') : null,
            ],
            $request->integer('per_page', 15),
        );

        return $this->paginated($roles);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('organization_id', $organizationId)],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('roles', 'slug')->where('organization_id', $organizationId)],
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role = $this->roles->create($request->user(), $validated);

        return $this->created(new RoleResource($role), 'Role created successfully.');
    }

    /**
     * Show a role with permissions.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('core.roles.view'), 403, 'Permission denied.');

        return $this->success(new RoleResource($this->roles->details($role)));
    }

    /**
     * Update a role and sync permissions.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('core.roles.edit'), 403, 'Permission denied.');

        // Answered before validation, so a system role is refused whatever the payload.
        if ($role->is_system) {
            return $this->error('System roles cannot be modified.', 'VALIDATION_ERROR', 422);
        }

        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('roles', 'name')->where('organization_id', $organizationId)->ignore($role->id)],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('roles', 'slug')->where('organization_id', $organizationId)->ignore($role->id)],
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role = $this->roles->update($request->user(), $role, $validated);

        return $this->success(new RoleResource($role), 'Role updated successfully.');
    }

    /**
     * Delete a role (only if no users are assigned).
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('core.roles.delete'), 403, 'Permission denied.');

        return $this->tryAction(fn () => $this->roles->delete($role), 'Role deleted successfully.');
    }
}
