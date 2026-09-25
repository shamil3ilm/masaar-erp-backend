<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\PlatformAdmin;
use App\Models\Core\Organization;
use App\Services\Admin\PlatformAdminService;
use App\Services\Admin\PlatformOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformAdminController extends Controller
{
    public function __construct(
        private PlatformAdminService $service,
        private PlatformOrganizationService $organizations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate($request->input('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:platform_admins,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:30',
            'avatar' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
        ]);

        return $this->created($this->service->create($validated));
    }

    public function show(PlatformAdmin $admin): JsonResponse
    {
        return $this->success($admin);
    }

    public function update(Request $request, PlatformAdmin $admin): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('platform_admins', 'email')->ignore($admin->id)],
            'phone' => 'nullable|string|max:30',
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|string|max:30',
            'avatar' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
        ]);

        return $this->success($this->service->update($admin, $validated));
    }

    public function destroy(PlatformAdmin $admin): JsonResponse
    {
        $this->service->delete($admin);

        return $this->success(['message' => 'Admin deleted']);
    }

    public function listOrganizations(Request $request): JsonResponse
    {
        return $this->paginated($this->organizations->paginateOrganizations(
            ['status' => $request->input('status'), 'search' => $request->input('search')],
            $request->input('per_page', 20)
        ));
    }

    public function showOrganization(Organization $organization): JsonResponse
    {
        return $this->success($this->organizations->withCounts($organization));
    }

    public function suspendOrganization(Request $request, Organization $organization): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return $this->success($this->organizations->suspend($organization));
    }

    public function activateOrganization(Organization $organization): JsonResponse
    {
        return $this->success($this->organizations->activate($organization));
    }

    /**
     * Set or clear an organization's parent.
     *
     * The link decides which other organizations an inter-company asset
     * transfer, a consolidation entity or an intercompany sales order may
     * name, so it is a platform-admin decision: the admin route group runs
     * behind the super.admin middleware and no tenant user reaches it.
     */
    public function setOrganizationParent(Request $request, Organization $organization): JsonResponse
    {
        // A platform admin holds no organization of their own, so there is no
        // caller group to narrow the choice of parent to.
        $validated = $request->validate([
            'parent_organization_id' => ['present', 'nullable', 'integer', 'exists:organizations,id'],
        ]);

        return $this->tryAction(
            fn () => $this->organizations->setParent($organization, $validated['parent_organization_id']),
            'Organization parent updated.',
            'INVALID_PARENT',
        );
    }

    public function listUsers(Request $request): JsonResponse
    {
        if (!auth()->user()?->is_super_admin) {
            abort(403, 'Forbidden: super-admin access required.');
        }

        return $this->paginated($this->organizations->paginateUsers(
            $request->input('search'),
            $request->input('per_page', 20)
        ));
    }
}
