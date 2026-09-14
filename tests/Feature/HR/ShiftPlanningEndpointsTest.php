<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\ShiftPattern;
use App\Models\HR\ShiftRoster;
use App\Models\HR\ShiftRosterLine;
use App\Models\HR\ShiftSwapRequest;
use App\Services\HR\ShiftPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Shift patterns, rosters and swaps: listings, the references a roster,
 * assignment or swap may make, and swap decisions against the current row.
 */
class ShiftPlanningEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/hr/shifts';

    private Organization $otherOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.shifts.view', 'hr.shifts.manage', 'hr.shifts.request-swap']);

        $this->otherOrganization = Organization::factory()->create();
        $this->employee = $this->employee($this->organization);
    }

    public function test_patterns_are_listed_by_name_and_filtered_to_active(): void
    {
        $this->pattern($this->organization, 'Night');
        $this->pattern($this->organization, 'Day');
        $this->pattern($this->organization, 'Old', ['is_active' => false]);
        $this->pattern($this->otherOrganization, 'Theirs');

        $response = $this->apiGet("{$this->baseUrl}/patterns?active_only=1");

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Day', 'Night'], array_column($response->json('data'), 'name'));
    }

    public function test_a_pattern_is_created_active_in_the_organization(): void
    {
        $this->apiPost("{$this->baseUrl}/patterns", [
            'name' => 'Morning',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'days_of_week' => ['monday', 'tuesday'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_rosters_are_filtered_by_status_with_their_line_count(): void
    {
        $draft = $this->roster($this->organization);
        $this->line($draft, $this->employee, '2026-03-02');
        $published = $this->roster($this->organization, ShiftRoster::STATUS_PUBLISHED);

        $response = $this->apiGet("{$this->baseUrl}/rosters?status=draft");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$draft->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.lines_count', 1);
        $this->assertNotSame($draft->id, $published->id);
    }

    public function test_a_roster_cannot_name_another_organizations_branch_or_department(): void
    {
        $payload = ['name' => 'March', 'roster_period_start' => '2026-03-01', 'roster_period_end' => '2026-03-31'];

        $theirBranch = Branch::factory()->create(['organization_id' => $this->otherOrganization->id]);
        $this->apiPost("{$this->baseUrl}/rosters", $payload + ['branch_id' => $theirBranch->id])
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $theirDepartment = Department::create(['organization_id' => $this->otherOrganization->id, 'name' => 'Theirs', 'is_active' => true]);
        $this->apiPost("{$this->baseUrl}/rosters", $payload + ['department_id' => $theirDepartment->id])
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->apiPost("{$this->baseUrl}/rosters", $payload + ['branch_id' => $this->branch->id])->assertCreated();
        $this->assertSame(1, ShiftRoster::withoutGlobalScopes()->count());
    }

    public function test_a_shift_is_assigned_to_an_employee_of_the_organization(): void
    {
        $roster = $this->roster($this->organization);
        $pattern = $this->pattern($this->organization, 'Day');

        $this->apiPost("{$this->baseUrl}/rosters/{$roster->uuid}/assign", [
            'employee_id' => $this->employee->id,
            'shift_date' => '2026-03-02',
            'shift_pattern_id' => $pattern->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.shift_pattern.id', $pattern->id);
    }

    public function test_an_assignment_cannot_name_another_organizations_employee_or_pattern(): void
    {
        $roster = $this->roster($this->organization);
        $theirPattern = $this->pattern($this->otherOrganization, 'Theirs');

        $this->apiPost("{$this->baseUrl}/rosters/{$roster->uuid}/assign", [
            'employee_id' => $this->employee($this->otherOrganization)->id,
            'shift_date' => '2026-03-02',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost("{$this->baseUrl}/rosters/{$roster->uuid}/assign", [
            'employee_id' => $this->employee->id,
            'shift_date' => '2026-03-02',
            'shift_pattern_id' => $theirPattern->id,
        ])->assertStatus(422)->assertJsonValidationErrors('shift_pattern_id');

        $this->apiPost("{$this->baseUrl}/rosters/{$roster->uuid}/bulk-assign", [
            'employee_id' => $this->employee->id,
            'shift_pattern_id' => $theirPattern->id,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-03',
        ])->assertStatus(422)->assertJsonValidationErrors('shift_pattern_id');

        $this->assertSame(0, ShiftRosterLine::count());
    }

    public function test_bulk_assignment_fills_the_range_with_working_days_and_days_off(): void
    {
        $roster = $this->roster($this->organization);
        $pattern = $this->pattern($this->organization, 'Weekdays', ['days_of_week' => ['monday']]);

        $this->apiPost("{$this->baseUrl}/rosters/{$roster->uuid}/bulk-assign", [
            'employee_id' => $this->employee->id,
            'shift_pattern_id' => $pattern->id,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-03',
        ])->assertOk()->assertJsonPath('data.assigned_days', 2);

        $this->assertSame([false, true], ShiftRosterLine::orderBy('shift_date')->pluck('is_day_off')->all());
    }

    /**
     * The days of a range are written together: a failure on one day leaves
     * none of the range assigned.
     */
    public function test_bulk_assignment_writes_all_days_or_none(): void
    {
        $roster = $this->roster($this->organization);
        $pattern = $this->pattern($this->organization, 'Day');
        $written = 0;
        ShiftRosterLine::creating(function () use (&$written): void {
            if (++$written === 3) {
                throw new \RuntimeException('Disk full');
            }
        });

        try {
            app(ShiftPlanningService::class)->bulkAssignShift($roster, $this->employee, $pattern, '2026-03-02', '2026-03-06');
            $this->fail('The failing day did not stop the assignment.');
        } catch (\RuntimeException) {
            $this->assertSame(0, ShiftRosterLine::count());
        }
    }

    public function test_swaps_are_filtered_by_employee_on_either_side(): void
    {
        $colleague = $this->employee($this->organization);
        $third = $this->employee($this->organization);
        $asRequester = $this->swapRequest($this->employee, $colleague, ShiftSwapRequest::STATUS_PENDING);
        $asRequested = $this->swapRequest($colleague, $this->employee, ShiftSwapRequest::STATUS_PENDING);
        $this->swapRequest($colleague, $third, ShiftSwapRequest::STATUS_PENDING);

        $response = $this->apiGet("{$this->baseUrl}/swaps?employee_id={$this->employee->id}");

        $this->assertPaginatedResponse($response);
        $this->assertEqualsCanonicalizing([$asRequester->id, $asRequested->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_swap_is_requested_by_the_callers_employee_with_a_colleague(): void
    {
        $this->employee->update(['user_id' => $this->user->id]);
        $colleague = $this->employee($this->organization);

        $this->apiPost("{$this->baseUrl}/swaps", [
            'requested_employee_id' => $colleague->id,
            'requester_shift_date' => '2026-03-02',
            'requested_shift_date' => '2026-03-03',
        ])
            ->assertCreated()
            ->assertJsonPath('data.requester.id', $this->employee->id)
            ->assertJsonPath('data.requested_employee.id', $colleague->id);
    }

    public function test_a_swap_cannot_be_requested_with_another_organizations_employee(): void
    {
        $this->employee->update(['user_id' => $this->user->id]);

        $this->apiPost("{$this->baseUrl}/swaps", [
            'requested_employee_id' => $this->employee($this->otherOrganization)->id,
            'requester_shift_date' => '2026-03-02',
            'requested_shift_date' => '2026-03-03',
        ])->assertStatus(422)->assertJsonValidationErrors('requested_employee_id');

        $this->assertSame(0, ShiftSwapRequest::withoutGlobalScopes()->count());
    }

    public function test_an_account_with_no_employee_cannot_request_a_swap(): void
    {
        $this->apiPost("{$this->baseUrl}/swaps", [
            'requested_employee_id' => $this->employee->id,
            'requester_shift_date' => '2026-03-02',
            'requested_shift_date' => '2026-03-03',
        ])->assertNotFound();
    }

    /**
     * Approving exchanges the two shifts. A second approval made from a copy
     * read before the first must not exchange them back.
     */
    public function test_an_approved_swap_is_not_approved_again_from_a_stale_copy(): void
    {
        $colleague = $this->employee($this->organization);
        $roster = $this->roster($this->organization, ShiftRoster::STATUS_PUBLISHED);
        $day = $this->pattern($this->organization, 'Day');
        $night = $this->pattern($this->organization, 'Night');
        $mine = $this->line($roster, $this->employee, '2026-03-02', $day);
        $theirs = $this->line($roster, $colleague, '2026-03-03', $night);
        $swap = $this->swapRequest($this->employee, $colleague, ShiftSwapRequest::STATUS_ACCEPTED, $mine, $theirs);
        $stale = ShiftSwapRequest::find($swap->id);

        $this->apiPost("{$this->baseUrl}/swaps/{$swap->uuid}/approve")->assertOk();

        try {
            app(ShiftPlanningService::class)->approveSwap($stale);
            $this->fail('An approved swap was approved again.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($night->id, $mine->fresh()->shift_pattern_id);
            $this->assertSame($day->id, $theirs->fresh()->shift_pattern_id);
        }
    }

    public function test_a_swap_decided_meanwhile_is_not_rejected_from_a_stale_copy(): void
    {
        $swap = $this->swapRequest($this->employee, $this->employee($this->organization), ShiftSwapRequest::STATUS_PENDING);
        $stale = ShiftSwapRequest::find($swap->id);
        ShiftSwapRequest::whereKey($swap->id)->toBase()->update(['status' => ShiftSwapRequest::STATUS_ACCEPTED]);

        try {
            app(ShiftPlanningService::class)->rejectSwap($stale, 'No');
            $this->fail('An accepted swap was rejected as if still pending.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(ShiftSwapRequest::STATUS_ACCEPTED, $swap->fresh()->status);
        }
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function pattern(Organization $organization, string $name, array $overrides = []): ShiftPattern
    {
        return ShiftPattern::create(array_merge([
            'organization_id' => $organization->id,
            'name' => $name,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'days_of_week' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_active' => true,
        ], $overrides));
    }

    private function roster(Organization $organization, string $status = ShiftRoster::STATUS_DRAFT): ShiftRoster
    {
        return ShiftRoster::create([
            'organization_id' => $organization->id,
            'name' => 'March',
            'roster_period_start' => '2026-03-01',
            'roster_period_end' => '2026-03-31',
            'status' => $status,
        ]);
    }

    private function line(ShiftRoster $roster, Employee $employee, string $date, ?ShiftPattern $pattern = null): ShiftRosterLine
    {
        return ShiftRosterLine::create([
            'roster_id' => $roster->id,
            'employee_id' => $employee->id,
            'shift_date' => $date,
            'shift_pattern_id' => $pattern?->id,
        ]);
    }

    private function swapRequest(
        Employee $requester,
        Employee $requested,
        string $status,
        ?ShiftRosterLine $requesterLine = null,
        ?ShiftRosterLine $requestedLine = null,
    ): ShiftSwapRequest {
        return ShiftSwapRequest::create([
            'organization_id' => $requester->organization_id,
            'requester_id' => $requester->id,
            'requested_employee_id' => $requested->id,
            'requester_roster_line_id' => $requesterLine?->id,
            'requested_roster_line_id' => $requestedLine?->id,
            'requester_shift_date' => '2026-03-02',
            'requested_shift_date' => '2026-03-03',
            'status' => $status,
        ]);
    }
}
