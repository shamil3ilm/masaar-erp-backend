<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Attendance listing, check-in and check-out, manual marking and summaries,
 * for employees of the caller's organization only.
 */
class AttendanceEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/attendance';

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.attendance.view', 'hr.attendance.manage']);

        $this->employee = $this->employee($this->organization);
    }

    public function test_attendance_is_filtered_by_employee_status_range_and_lateness_latest_first(): void
    {
        $earlier = $this->attendance($this->employee, '2026-03-02', Attendance::STATUS_PRESENT, 10);
        $later = $this->attendance($this->employee, '2026-03-05', Attendance::STATUS_PRESENT, 5);
        $this->attendance($this->employee, '2026-03-04', Attendance::STATUS_PRESENT, 0);
        $this->attendance($this->employee, '2026-03-03', Attendance::STATUS_ABSENT, 7);
        $this->attendance($this->employee, '2026-04-01', Attendance::STATUS_PRESENT, 9);
        $this->attendance($this->employee($this->organization), '2026-03-06', Attendance::STATUS_PRESENT, 10);

        $response = $this->apiGet(
            "{$this->baseUrl}?employee_id={$this->employee->id}&status=present&start_date=2026-03-01&end_date=2026-03-31&late=true"
        );

        $this->assertPaginatedResponse($response);
        $this->assertSame([$later->id, $earlier->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.employee.id', $this->employee->id);
    }

    public function test_a_check_in_and_check_out_are_recorded_once_a_day(): void
    {
        $this->apiPost("{$this->baseUrl}/check-in", [
            'employee_id' => $this->employee->id,
            'check_in_time' => '2026-03-10 08:00:00',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.attendance_date', '2026-03-10');

        $this->apiPost("{$this->baseUrl}/check-in", [
            'employee_id' => $this->employee->id,
            'check_in_time' => '2026-03-10 09:00:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $response = $this->apiPost("{$this->baseUrl}/check-out", [
            'employee_id' => $this->employee->id,
            'check_out_time' => '2026-03-10 17:00:00',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Check-out recorded successfully.');
        $this->assertNotNull($response->json('data.check_out'));
    }

    public function test_attendance_is_marked_manually(): void
    {
        $this->apiPost("{$this->baseUrl}/mark", [
            'employee_id' => $this->employee->id,
            'date' => '2026-03-11',
            'status' => Attendance::STATUS_ABSENT,
            'notes' => 'No show',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Attendance::STATUS_ABSENT)
            ->assertJsonPath('message', 'Attendance marked successfully.');
    }

    public function test_attendance_is_not_recorded_or_summarised_for_another_organizations_employee(): void
    {
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/mark", [
            'employee_id' => $theirs->id,
            'date' => '2026-03-11',
            'status' => Attendance::STATUS_PRESENT,
        ])->assertStatus(422);

        $this->apiPost("{$this->baseUrl}/check-in", ['employee_id' => $theirs->id])->assertStatus(422);
        $this->apiGet("{$this->baseUrl}/employee-summary?employee_id={$theirs->id}")->assertStatus(422);

        $this->assertSame(0, Attendance::withoutGlobalScopes()->where('employee_id', $theirs->id)->count());
    }

    public function test_an_employees_summary_is_returned_for_a_range(): void
    {
        $this->attendance($this->employee, '2026-03-02', Attendance::STATUS_PRESENT, 0);

        $this->apiGet("{$this->baseUrl}/employee-summary?employee_id={$this->employee->id}&start_date=2026-03-01&end_date=2026-03-31")
            ->assertOk();
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function attendance(Employee $employee, string $date, string $status, int $lateMinutes): Attendance
    {
        return Attendance::factory()->create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ]);
    }
}
