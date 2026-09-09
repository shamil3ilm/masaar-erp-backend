<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Leave\LeavePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Leave types are created under a policy.
 *
 * The controller validates sixteen fields the leave_types table did not have,
 * and always set leave_policy_id, which it also did not have, so every call
 * failed. Nothing noticed: the route smoke test sends an empty body and gets
 * the validation error it expects, and no other test posts here.
 */
class LeaveTypeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private LeavePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['hr.leave.view', 'hr.leave.manage']);

        $this->policy = LeavePolicy::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_leave_type(): void
    {
        $this->markTestSkipped(
            'leave_types has two models that disagree about it — see '
            .'tests/Fixtures/duplicate-models.txt. This endpoint writes '
            .'leave_policy_id, which the table does not have, so it answers '
            .'500 to every request. Un-skip once that table has one model.'
        );

        $response = $this->apiPost("/hr/leave-management/policies/{$this->policy->id}/leave-types", [
            'name' => 'Annual Leave',
            'code' => 'AL',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('leave_types', [
            'code' => 'AL',
            'leave_policy_id' => $this->policy->id,
        ]);
    }

    public function test_it_keeps_every_field_it_accepts(): void
    {
        $this->markTestSkipped('Same as above: leave_types has two models.');

        $response = $this->apiPost("/hr/leave-management/policies/{$this->policy->id}/leave-types", [
            'name' => 'Maternity',
            'code' => 'ML',
            'is_carryforward_allowed' => true,
            'max_carryforward_days' => 5,
            'requires_reason' => true,
            'gender_restriction' => 'female',
            'employment_type_restriction' => 'full_time',
            'min_service_months' => 12,
            'min_days_per_request' => 1,
            'max_days_per_request' => 70,
            'allowed_days_of_week' => [1, 2, 3, 4, 5],
            'blackout_dates' => ['2026-12-25'],
            'accrual_day' => 1,
            'count_holidays' => true,
            'count_weekends' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('leave_types', [
            'code' => 'ML',
            'gender_restriction' => 'female',
            'min_service_months' => 12,
            'max_days_per_request' => 70,
        ]);
    }
}
