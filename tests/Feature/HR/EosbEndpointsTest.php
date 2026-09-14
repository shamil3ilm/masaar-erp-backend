<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\EosbPolicy;
use App\Models\HR\EosbProvision;
use App\Models\HR\EosbSettlement;
use App\Services\HR\EosbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * End-of-service policies, provisions and settlements: what the endpoints
 * list and change, inside the caller's organization only.
 */
class EosbEndpointsTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    // hr-compliance.php declares its own "hr/" prefix inside the hr route
    // group, so these endpoints are served under /hr/hr/eosb.
    private string $baseUrl = '/hr/hr/eosb';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.eosb.view', 'hr.eosb.manage']);
    }

    public function test_policies_are_filtered_by_country_and_activity_within_the_organization(): void
    {
        $wanted = $this->policy(['country_code' => 'SA']);
        $this->policy(['country_code' => 'SA', 'is_active' => false]);
        $this->policy(['country_code' => 'AE']);
        $this->policy(['country_code' => 'SA'], Organization::factory()->create());

        $response = $this->apiGet("{$this->baseUrl}/policies?country_code=SA&active_only=1");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_created_policy_is_active_and_belongs_to_the_organization(): void
    {
        $this->apiPost("{$this->baseUrl}/policies", [
            'country_code' => 'SA',
            'calculation_method' => 'saudi',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'EOSB policy created successfully.');
    }

    public function test_a_policy_is_updated(): void
    {
        $policy = $this->policy();

        $this->apiPut("{$this->baseUrl}/policies/{$policy->id}", ['is_active' => false, 'notes' => 'Retired'])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.notes', 'Retired');
    }

    public function test_an_employees_provisions_are_listed_latest_period_first(): void
    {
        $employee = $this->employee();
        $policy = $this->policy();
        $older = $this->provision($employee, $policy, 2025, 12);
        $newer = $this->provision($employee, $policy, 2026, 2);
        $this->provision($employee, $policy, 2024, 6);

        $response = $this->apiGet("{$this->baseUrl}/employees/{$employee->id}/provisions?year=2026");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$newer->id], array_column($response->json('data'), 'id'));

        $all = $this->apiGet("{$this->baseUrl}/employees/{$employee->id}/provisions");
        $this->assertSame($newer->id, $all->json('data.0.id'));
        $this->assertSame($older->id, $all->json('data.1.id'));
    }

    public function test_settlements_are_filtered_by_status_with_their_employee(): void
    {
        $employee = $this->employee();
        $wanted = $this->settlement($employee, EosbSettlement::STATUS_DRAFT);
        $this->settlement($employee, EosbSettlement::STATUS_APPROVED);

        $response = $this->apiGet("{$this->baseUrl}/settlements?status=draft&employee_id={$employee->id}");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.employee.id', $employee->id);
    }

    public function test_a_draft_settlement_is_approved_once(): void
    {
        $settlement = $this->settlement($this->employee(), EosbSettlement::STATUS_DRAFT);

        $this->apiPost("{$this->baseUrl}/settlements/{$settlement->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', EosbSettlement::STATUS_APPROVED)
            ->assertJsonPath('data.approved_by', $this->user->id);

        $this->apiPost("{$this->baseUrl}/settlements/{$settlement->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_a_paid_settlement_cannot_be_approved_again_through_a_stale_copy(): void
    {
        $this->actingAs($this->user, 'api');
        $settlement = $this->settlement($this->employee(), EosbSettlement::STATUS_DRAFT);
        $stale = EosbSettlement::findOrFail($settlement->id);

        // Another request approves it and it is paid before the stale copy acts.
        $settlement->forceFill(['status' => EosbSettlement::STATUS_PAID])->save();

        $this->assertRejected(fn () => app(EosbService::class)->approveSettlement($stale));
        $this->assertSame(EosbSettlement::STATUS_PAID, $settlement->fresh()->status);
    }

    private function policy(array $overrides = [], ?Organization $organization = null): EosbPolicy
    {
        return EosbPolicy::create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id,
            'country_code' => 'SA',
            'calculation_method' => 'saudi',
            'is_active' => true,
        ], $overrides));
    }

    private function employee(): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    private function provision(Employee $employee, EosbPolicy $policy, int $year, int $month): EosbProvision
    {
        return EosbProvision::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'eosb_policy_id' => $policy->id,
            'period_year' => $year,
            'period_month' => $month,
        ]);
    }

    private function settlement(Employee $employee, string $status): EosbSettlement
    {
        return EosbSettlement::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'eosb_policy_id' => $this->policy()->id,
            'termination_date' => now()->toDateString(),
            'years_of_service' => 5,
            'total_days_earned' => 75,
            'daily_rate' => 100,
            'gross_amount' => 7500,
            'net_amount' => 7500,
            'status' => $status,
        ]);
    }
}
