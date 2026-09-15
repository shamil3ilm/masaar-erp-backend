<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Core\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly UserService $users) {}

    /**
     * List users in the organization with search/filter and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->users->list(
            $request->user()->organization_id,
            [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
                'branch_id' => $request->input('branch_id'),
            ],
            $this->safeSortBy($request->input('sort_by'), UserService::SORTABLE, 'name'),
            $this->safeSortOrder($request->input('sort_order'), 'asc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($users, UserResource::class);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => ['integer', $this->ownedBy('roles')],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => ['integer', $this->ownedBy('branches')],
            'default_branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
        ]);

        $user = $this->users->create($request->user(), $validated);

        return $this->created(new UserResource($user), 'User created successfully.');
    }

    /**
     * Show a single user with roles and branches.
     */
    public function show(User $user): JsonResponse
    {
        $this->authorizeOrganizationAccess($user);

        return $this->success(new UserResource($this->users->details($user)));
    }

    /**
     * Update user details, roles, and branches.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeOrganizationAccess($user);

        if (! $this->users->mayManage($request->user(), $user)) {
            return $this->forbidden('You cannot change a user who holds permissions you do not have.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => ['integer', $this->ownedBy('roles')],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => ['integer', $this->ownedBy('branches')],
            'default_branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
        ]);

        $user = $this->users->update($request->user(), $user, $validated);

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    /**
     * Soft delete / deactivate a user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeOrganizationAccess($user);

        if (! $this->users->mayManage($request->user(), $user)) {
            return $this->forbidden('You cannot remove a user who holds permissions you do not have.');
        }

        return $this->tryAction(
            fn () => $this->users->deactivate($request->user(), $user),
            'User deactivated successfully.'
        );
    }

    /**
     * Users carry no organization scope, so a user bound from the route may
     * belong to another organization.
     */
    private function authorizeOrganizationAccess(User $user): void
    {
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You do not have access to this user.');
        }
    }
}
