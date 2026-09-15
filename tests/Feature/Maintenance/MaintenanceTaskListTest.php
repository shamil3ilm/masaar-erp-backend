<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\MaintenanceTaskList;
use App\Models\Maintenance\TaskListOperation;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins task list creation and search, keeps operations on the caller's own
 * work centers, and removes an operation only through its own task list.
 */
class MaintenanceTaskListTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['maintenance.task-lists.view', 'maintenance.task-lists.manage']);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_a_task_list_is_created_with_its_operations_and_found_by_its_description(): void
    {
        $this->apiPost('/maintenance/task-lists', [
            'task_list_number' => 'TL-1',
            'description' => 'Pump overhaul',
            'operations' => [
                ['operation_number' => '10', 'description' => 'Drain', 'planned_hours' => 1],
                ['operation_number' => '20', 'description' => 'Replace seals', 'planned_hours' => 2],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.operations');
        $this->taskList($this->organization, 'Fan check');

        $this->apiGet('/maintenance/task-lists?search=Pump')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Pump overhaul');
    }

    public function test_an_operation_on_another_organizations_work_center_is_refused(): void
    {
        $theirWorkCenter = WorkCenter::factory()->create(['organization_id' => $this->other->id]);
        $taskList = $this->taskList($this->organization, 'Pump overhaul');

        $this->apiPost('/maintenance/task-lists', [
            'task_list_number' => 'TL-2',
            'description' => 'Fan check',
            'operations' => [
                ['operation_number' => '10', 'description' => 'Inspect', 'work_center_id' => $theirWorkCenter->id, 'planned_hours' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['operations.0.work_center_id']);

        $this->apiPost("/maintenance/task-lists/{$taskList->id}/operations", [
            'operation_number' => '10',
            'description' => 'Inspect',
            'work_center_id' => $theirWorkCenter->id,
            'planned_hours' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['work_center_id']);

        $this->assertSame(0, TaskListOperation::count());
    }

    public function test_an_operation_is_removed_only_through_its_own_task_list(): void
    {
        $ours = $this->taskList($this->organization, 'Pump overhaul');
        $ourOperation = $this->operation($ours);
        $siblingOperation = $this->operation($this->taskList($this->organization, 'Fan check'));
        $theirOperation = $this->operation($this->taskList($this->other, 'Their list'));

        $this->apiDelete("/maintenance/task-lists/{$ours->id}/operations/{$siblingOperation->id}")->assertNotFound();
        $this->apiDelete("/maintenance/task-lists/{$ours->id}/operations/{$theirOperation->id}")->assertNotFound();
        $this->apiDelete("/maintenance/task-lists/{$ours->id}/operations/{$ourOperation->id}")->assertOk();

        $this->assertNotNull($siblingOperation->fresh());
        $this->assertNotNull($theirOperation->fresh());
        $this->assertNull($ourOperation->fresh());
    }

    public function test_another_organizations_task_list_is_not_found(): void
    {
        $theirs = $this->taskList($this->other, 'Their list');

        $this->apiGet("/maintenance/task-lists/{$theirs->id}")->assertNotFound();
        $this->apiDelete("/maintenance/task-lists/{$theirs->id}")->assertNotFound();
    }

    private function taskList(Organization $organization, string $description): MaintenanceTaskList
    {
        return MaintenanceTaskList::create([
            'organization_id' => $organization->id,
            'task_list_number' => 'TL-'.fake()->unique()->numerify('#####'),
            'description' => $description,
        ]);
    }

    private function operation(MaintenanceTaskList $taskList): TaskListOperation
    {
        return $taskList->operations()->create([
            'operation_number' => 10,
            'description' => 'Inspect',
            'planned_hours' => 1,
        ]);
    }
}
