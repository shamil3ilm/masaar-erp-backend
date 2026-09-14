<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\SocialInsuranceRecord;
use App\Models\HR\SocialInsuranceScheme;
use App\Models\HR\SocialInsuranceSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Social insurance schemes, enrolment and submissions, inside the caller's
 * organization only.
 */
class SocialInsuranceEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/social-insurance';

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['hr.social-insurance.view', 'hr.social-insurance.manage']);

        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_schemes_are_filtered_by_country_and_activity(): void
    {
        $wanted = $this->scheme($this->organization, 'OM');
        $this->scheme($this->organization, 'OM', ['is_active' => false]);
        $this->scheme($this->organization, 'KW');
        $this->scheme($this->otherOrganization, 'OM');

        $response = $this->apiGet("{$this->baseUrl}/schemes?country_code=OM&active_only=1");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_scheme_is_created_active_in_the_organization(): void
    {
        $this->apiPost("{$this->baseUrl}/schemes", [
            'name' => 'GOSI',
            'country_code' => 'SA',
            'employee_contribution_pct' => 9.75,
            'employer_contribution_pct' => 11.75,
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_an_employee_of_the_organization_is_enrolled(): void
    {
        $scheme = $this->scheme($this->organization, 'SA');
        $employee = $this->employee($this->organization);

        $this->apiPost("{$this->baseUrl}/schemes/{$scheme->uuid}/enroll", [
            'employee_id' => $employee->id,
            'enrollment_date' => '2026-01-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.scheme.id', $scheme->id);
    }

    public function test_another_organizations_employee_cannot_be_enrolled(): void
    {
        $scheme = $this->scheme($this->organization, 'SA');

        $this->apiPost("{$this->baseUrl}/schemes/{$scheme->uuid}/enroll", [
            'employee_id' => $this->employee($this->otherOrganization)->id,
            'enrollment_date' => '2026-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->assertSame(0, SocialInsuranceRecord::withoutGlobalScopes()->count());
    }

    public function test_records_of_a_scheme_are_filtered_by_status(): void
    {
        $scheme = $this->scheme($this->organization, 'SA');
        $active = $this->record($scheme, SocialInsuranceRecord::STATUS_ACTIVE);
        $this->record($scheme, SocialInsuranceRecord::STATUS_SUSPENDED);
        $this->record($this->scheme($this->organization, 'SA'), SocialInsuranceRecord::STATUS_ACTIVE);

        $response = $this->apiGet("{$this->baseUrl}/schemes/{$scheme->uuid}/records?status=active");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$active->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.employee.id', $active->employee_id);
    }

    public function test_submissions_are_filtered_and_latest_period_first(): void
    {
        $scheme = $this->scheme($this->organization, 'SA');
        $march = $this->submission($scheme, 2026, 3, SocialInsuranceSubmission::STATUS_DRAFT);
        $april = $this->submission($scheme, 2026, 4, SocialInsuranceSubmission::STATUS_DRAFT);
        $this->submission($scheme, 2025, 12, SocialInsuranceSubmission::STATUS_DRAFT);
        $this->submission($scheme, 2026, 5, SocialInsuranceSubmission::STATUS_SUBMITTED);
        $this->submission($this->scheme($this->organization, 'SA'), 2026, 6, SocialInsuranceSubmission::STATUS_DRAFT);

        $response = $this->apiGet("{$this->baseUrl}/submissions?scheme_id={$scheme->id}&status=draft&year=2026");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$april->id, $march->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.scheme.id', $scheme->id);
    }

    public function test_another_organizations_submission_is_not_listed(): void
    {
        $this->submission($this->scheme($this->otherOrganization, 'SA'), 2026, 3, SocialInsuranceSubmission::STATUS_DRAFT);

        $this->apiGet("{$this->baseUrl}/submissions")->assertOk()->assertJsonCount(0, 'data');
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function scheme(Organization $organization, string $countryCode, array $overrides = []): SocialInsuranceScheme
    {
        return SocialInsuranceScheme::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'country_code' => $countryCode,
            'is_active' => true,
        ], $overrides));
    }

    private function record(SocialInsuranceScheme $scheme, string $status): SocialInsuranceRecord
    {
        return SocialInsuranceRecord::factory()->create([
            'organization_id' => $scheme->organization_id,
            'employee_id' => $this->employee($this->organization)->id,
            'scheme_id' => $scheme->id,
            'status' => $status,
            'enrollment_date' => '2024-01-01',
        ]);
    }

    private function submission(SocialInsuranceScheme $scheme, int $year, int $month, string $status): SocialInsuranceSubmission
    {
        return SocialInsuranceSubmission::factory()->create([
            'organization_id' => $scheme->organization_id,
            'scheme_id' => $scheme->id,
            'period_year' => $year,
            'period_month' => $month,
            'status' => $status,
        ]);
    }
}
