<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeTransfer;
use App\Services\HR\EmployeeTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Employee transfers: initiated pending approval, approved or rejected, then
 * applied to the employee record, inside the organization that owns them.
 */
class EmployeeTransferTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/employee-transfers';

    private Employee $employee;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.lifecycle.view', 'hr.lifecycle.manage']);

        $this->employee = $this->employee($this->organization);
    }

    public function test_a_transfer_is_initiated_pending_approval(): void
    {
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPost($this->baseUrl, [
            'employee_id' => $this->employee->id,
            'effective_date' => '2026-06-01',
            'transfer_type' => EmployeeTransfer::TYPE_DEPARTMENT,
            'to_department_id' => $department->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', EmployeeTransfer::STATUS_PENDING_APPROVAL)
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.to_department.id', $department->id);
    }

    public function test_a_transfer_cannot_name_another_organizations_employee(): void
    {
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost($this->baseUrl, [
            'employee_id' => $theirs->id,
            'effective_date' => '2026-06-01',
            'transfer_type' => EmployeeTransfer::TYPE_LATERAL,
        ])->assertStatus(422);

        $this->assertSame(0, EmployeeTransfer::withoutGlobalScopes()->count());
    }

    public function test_an_approved_transfer_is_applied_to_the_employee(): void
    {
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);
        $transfer = $this->transfer(EmployeeTransfer::STATUS_APPROVED, ['to_department_id' => $department->id]);

        $this->apiPost("{$this->baseUrl}/{$transfer->id}/apply")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeTransfer::STATUS_APPLIED);

        $this->assertSame($department->id, $this->employee->fresh()->department_id);
    }

    public function test_a_rejected_transfer_cannot_be_approved_through_a_stale_copy(): void
    {
        $this->actingAs($this->user, 'api');
        $service = app(EmployeeTransferService::class);

        $transfer = $this->transfer(EmployeeTransfer::STATUS_PENDING_APPROVAL);
        $stale = EmployeeTransfer::findOrFail($transfer->id);

        $service->reject($transfer, $this->user, 'Not this quarter');

        $this->assertRejected(fn () => $service->approve($stale, $this->user));
        $this->assertSame(EmployeeTransfer::STATUS_REJECTED, $transfer->fresh()->status);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function transfer(string $status, array $overrides = []): EmployeeTransfer
    {
        return EmployeeTransfer::forceCreate(array_merge([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'transfer_number' => 'TRF-TEST-'.(++$this->sequence),
            'effective_date' => '2026-06-01',
            'transfer_type' => EmployeeTransfer::TYPE_DEPARTMENT,
            'status' => $status,
        ], $overrides));
    }
}
