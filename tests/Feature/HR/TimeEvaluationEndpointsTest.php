<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\TimeSheet;
use App\Models\HR\TimeSheetEntry;
use App\Models\HR\TimeWageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Time sheets and wage types: listing, creation and the references a sheet
 * or entry may make, all inside the caller's organization.
 */
class TimeEvaluationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Employee $employee;

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.attendance.view', 'hr.attendance.manage', 'hr.payroll.process']);

        $this->employee = $this->employee($this->organization);
        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_time_sheets_are_filtered_by_employee_and_status_and_sorted(): void
    {
        $other = $this->employee($this->organization);
        $march = $this->sheet($this->employee, '2026-03-01', TimeSheet::STATUS_DRAFT);
        $february = $this->sheet($this->employee, '2026-02-01', TimeSheet::STATUS_DRAFT);
        $this->sheet($this->employee, '2026-01-01', TimeSheet::STATUS_SUBMITTED);
        $this->sheet($other, '2026-03-01', TimeSheet::STATUS_DRAFT);

        $response = $this->apiGet("/hr/time-sheets?employee_id={$this->employee->id}&status=draft&sort_by=period_start&sort_order=asc");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$february->id, $march->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.employee.id', $this->employee->id);
    }

    public function test_a_time_sheet_is_created_for_an_employee_of_the_organization(): void
    {
        $this->apiPost('/hr/time-sheets', [
            'employee_id' => $this->employee->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ])->assertCreated()->assertJsonPath('data.employee.id', $this->employee->id);
    }

    public function test_a_time_sheet_cannot_name_another_organizations_employee(): void
    {
        $this->apiPost('/hr/time-sheets', [
            'employee_id' => $this->employee($this->otherOrganization)->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->assertSame(0, TimeSheet::withoutGlobalScopes()->count());
    }

    public function test_an_entry_cannot_name_another_organizations_wage_type_or_cost_center(): void
    {
        $sheet = $this->sheet($this->employee, '2026-03-01', TimeSheet::STATUS_DRAFT);
        $theirWageType = $this->wageType($this->otherOrganization, 'OT1');
        $theirCostCenter = DB::table('cost_centers')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $this->otherOrganization->id,
            'code' => 'CC-THEIRS',
            'name' => 'Theirs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entry = ['entry_date' => '2026-03-02', 'hours' => 8];

        $this->apiPost("/hr/time-sheets/{$sheet->uuid}/entries", $entry + ['wage_type_id' => $theirWageType->id])
            ->assertStatus(422)->assertJsonValidationErrors('wage_type_id');

        $this->apiPost("/hr/time-sheets/{$sheet->uuid}/entries", $entry + ['cost_center_id' => $theirCostCenter])
            ->assertStatus(422)->assertJsonValidationErrors('cost_center_id');

        $this->assertSame(0, TimeSheetEntry::count());
    }

    public function test_wage_types_are_the_active_ones_by_code(): void
    {
        $this->wageType($this->organization, 'OT2');
        $this->wageType($this->organization, 'OT1');
        $this->wageType($this->organization, 'OT0', ['is_active' => false]);
        $this->wageType($this->otherOrganization, 'OTX');

        $response = $this->apiGet('/hr/wage-types')->assertOk();

        $this->assertSame(['OT1', 'OT2'], array_column($response->json('data'), 'code'));
    }

    public function test_a_wage_type_code_is_unique_within_the_organization(): void
    {
        $this->wageType($this->organization, 'OT1');
        $this->wageType($this->otherOrganization, 'OT9');

        $payload = ['name' => 'Overtime', 'wage_category' => 'overtime', 'rate_multiplier' => 1.5];

        $this->apiPost('/hr/wage-types', $payload + ['code' => 'OT1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_CODE');

        $this->apiPost('/hr/wage-types', $payload + ['code' => 'OT9'])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function sheet(Employee $employee, string $periodStart, string $status): TimeSheet
    {
        $start = \Illuminate\Support\Carbon::parse($periodStart);

        return TimeSheet::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function wageType(Organization $organization, string $code, array $overrides = []): TimeWageType
    {
        return TimeWageType::create(array_merge([
            'organization_id' => $organization->id,
            'code' => $code,
            'name' => "Wage {$code}",
            'wage_category' => 'overtime',
            'rate_multiplier' => 1.5,
            'is_active' => true,
        ], $overrides));
    }
}
