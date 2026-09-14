<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\PayrollCorrection;
use App\Models\HR\PayrollPeriod;
use App\Services\HR\PayrollCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Payroll corrections: a draft records the difference between what was paid
 * and what should have been, then is approved and posted, or cancelled.
 */
class PayrollCorrectionTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/payroll-corrections';

    private Employee $employee;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.payroll.view', 'hr.payroll.process']);

        $this->employee = $this->employee();
        $this->period = PayrollPeriod::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_corrections_are_listed_and_filtered_by_status(): void
    {
        $this->correction();
        $this->correction(['status' => PayrollCorrection::STATUS_APPROVED]);

        $response = $this->apiGet("{$this->baseUrl}?status=approved");

        $this->assertPaginatedResponse($response);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', PayrollCorrection::STATUS_APPROVED);
    }

    public function test_a_created_correction_records_the_difference_as_a_draft(): void
    {
        $this->apiPost($this->baseUrl, $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.status', PayrollCorrection::STATUS_DRAFT)
            ->assertJsonPath('data.difference_amount', '200.0000')
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.original_period.id', $this->period->id);
    }

    public function test_a_correction_cannot_name_another_organizations_employee_or_period(): void
    {
        $other = Organization::factory()->create();
        $theirEmployee = $this->employee($other);
        $theirPeriod = PayrollPeriod::factory()->create(['organization_id' => $other->id]);

        $this->apiPost($this->baseUrl, $this->payload(['employee_id' => $theirEmployee->id]))
            ->assertStatus(422);
        $this->apiPost($this->baseUrl, $this->payload(['original_payroll_period_id' => $theirPeriod->id]))
            ->assertStatus(422);
        $this->apiPost($this->baseUrl, $this->payload(['correction_payroll_period_id' => $theirPeriod->id]))
            ->assertStatus(422);

        $this->apiPut("{$this->baseUrl}/{$this->correction()->id}", ['correction_payroll_period_id' => $theirPeriod->id])
            ->assertStatus(422);

        $this->assertSame(1, PayrollCorrection::count());
    }

    public function test_a_correction_shows_its_employee_periods_and_approver(): void
    {
        $correction = $this->correction();

        $this->apiGet("{$this->baseUrl}/{$correction->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $correction->id)
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.correction_period', null)
            ->assertJsonPath('data.approver', null);
    }

    public function test_another_organizations_correction_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $theirs = PayrollCorrection::create([
            'organization_id' => $other->id,
            'employee_id' => $this->employee($other)->id,
            'original_payroll_period_id' => PayrollPeriod::factory()->create(['organization_id' => $other->id])->id,
            'status' => PayrollCorrection::STATUS_DRAFT,
            'original_amount' => 1,
            'corrected_amount' => 2,
            'difference_amount' => 1,
        ]);

        $this->apiGet("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->id}/approve")->assertNotFound();
    }

    public function test_updating_an_amount_recalculates_the_difference(): void
    {
        $correction = $this->correction();

        $this->apiPut("{$this->baseUrl}/{$correction->id}", ['corrected_amount' => 1500])
            ->assertOk()
            ->assertJsonPath('data.corrected_amount', '1500.0000')
            ->assertJsonPath('data.difference_amount', '500.0000');
    }

    public function test_only_a_draft_correction_can_be_updated(): void
    {
        $approved = $this->correction(['status' => PayrollCorrection::STATUS_APPROVED]);

        $this->apiPut("{$this->baseUrl}/{$approved->id}", ['reason' => 'late change'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_correction_is_approved_then_posted(): void
    {
        $correction = $this->correction();

        $this->apiPost("{$this->baseUrl}/{$correction->id}/post")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->apiPost("{$this->baseUrl}/{$correction->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollCorrection::STATUS_APPROVED)
            ->assertJsonPath('data.approver.id', $this->user->id);

        $this->apiPost("{$this->baseUrl}/{$correction->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->apiPost("{$this->baseUrl}/{$correction->id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollCorrection::STATUS_POSTED);

        $this->apiPost("{$this->baseUrl}/{$correction->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_draft_correction_is_cancelled(): void
    {
        $correction = $this->correction();

        $this->apiPost("{$this->baseUrl}/{$correction->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollCorrection::STATUS_CANCELLED);
    }

    public function test_a_cancelled_correction_cannot_be_approved_through_a_stale_copy(): void
    {
        $this->actingAs($this->user, 'api');
        $service = app(PayrollCorrectionService::class);

        $correction = $this->correction();
        $stale = PayrollCorrection::findOrFail($correction->id);

        $service->cancel($correction);

        $this->assertRejected(fn () => $service->approve($stale, $this->user->id));
        $this->assertSame(PayrollCorrection::STATUS_CANCELLED, $correction->fresh()->status);
    }

    private function employee(?Organization $organization = null): Employee
    {
        return Employee::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'branch_id' => $organization === null ? $this->branch->id : null,
        ]);
    }

    private function correction(array $overrides = []): PayrollCorrection
    {
        return PayrollCorrection::create(array_merge([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'original_payroll_period_id' => $this->period->id,
            'correction_type' => PayrollCorrection::TYPE_SALARY_CHANGE,
            'status' => PayrollCorrection::STATUS_DRAFT,
            'original_amount' => 1000,
            'corrected_amount' => 1200,
            'difference_amount' => 200,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'original_payroll_period_id' => $this->period->id,
            'correction_type' => 'salary_change',
            'original_amount' => 1000,
            'corrected_amount' => 1200,
            'reason' => 'Missed allowance',
        ], $overrides);
    }
}
