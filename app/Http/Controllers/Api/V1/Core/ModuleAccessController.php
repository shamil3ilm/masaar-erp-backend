<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Role;
use App\Models\User;
use App\Services\Core\ModuleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleAccessController extends Controller
{
    public function __construct(private ModuleAccessService $service) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->getModules());
    }

    public function orgModules(): JsonResponse
    {
        return $this->success($this->service->getOrganizationModules(auth()->user()->organization_id));
    }

    public function setModuleActive(Request $request, int $moduleId): JsonResponse
    {
        $request->validate(['active' => 'required|boolean']);

        if (! $this->service->findModule($moduleId)) {
            return $this->notFound('Module not found.');
        }

        if ($request->boolean('active')) {
            $access = $this->service->enableModule(auth()->user()->organization_id, $moduleId, auth()->id());
            return $this->success($access->load('module'));
        }

        $access = $this->service->disableModule(auth()->user()->organization_id, $moduleId);
        return $this->success($access);
    }

    public function rolePermissions(Role $role): JsonResponse
    {
        return $this->success($this->service->getRolePermissions($role->id));
    }

    public function setRolePermission(Request $request, Role $role, int $moduleId): JsonResponse
    {
        $permission = $this->service->setRolePermission($role->id, $moduleId, $request->all());
        return $this->success($permission);
    }

    public function userOverrides(User $user): JsonResponse
    {
        return $this->success($this->service->getUserOverrides($user->id));
    }

    public function setUserOverride(Request $request, User $user, int $moduleId): JsonResponse
    {
        $override = $this->service->setUserOverride($user->id, $moduleId, $request->all(), auth()->id());
        return $this->success($override);
    }

    public function removeUserOverride(User $user, int $moduleId): JsonResponse
    {
        if (! $this->service->removeUserOverride($user->id, $moduleId)) {
            return $this->notFound('Override not found.');
        }

        return $this->success(['message' => 'Override removed']);
    }

    public function menuItems(): JsonResponse
    {
        $roleId = auth()->user()->roles->first()?->id;
        if (!$roleId) {
            return $this->success([]);
        }
        return $this->success($this->service->getMenuItems($roleId));
    }

    public function accessLogs(Request $request): JsonResponse
    {
        return $this->paginated($this->service->getAccessLogs($request->integer('per_page', 50)));
    }
}
