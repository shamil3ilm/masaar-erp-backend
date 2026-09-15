<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\MaintenanceTaskList;
use App\Models\Maintenance\TaskListOperation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance task lists and their operations. Operations have no
 * organization column of their own and are reached through their task list.
 */
class MaintenanceTaskListService
{
    public function paginate(int $organizationId, mixed $search, int $perPage): LengthAwarePaginator
    {
        return MaintenanceTaskList::query()
            ->with('operations')
            ->where('organization_id', $organizationId)
            ->when($search, fn ($query, $term) => $query->where('description', 'like', "%{$term}%"))
            ->paginate($perPage);
    }

    /**
     * Create a task list with its operations, together or not at all.
     *
     * @param  array{task_list_number: string, description: string, operations?: list<array<string, mixed>>}  $data
     */
    public function create(int $organizationId, array $data): MaintenanceTaskList
    {
        return DB::transaction(function () use ($organizationId, $data): MaintenanceTaskList {
            $taskList = MaintenanceTaskList::create([
                'organization_id' => $organizationId,
                'task_list_number' => $data['task_list_number'],
                'description' => $data['description'],
            ]);

            foreach ($data['operations'] ?? [] as $operation) {
                $taskList->operations()->create($operation);
            }

            return $taskList->load('operations');
        });
    }

    public function update(MaintenanceTaskList $taskList, array $data): MaintenanceTaskList
    {
        $taskList->update($data);

        return $taskList->load('operations');
    }

    public function delete(MaintenanceTaskList $taskList): void
    {
        $taskList->delete();
    }

    public function addOperation(MaintenanceTaskList $taskList, array $data): TaskListOperation
    {
        return $taskList->operations()->create($data);
    }

    /**
     * Remove one of the task list's operations; an operation of any other
     * task list is not found.
     */
    public function removeOperation(MaintenanceTaskList $taskList, int $operationId): void
    {
        $taskList->operations()->findOrFail($operationId)->delete();
    }
}
