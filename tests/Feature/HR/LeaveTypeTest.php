<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Leave types are created under a policy and keep every rule they are given.
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
        $response = $this->apiPost("/hr/leave-management/policies/{$this->policy->id}/leave-types", [
            'name' => 'Maternity',
            'code' => 'ML',
            'carry_forward' => true,
            'max_carry_forward_days' => 5,
            'requires_reason' => true,
            'applicable_gender' => 'female',
            'employment_type_restriction' => 'full_time',
            'applicable_after_months' => 12,
            'min_days_per_request' => 1,
            'max_days_per_request' => 70,
            'allowed_days_of_week' => [1, 2, 3, 4, 5],
            'blackout_dates' => ['2026-12-25'],
            'accrual_type' => 'none',
            'accrual_day' => 1,
            'count_holidays' => true,
            'count_weekends' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('leave_types', [
            'code' => 'ML',
            'applicable_gender' => 'female',
            'employment_type_restriction' => 'full_time',
            'applicable_after_months' => 12,
            'max_days_per_request' => 70,
            'accrual_type' => 'none',
        ]);

        $type = LeaveType::where('code', 'ML')->sole();
        $this->assertSame([1, 2, 3, 4, 5], $type->allowed_days_of_week);
        $this->assertSame(['2026-12-25'], $type->blackout_dates);
        $this->assertTrue($type->requires_reason);
        $this->assertTrue($type->count_holidays);
    }
}
