<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\OvertimePolicy;
use App\Models\HR\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Overtime requests stay inside the caller's organization.
 *
 * The overtime table has no organization column: a request belongs to the
 * organization of its employee. Listing, opening, approving and summarising
 * overtime must never reach another organization's requests.
 */
class OvertimeEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/overtime';

    private Employee $employee;

    private OvertimePolicy $policy;

    private Organization $otherOrganization;

    private Employee $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.attendance.view', 'hr.attendance.manage']);

        $this->employee = $this->employee($this->organization);
        $this->policy = $this->policy($this->organization);
        $this->otherOrganization = Organization::factory()->create();
        $this->stranger = $this->employee($this->otherOrganization);
    }

    public function test_index_lists_the_organizations_requests_filtered_by_status(): void
    {
        $pending = $this->overtime($this->employee, $this->policy, '2026-03-02', OvertimeRequest::STATUS_PENDING);
        $this->overtime($this->employee, $this->policy, '2026-03-03', OvertimeRequest::STATUS_APPROVED);
        $this->overtime($this->stranger, $this->policy($this->otherOrganization), '2026-03-04', OvertimeRequest::STATUS_PENDING);

        $response = $this->apiGet("{$this->baseUrl}?status=pending");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$pending->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.employee.id', $this->employee->id);
    }

    public function test_index_is_ordered_by_date_and_filtered_by_range(): void
    {
        $early = $this->overtime($this->employee, $this->policy, '2026-03-02', OvertimeRequest::STATUS_PENDING);
        $late = $this->overtime($this->employee, $this->policy, '2026-03-20', OvertimeRequest::STATUS_PENDING);
        $this->overtime($this->employee, $this->policy, '2026-04-20', OvertimeRequest::STATUS_PENDING);

        $response = $this->apiGet("{$this->baseUrl}?from_date=2026-03-01&to_date=2026-03-31&employee_id={$this->employee->id}");

        $this->assertSame([$late->id, $early->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_request_is_out_of_reach(): void
    {
        $theirs = $this->overtime($this->stranger, $this->policy($this->otherOrganization), '2026-03-04', OvertimeRequest::STATUS_PENDING);

        $this->apiGet("{$this->baseUrl}/{$theirs->uuid}")->assertNotFound();
        $this->apiPut("{$this->baseUrl}/{$theirs->uuid}", ['ot_hours' => 9])->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->uuid}/approve")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->uuid}/reject", ['reason' => 'No'])->assertNotFound();
        $this->deleteJson("/api/v1{$this->baseUrl}/{$theirs->uuid}", [], $this->authHeaders())->assertNotFound();

        $theirs = OvertimeRequest::withoutGlobalScopes()->find($theirs->id);
        $this->assertSame(OvertimeRequest::STATUS_PENDING, $theirs->status);
        $this->assertSame('2.00', $theirs->ot_hours);
    }

    public function test_a_request_is_created_for_an_employee_and_policy_of_the_organization(): void
    {
        $this->apiPost($this->baseUrl, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.status', OvertimeRequest::STATUS_PENDING);
    }

    public function test_a_request_cannot_name_another_organizations_employee_or_policy(): void
    {
        $this->apiPost($this->baseUrl, $this->payload(['employee_id' => $this->stranger->id]))
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost($this->baseUrl, $this->payload(['policy_id' => $this->policy($this->otherOrganization)->id]))
            ->assertStatus(422)->assertJsonValidationErrors('policy_id');

        $this->assertSame(0, OvertimeRequest::withoutGlobalScopes()->count());
    }

    public function test_monthly_summary_totals_approved_and_paid_overtime(): void
    {
        $this->overtime($this->employee, $this->policy, '2026-03-02', OvertimeRequest::STATUS_APPROVED, 50);
        $this->overtime($this->employee, $this->policy, '2026-03-03', OvertimeRequest::STATUS_PAID, 25);
        $this->overtime($this->employee, $this->policy, '2026-03-04', OvertimeRequest::STATUS_PENDING, 99);

        $this->apiGet("{$this->baseUrl}/summary/{$this->employee->id}/2026/3")
            ->assertOk()
            ->assertJsonPath('data.total_hours', 4)
            ->assertJsonPath('data.total_amount', 75)
            ->assertJsonPath('data.request_count', 3)
            ->assertJsonPath('data.approved_count', 1)
            ->assertJsonPath('data.paid_count', 1);
    }

    public function test_monthly_summary_of_another_organizations_employee_is_refused(): void
    {
        $this->overtime($this->stranger, $this->policy($this->otherOrganization), '2026-03-02', OvertimeRequest::STATUS_APPROVED, 500);

        $this->apiGet("{$this->baseUrl}/summary/{$this->stranger->id}/2026/3")->assertNotFound();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'policy_id' => $this->policy->id,
            'ot_date' => '2026-03-10',
            'ot_start' => '18:00',
            'ot_end' => '20:00',
            'ot_hours' => 2,
        ], $overrides);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function policy(Organization $organization): OvertimePolicy
    {
        return OvertimePolicy::create([
            'organization_id' => $organization->id,
            'policy_name' => 'Standard',
            'daily_standard_hours' => 8,
            'weekly_standard_hours' => 40,
            'ot_rate_weekday' => 1.25,
            'ot_rate_weekend' => 1.5,
            'ot_rate_holiday' => 2,
            'max_daily_ot_hours' => 4,
            'max_weekly_ot_hours' => 20,
            'requires_approval' => true,
            'is_active' => true,
        ]);
    }

    private function overtime(Employee $employee, OvertimePolicy $policy, string $date, string $status, float $amount = 0): OvertimeRequest
    {
        return OvertimeRequest::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'employee_id' => $employee->id,
            'policy_id' => $policy->id,
            'ot_date' => $date,
            'ot_start' => '18:00',
            'ot_end' => '20:00',
            'ot_hours' => 2,
            'day_type' => OvertimeRequest::DAY_TYPE_WEEKDAY,
            'ot_rate' => 1.25,
            'ot_amount' => $amount,
            'status' => $status,
            'created_by' => User::factory()->create(['organization_id' => $employee->organization_id])->id,
        ]);
    }
}
