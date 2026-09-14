<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Leave types, request listing filters and balances, inside the caller's
 * organization only.
 */
class LeaveEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/leave';

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.leave.view', 'hr.leave.create', 'hr.leave.approve', 'hr.leave.manage']);

        $this->employee = $this->employee($this->organization);
    }

    public function test_leave_types_are_the_active_ones_in_their_order(): void
    {
        $this->leaveType(['name' => 'Beta', 'sort_order' => 2]);
        $this->leaveType(['name' => 'Zeta', 'sort_order' => 1]);
        $this->leaveType(['name' => 'Alpha', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->apiGet("{$this->baseUrl}/types");

        $response->assertOk();
        $this->assertSame(['Zeta', 'Beta'], array_column($response->json('data'), 'name'));
    }

    public function test_requests_are_filtered_by_status_and_type_and_sorted(): void
    {
        $annual = $this->leaveType();
        $sick = $this->leaveType();

        $later = $this->request($annual, '2026-06-10', LeaveRequest::STATUS_PENDING);
        $earlier = $this->request($annual, '2026-05-10', LeaveRequest::STATUS_PENDING);
        $this->request($annual, '2026-04-10', LeaveRequest::STATUS_APPROVED);
        $this->request($sick, '2026-03-10', LeaveRequest::STATUS_PENDING);

        $response = $this->apiGet("{$this->baseUrl}/requests?status=pending&leave_type_id={$annual->id}&sort_by=from_date&sort_order=asc");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$earlier->id, $later->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_request_cannot_name_another_organizations_employee(): void
    {
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/requests", [
            'employee_id' => $theirs->id,
            'leave_type_id' => $this->leaveType()->id,
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(0, LeaveRequest::withoutGlobalScopes()->count());
    }

    public function test_balances_are_returned_for_an_employee_of_the_organization_only(): void
    {
        $type = $this->leaveType();
        LeaveBalance::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'year' => 2026,
            'opening_balance' => 21,
        ]);

        $this->apiGet("{$this->baseUrl}/balances?employee_id={$this->employee->id}&year=2026")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.leave_type.id', $type->id);

        $theirs = $this->employee(Organization::factory()->create());

        $this->apiGet("{$this->baseUrl}/balances?employee_id={$theirs->id}")->assertStatus(422);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function leaveType(array $overrides = []): LeaveType
    {
        return LeaveType::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ], $overrides));
    }

    private function request(LeaveType $type, string $fromDate, string $status): LeaveRequest
    {
        return LeaveRequest::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => $fromDate,
            'to_date' => $fromDate,
            'total_days' => 1,
            'reason' => 'Personal',
            'status' => $status,
        ]);
    }
}
