<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeExit;
use App\Models\HR\ExitClearanceItem;
use App\Services\HR\ExitManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Employee exits: initiated, approved into notice, cleared, settled and
 * closed, one step at a time and inside the organization that owns them.
 */
class ExitManagementTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/exit-management';

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.lifecycle.view', 'hr.lifecycle.manage']);

        $this->employee = $this->employee($this->organization);
    }

    public function test_an_exit_is_initiated_for_an_employee_of_the_organization(): void
    {
        $this->apiPost($this->baseUrl, [
            'employee_id' => $this->employee->id,
            'exit_type' => 'resignation',
            'exit_reason' => 'Relocating',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', EmployeeExit::STATUS_INITIATED)
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.initiator.id', $this->user->id);
    }

    public function test_an_exit_cannot_name_another_organizations_employee(): void
    {
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost($this->baseUrl, ['employee_id' => $theirs->id, 'exit_type' => 'termination'])
            ->assertStatus(422);

        $this->assertSame(0, EmployeeExit::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_exit_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirs = $this->exit($other, $this->employee($other));

        $this->apiGet("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->id}/approve")->assertNotFound();
    }

    public function test_an_exit_moves_through_notice_clearance_settlement_and_closure(): void
    {
        $exit = $this->exit($this->organization, $this->employee);

        $this->apiPost("{$this->baseUrl}/{$exit->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeExit::STATUS_NOTICE_PERIOD)
            ->assertJsonPath('data.approver.id', $this->user->id);

        $this->apiPost("{$this->baseUrl}/{$exit->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->apiPost("{$this->baseUrl}/{$exit->id}/start-clearance")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeExit::STATUS_CLEARANCE_IN_PROGRESS)
            ->assertJsonCount(8, 'data.clearance_items');

        $this->apiPost("{$this->baseUrl}/{$exit->id}/complete-clearance")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        foreach (ExitClearanceItem::where('employee_exit_id', $exit->id)->pluck('id') as $itemId) {
            $this->apiPost("{$this->baseUrl}/{$exit->id}/clearance-items/{$itemId}/clear", ['remarks' => 'Done'])
                ->assertOk()
                ->assertJsonPath('data.status', ExitClearanceItem::STATUS_CLEARED);
        }

        $this->apiPost("{$this->baseUrl}/{$exit->id}/complete-clearance")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeExit::STATUS_CLEARANCE_COMPLETE);

        $this->apiPost("{$this->baseUrl}/{$exit->id}/settle", ['final_settlement_amount' => 1000])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeExit::STATUS_SETTLED)
            ->assertJsonPath('data.employee.id', $this->employee->id);

        $this->apiPost("{$this->baseUrl}/{$exit->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeExit::STATUS_CLOSED);

        $this->apiPost("{$this->baseUrl}/{$exit->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_clearance_item_is_cleared_only_through_its_own_exit(): void
    {
        $first = $this->exit($this->organization, $this->employee);
        $second = $this->exit($this->organization, $this->employee($this->organization));
        app(ExitManagementService::class)->startClearance($first);

        $itemOfFirst = ExitClearanceItem::where('employee_exit_id', $first->id)->value('id');

        $this->apiPost("{$this->baseUrl}/{$second->id}/clearance-items/{$itemOfFirst}/clear")->assertNotFound();
    }

    public function test_a_closed_exit_cannot_be_approved_through_a_stale_copy(): void
    {
        $this->actingAs($this->user, 'api');
        $service = app(ExitManagementService::class);

        $exit = $this->exit($this->organization, $this->employee);
        $stale = EmployeeExit::findOrFail($exit->id);

        $service->close($exit);

        $this->assertRejected(fn () => $service->approve($stale, $this->user->id));
        $this->assertSame(EmployeeExit::STATUS_CLOSED, $exit->fresh()->status);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function exit(Organization $organization, Employee $employee): EmployeeExit
    {
        return EmployeeExit::forceCreate([
            'organization_id' => $organization->id,
            'employee_id' => $employee->id,
            'exit_type' => EmployeeExit::TYPE_RESIGNATION,
            'status' => EmployeeExit::STATUS_INITIATED,
        ]);
    }
}
