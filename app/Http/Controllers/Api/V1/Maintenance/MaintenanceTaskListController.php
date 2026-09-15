<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceTaskList;
use App\Services\Maintenance\MaintenanceTaskListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceTaskListController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly MaintenanceTaskListService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate(
            $request->user()->organization_id,
            $request->filled('search') ? $request->input('search') : null,
            $request->integer('per_page', 15)
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_list_number' => 'required|string|max:20',
            'description' => 'required|string|max:255',
            'operations' => 'array',
            'operations.*.operation_number' => 'required|string|max:10',
            'operations.*.description' => 'required|string|max:255',
            'operations.*.work_center_id' => ['nullable', $this->ownedBy('work_centers')],
            'operations.*.planned_hours' => 'nullable|numeric|min:0',
        ]);

        return $this->created($this->service->create($request->user()->organization_id, $validated));
    }

    public function show(MaintenanceTaskList $taskList): JsonResponse
    {
        return $this->success($taskList->load('operations'));
    }

    public function update(Request $request, MaintenanceTaskList $taskList): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
        ]);

        return $this->success($this->service->update($taskList, $validated));
    }

    public function destroy(MaintenanceTaskList $taskList): JsonResponse
    {
        $this->service->delete($taskList);

        return $this->success(null, 'Task list deleted');
    }

    public function storeOperation(Request $request, MaintenanceTaskList $taskList): JsonResponse
    {
        $validated = $request->validate([
            'operation_number' => 'required|string|max:10',
            'description' => 'required|string|max:255',
            'work_center_id' => ['nullable', $this->ownedBy('work_centers')],
            'planned_hours' => 'nullable|numeric|min:0',
        ]);

        return $this->created($this->service->addOperation($taskList, $validated));
    }

    public function destroyOperation(MaintenanceTaskList $taskList, int $operation): JsonResponse
    {
        $this->service->removeOperation($taskList, $operation);

        return $this->success(null, 'Operation removed');
    }
}
