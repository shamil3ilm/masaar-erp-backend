<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\HR\DepartmentResource;
use App\Models\HR\Department;
use App\Services\HR\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $service,
    ) {}

    /**
     * List departments with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $departments = $this->service->list(
            [
                'search'    => $request->search,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
                'root_only' => $request->boolean('root_only', false),
                'parent_id' => $request->parent_id,
            ],
            $this->safeSortBy($request->sort_by, ['name', 'code', 'created_at', 'updated_at'], 'name'),
            $this->safeSortOrder($request->sort_order, 'asc'),
            $request->integer('per_page', 15)
        );

        return $this->paginated($departments, DepartmentResource::class);
    }

    /**
     * Create a new department.
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departments', 'name')
                    ->where('organization_id', $organizationId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('departments', 'code')
                    ->where('organization_id', $organizationId),
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $organizationId)],
            // The manager is shown with the department, name and email included.
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'cost_center_id' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $department = $this->service->create($validated, $organizationId);

        return $this->created(new DepartmentResource($department), 'Department created successfully.');
    }

    /**
     * Show a specific department.
     */
    public function show(Department $department): JsonResponse
    {
        $department->load(['parent', 'children', 'manager'])
            ->loadCount(['employees', 'activeEmployees']);

        return $this->success(new DepartmentResource($department));
    }

    /**
     * Update a department.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('departments', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($department->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('departments', 'code')
                    ->where('organization_id', $organizationId)
                    ->ignore($department->id),
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('organization_id', $organizationId),
                Rule::notIn([$department->id]),
            ],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'cost_center_id' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $department = $this->service->update($department, $validated);

        return $this->success(new DepartmentResource($department), 'Department updated successfully.');
    }

    /**
     * Delete a department.
     */
    public function destroy(Department $department): JsonResponse
    {
        try {
            $this->service->delete($department);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(null, 'Department deleted successfully.');
    }
}
