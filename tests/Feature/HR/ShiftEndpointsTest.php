<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeShiftAssignment;
use App\Models\HR\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Shift definitions and their assignment to employees, inside the caller's
 * organization only.
 */
class ShiftEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/shifts';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.shifts.view', 'hr.shifts.manage']);
    }

    public function test_shifts_are_listed_by_name_and_filtered_to_active_ones(): void
    {
        $this->shift(['name' => 'Night']);
        $this->shift(['name' => 'Day']);
        $this->shift(['name' => 'Evening', 'is_active' => false]);
        $this->shift(['name' => 'Aaa'], Organization::factory()->create());

        $response = $this->apiGet("{$this->baseUrl}?active_only=1");

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Day', 'Night'], array_column($response->json('data'), 'name'));
    }

    public function test_a_shift_is_created_with_its_code(): void
    {
        $this->apiPost($this->baseUrl, [
            'name' => 'Morning',
            'shift_code' => 'MOR',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 30,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Morning')
            ->assertJsonPath('data.code', 'MOR')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_a_shift_is_renamed_and_its_code_changed(): void
    {
        $shift = $this->shift();

        $this->apiPut("{$this->baseUrl}/{$shift->id}", ['name' => 'Early', 'shift_code' => 'EAR'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Early')
            ->assertJsonPath('data.code', 'EAR');
    }

    public function test_another_organizations_shift_is_not_found(): void
    {
        $theirs = $this->shift([], Organization::factory()->create());

        $this->apiPut("{$this->baseUrl}/{$theirs->id}", ['name' => 'Mine'])->assertNotFound();
        $this->apiDelete("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
    }

    public function test_a_shift_currently_assigned_is_not_deleted(): void
    {
        $assigned = $this->shift();
        $free = $this->shift();

        EmployeeShiftAssignment::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee($this->organization)->id,
            'shift_id' => $assigned->id,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $this->apiDelete("{$this->baseUrl}/{$assigned->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SHIFT_IN_USE');

        $this->apiDelete("{$this->baseUrl}/{$free->id}")->assertNoContent();
        $this->assertDatabaseMissing('shifts', ['id' => $free->id]);
    }

    public function test_a_shift_is_assigned_to_an_employee_of_the_organization(): void
    {
        $employee = $this->employee($this->organization);
        $shift = $this->shift();

        $this->apiPost("{$this->baseUrl}/assign", [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_from' => '2026-01-01',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.shift.id', $shift->id);
    }

    public function test_another_organizations_shift_or_employee_is_not_assigned(): void
    {
        $other = Organization::factory()->create();
        $employee = $this->employee($this->organization);

        $this->apiPost("{$this->baseUrl}/assign", [
            'employee_id' => $employee->id,
            'shift_id' => $this->shift([], $other)->id,
            'effective_from' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('shift_id');

        $this->apiPost("{$this->baseUrl}/assign", [
            'employee_id' => $this->employee($other)->id,
            'shift_id' => $this->shift()->id,
            'effective_from' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_id');

        $this->assertSame(0, EmployeeShiftAssignment::withoutGlobalScopes()->count());
    }

    private function shift(array $overrides = [], ?Organization $organization = null): Shift
    {
        return Shift::create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id,
            'name' => 'Standard',
            'code' => 'STD',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ], $overrides));
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }
}
