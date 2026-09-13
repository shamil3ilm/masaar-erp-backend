<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\HR\Leave\LeaveEncashment;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Services\HR\LeaveAccrualService;
use App\Services\HR\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * How a leave balance is credited, spent and shown.
 *
 * A type is credited by its tiers or by its annual quota, never both, and
 * days that are encashed leave the balance.
 */
class LeaveBalanceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Employee $employee;

    private LeaveType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['hr.leave.view', 'hr.leave.manage']);

        $this->employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'joining_date' => '2020-01-01',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
        ]);

        $policy = LeavePolicy::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'is_active' => true,
        ]);

        $this->type = $this->leaveType(['leave_policy_id' => $policy->id, 'code' => 'AL', 'annual_quota' => 21]);
    }

    public function test_a_yearly_tier_credits_its_entitlement_once(): void
    {
        $this->tier(LeaveTier::ENTITLEMENT_YEARLY, 21);

        $this->accrue('2026-01-31');
        $this->accrue('2026-02-28');

        $balance = $this->balance(2026);
        $this->assertEquals(21, $balance->entitled);
        $this->assertEquals(0, $balance->accrued);
        $this->assertEquals(21, $balance->closing_balance);
        $this->assertSame(1, LeaveAccrual::where('leave_balance_id', $balance->id)->count());
    }

    public function test_a_monthly_tier_credits_a_month_at_most_once_a_month(): void
    {
        $this->tier(LeaveTier::ENTITLEMENT_MONTHLY, 24);

        $this->accrue('2026-03-05');
        $this->accrue('2026-03-20');
        $this->accrue('2026-04-05');

        $balance = $this->balance(2026);
        $this->assertEquals(4, $balance->accrued);
        $this->assertEquals(4, $balance->closing_balance);
        $this->assertSame(2, LeaveAccrual::where('leave_balance_id', $balance->id)->count());
    }

    public function test_a_type_with_tiers_is_not_also_given_its_annual_quota(): void
    {
        $this->tier(LeaveTier::ENTITLEMENT_YEARLY, 21);
        $sick = $this->leaveType(['code' => 'SL', 'annual_quota' => 10]);

        app(LeaveService::class)->initializeYearBalances(2026, $this->organization->id);
        $this->accrue('2026-01-31');

        $this->assertEquals(21, $this->balance(2026)->closing_balance);
        $this->assertEquals(10, $this->balance(2026, $sick)->closing_balance);
    }

    public function test_a_deduction_is_recorded_with_the_balance_either_side(): void
    {
        $balance = $this->heldBalance(10);

        $this->apiPost('/hr/leave-management/adjustments', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'adjustment_type' => 'deduct',
            'days' => 3,
            'reason' => 'Correction',
            'year' => $balance->year,
        ])->assertStatus(201);

        $this->assertEquals(7, $balance->fresh()->closing_balance);

        $adjustment = LeaveAdjustment::sole();
        $this->assertEquals(10, $adjustment->balance_before);
        $this->assertEquals(7, $adjustment->balance_after);

        $this->apiGet('/hr/leave-management/adjustments')->assertOk();
    }

    public function test_an_approved_encashment_leaves_the_balance_and_cannot_be_approved_twice(): void
    {
        $balance = $this->heldBalance(10);

        $this->apiPost('/hr/leave-management/encashments', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'requested_days' => 4,
            'daily_rate' => 100,
            'year' => $balance->year,
        ])->assertStatus(201);

        $this->assertEquals(10, $balance->fresh()->closing_balance);

        $encashment = LeaveEncashment::sole();
        $this->apiPost("/hr/leave-management/encashments/{$encashment->uuid}/approve")->assertOk();

        $this->assertEquals(4, $balance->fresh()->encashed);
        $this->assertEquals(6, $balance->fresh()->closing_balance);
        $this->assertEquals(400, $encashment->fresh()->amount);

        $this->apiPost("/hr/leave-management/encashments/{$encashment->uuid}/approve")->assertStatus(422);
        $this->assertEquals(6, $balance->fresh()->closing_balance);

        $this->apiGet('/hr/leave-management/encashments')->assertOk();
    }

    public function test_self_service_shows_the_balance_it_holds(): void
    {
        $year = (int) now()->year;

        LeaveBalance::factory()->create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'year' => $year,
            'opening_balance' => 2,
            'entitled' => 21,
            'accrued' => 0,
            'taken' => 5,
            'closing_balance' => 18,
        ]);

        LeaveRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'from_date' => "{$year}-06-01",
            'to_date' => "{$year}-06-02",
            'total_days' => 1.5,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->getJson(route('hr.ess.leave-balances', ['year' => $year]), $this->authHeaders());

        $response->assertOk();
        $this->assertEquals(21, $response->json('data.0.entitled'));
        $this->assertEquals(5, $response->json('data.0.used'));
        $this->assertEquals(1.5, $response->json('data.0.pending'));
        $this->assertEquals(18, $response->json('data.0.available'));
        $this->assertEquals(2, $response->json('data.0.carried_forward'));
    }

    private function leaveType(array $attributes): LeaveType
    {
        return LeaveType::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'accrual_type' => LeaveType::ACCRUAL_ANNUAL,
            'applicable_gender' => 'all',
            'applicable_marital_status' => 'all',
            'applicable_after_months' => 0,
            'employment_type_restriction' => null,
            'carry_forward' => false,
            'prorate_on_joining' => false,
            'is_encashable' => true,
            'is_active' => true,
        ]);
    }

    private function tier(string $period, float $entitled): LeaveTier
    {
        return LeaveTier::create([
            'leave_type_id' => $this->type->id,
            'name' => 'Standard',
            'min_service_months' => 0,
            'entitled_days' => $entitled,
            'entitlement_period' => $period,
            'is_active' => true,
        ]);
    }

    private function accrue(string $date): void
    {
        app(LeaveAccrualService::class)->processAccruals($this->organization->id, $date);
    }

    private function balance(int $year, ?LeaveType $type = null): LeaveBalance
    {
        return LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leave_type_id', ($type ?? $this->type)->id)
            ->where('year', $year)
            ->sole();
    }

    private function heldBalance(float $days): LeaveBalance
    {
        return LeaveBalance::factory()->create([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->type->id,
            'year' => (int) now()->year,
            'opening_balance' => 0,
            'accrued' => $days,
            'taken' => 0,
            'closing_balance' => $days,
        ]);
    }
}
