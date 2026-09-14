<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\BenefitType;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeBenefit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Benefit types and employee benefits, inside the caller's organization only.
 */
class BenefitsEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // hr-compliance.php declares its own "hr/" prefix inside the hr route
    // group, so these endpoints are served under /hr/hr/benefits.
    private string $baseUrl = '/hr/hr/benefits';

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.benefits.view', 'hr.benefits.manage']);

        $this->employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_benefit_types_are_filtered_and_ordered_by_category_then_name(): void
    {
        $this->type(['name' => 'Transport', 'category' => 'allowance']);
        $this->type(['name' => 'Housing', 'category' => 'allowance']);
        $this->type(['name' => 'Retired', 'category' => 'allowance', 'is_active' => false]);
        $this->type(['name' => 'Medical', 'category' => 'insurance']);
        $this->type(['name' => 'Theirs', 'category' => 'allowance'], Organization::factory()->create());

        $response = $this->apiGet("{$this->baseUrl}/types?category=allowance&active_only=1");

        $this->assertPaginatedResponse($response);
        $this->assertSame(['Housing', 'Transport'], array_column($response->json('data'), 'name'));
    }

    public function test_a_benefit_type_is_created_active_and_updated(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/types", [
            'name' => 'Education',
            'category' => 'allowance',
            'calculation_type' => 'fixed',
            'default_amount' => 500,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Benefit type created successfully.');

        $this->apiPut("{$this->baseUrl}/types/{$response->json('data.id')}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Benefit type updated successfully.');
    }

    public function test_an_employee_is_not_enrolled_in_another_organizations_benefit_type(): void
    {
        $theirs = $this->type([], Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/employees/{$this->employee->id}/enroll", [
            'benefit_type_id' => $theirs->id,
            'start_date' => '2026-01-01',
        ])->assertNotFound();

        $this->assertSame(0, EmployeeBenefit::withoutGlobalScopes()->count());
    }

    public function test_an_employees_benefits_are_filtered_by_status_latest_start_first(): void
    {
        $housing = $this->type(['name' => 'Housing']);
        $older = $this->benefit($housing, '2025-01-01', EmployeeBenefit::STATUS_ACTIVE);
        $newer = $this->benefit($this->type(['name' => 'Transport']), '2026-01-01', EmployeeBenefit::STATUS_ACTIVE);
        $this->benefit($this->type(['name' => 'Medical']), '2026-02-01', EmployeeBenefit::STATUS_TERMINATED);

        $response = $this->apiGet("{$this->baseUrl}/employees/{$this->employee->id}?status=active");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    private function type(array $overrides = [], ?Organization $organization = null): BenefitType
    {
        return BenefitType::create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id,
            'name' => 'Allowance',
            'category' => 'allowance',
            'calculation_type' => 'fixed',
            'default_amount' => 100,
            'is_active' => true,
        ], $overrides));
    }

    private function benefit(BenefitType $type, string $startDate, string $status): EmployeeBenefit
    {
        return EmployeeBenefit::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'benefit_type_id' => $type->id,
            'amount' => 100,
            'start_date' => $startDate,
            'status' => $status,
        ]);
    }
}
