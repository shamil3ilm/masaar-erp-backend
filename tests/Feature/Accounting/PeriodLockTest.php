<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PeriodLockTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.period-lock.manage',
            'accounting.period-lock.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_active_overrides(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/period-lock');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Check Period
    // -------------------------------------------------------------------------

    public function test_check_period_validates_date_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/period-lock/check');

        $response->assertStatus(422);
    }

    public function test_check_period_returns_lock_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/period-lock/check?date=2025-01-15');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['locked']]);
    }

    // -------------------------------------------------------------------------
    // Store (grant override)
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', []);

        $response->assertStatus(422);
    }

    public function test_store_returns_404_for_missing_period(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', [
                'period_id' => 99999,
                'user_id'   => $this->user->id,
                'reason'    => 'Testing',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_grants_an_override_on_an_own_period_to_an_own_user(): void
    {
        $period = $this->periodOf($this->organization);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', [
                'period_id' => $period->id,
                'user_id'   => $this->user->id,
                'reason'    => 'Year-end adjustment',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.period_id', $period->id)
            ->assertJsonPath('data.user.email', $this->user->email);
    }

    public function test_store_refuses_a_period_of_another_organization(): void
    {
        $period = $this->periodOf(Organization::factory()->create());

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', [
                'period_id' => $period->id,
                'user_id'   => $this->user->id,
                'reason'    => 'Cross-tenant attempt',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('period_id');
        $this->assertDatabaseMissing('period_lock_overrides', ['period_id' => $period->id]);
    }

    public function test_store_refuses_a_user_of_another_organization(): void
    {
        $outsider = User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', [
                'period_id' => $this->periodOf($this->organization)->id,
                'user_id'   => $outsider->id,
                'reason'    => 'Cross-tenant attempt',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->assertStringNotContainsString($outsider->email, $response->getContent());
        $this->assertDatabaseMissing('period_lock_overrides', ['user_id' => $outsider->id]);
    }

    public function test_check_period_returns_the_own_period_for_the_date(): void
    {
        $period = $this->periodOf($this->organization);

        $this->withToken($this->token)
            ->getJson('/api/v1/period-lock/check?date=2025-01-15')
            ->assertStatus(200)
            ->assertJsonPath('data.period.id', $period->id)
            ->assertJsonPath('data.locked', false);
    }

    public function test_check_period_ignores_another_organizations_period(): void
    {
        $this->periodOf(Organization::factory()->create());

        $this->withToken($this->token)
            ->getJson('/api/v1/period-lock/check?date=2025-01-15')
            ->assertStatus(200)
            ->assertJsonPath('data.period', null)
            ->assertJsonPath('data.locked', true);
    }

    private function periodOf(Organization $organization): AccountingPeriod
    {
        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $organization->id,
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
        ]);

        return AccountingPeriod::factory()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number'  => 1,
            'start_date'     => '2025-01-01',
            'end_date'       => '2025-01-31',
            'is_closed'      => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Revoke
    // -------------------------------------------------------------------------

    public function test_revoke_returns_404_for_missing_override(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock/99999/revoke');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_check_returns_401(): void
    {
        $this->getJson('/api/v1/period-lock/check?date=2025-01-15')->assertStatus(401);
    }
}
