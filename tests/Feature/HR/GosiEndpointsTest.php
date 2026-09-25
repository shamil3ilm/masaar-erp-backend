<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\GosiContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * GOSI contribution listing, calculation and period submission, inside the
 * caller's organization only.
 */
class GosiEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/gosi';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.gosi.view', 'hr.gosi.manage']);
    }

    public function test_contributions_are_filtered_by_year_and_status_latest_period_first(): void
    {
        $february = $this->contribution(2026, 2, GosiContribution::STATUS_DRAFT);
        $march = $this->contribution(2026, 3, GosiContribution::STATUS_DRAFT);
        $this->contribution(2026, 3, GosiContribution::STATUS_SUBMITTED);
        $this->contribution(2025, 3, GosiContribution::STATUS_DRAFT);
        $this->contribution(2026, 3, GosiContribution::STATUS_DRAFT, Organization::factory()->create());

        $response = $this->apiGet("{$this->baseUrl}?year=2026&status=draft");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$march->id, $february->id], array_column($response->json('data'), 'id'));
        $this->assertNotNull($response->json('data.0.employee.id'));
    }

    public function test_a_contribution_is_not_calculated_for_another_organizations_employee(): void
    {
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/calculate", [
            'employee_id' => $theirs->id,
            'year' => 2026,
            'month' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_id');

        $this->assertSame(0, GosiContribution::withoutGlobalScopes()->count());
    }

    public function test_the_draft_contributions_of_a_period_are_submitted(): void
    {
        $march = [$this->contribution(2026, 3, GosiContribution::STATUS_DRAFT), $this->contribution(2026, 3, GosiContribution::STATUS_DRAFT)];
        $april = $this->contribution(2026, 4, GosiContribution::STATUS_DRAFT);
        $theirs = $this->contribution(2026, 3, GosiContribution::STATUS_DRAFT, Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/submit-period", ['year' => 2026, 'month' => 3])
            ->assertOk()
            ->assertJsonPath('message', 'GOSI contributions submitted for the period.');

        $this->assertSame(GosiContribution::STATUS_SUBMITTED, $march[0]->fresh()->status);
        $this->assertSame(GosiContribution::STATUS_SUBMITTED, $march[1]->fresh()->status);
        $this->assertSame(GosiContribution::STATUS_DRAFT, $april->fresh()->status);
        $this->assertSame(GosiContribution::STATUS_DRAFT, GosiContribution::withoutGlobalScopes()->find($theirs->id)->status);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function contribution(int $year, int $month, string $status, ?Organization $organization = null): GosiContribution
    {
        $organization ??= $this->organization;

        return GosiContribution::forceCreate([
            'organization_id' => $organization->id,
            'employee_id' => $this->employee($organization)->id,
            'period_year' => $year,
            'period_month' => $month,
            'status' => $status,
        ]);
    }
}
