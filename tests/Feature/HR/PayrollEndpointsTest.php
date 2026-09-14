<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Payroll listings, bulk payslip actions and WPS validation: what each returns
 * and that each stays inside the caller's organization.
 */
class PayrollEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/payroll';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.payroll.view', 'hr.payroll.generate', 'hr.payroll.approve', 'hr.payroll.pay',
        ]);
    }

    public function test_periods_are_filtered_by_status_and_year(): void
    {
        $this->period('2025-01-01', PayrollPeriod::STATUS_OPEN);
        $wanted = $this->period('2026-01-01', PayrollPeriod::STATUS_OPEN);
        $this->period('2026-02-01', PayrollPeriod::STATUS_CLOSED);

        $response = $this->apiGet("{$this->baseUrl}/periods?year=2026&status=open");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_payslips_are_filtered_by_period_and_status(): void
    {
        $period = $this->period('2026-01-01');
        $wanted = $this->payslip($period, Payslip::STATUS_PENDING);
        $this->payslip($period, Payslip::STATUS_DRAFT);
        $this->payslip($this->period('2026-02-01'), Payslip::STATUS_PENDING);

        $response = $this->apiGet("{$this->baseUrl}/payslips?period_id={$period->id}&status=pending");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_single_payslip_is_not_generated_for_another_organizations_employee(): void
    {
        $period = $this->period('2026-01-01');
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/periods/{$period->id}/generate-single", ['employee_id' => $theirs->id])
            ->assertStatus(422);

        $this->assertSame(0, Payslip::withoutGlobalScopes()->count());
    }

    public function test_bulk_approval_approves_only_pending_payslips(): void
    {
        $period = $this->period('2026-01-01');
        $pending = [$this->payslip($period, Payslip::STATUS_PENDING), $this->payslip($period, Payslip::STATUS_PENDING)];
        $draft = $this->payslip($period, Payslip::STATUS_DRAFT);

        $this->apiPost("{$this->baseUrl}/payslips/bulk-approve", [
            'payslip_ids' => [$pending[0]->id, $pending[1]->id, $draft->id],
        ])->assertOk()->assertJsonPath('message', 'Approved 2 payslips.');

        $this->assertSame(Payslip::STATUS_APPROVED, $pending[0]->fresh()->status);
        $this->assertSame(Payslip::STATUS_APPROVED, $pending[1]->fresh()->status);
        $this->assertSame(Payslip::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_bulk_approval_refuses_another_organizations_payslip(): void
    {
        $other = Organization::factory()->create();
        $theirs = Payslip::factory()->create([
            'organization_id' => $other->id,
            'payroll_period_id' => PayrollPeriod::factory()->create(['organization_id' => $other->id])->id,
            'employee_id' => $this->employee($other)->id,
            'status' => Payslip::STATUS_PENDING,
        ]);

        $this->apiPost("{$this->baseUrl}/payslips/bulk-approve", ['payslip_ids' => [$theirs->id]])
            ->assertStatus(422);

        $this->assertSame(Payslip::STATUS_PENDING, Payslip::withoutGlobalScopes()->find($theirs->id)->status);
    }

    public function test_bulk_payment_skips_payslips_that_are_not_approved(): void
    {
        $pending = $this->payslip($this->period('2026-01-01'), Payslip::STATUS_PENDING);

        $this->apiPost("{$this->baseUrl}/payslips/bulk-pay", [
            'payslip_ids' => [$pending->id],
            'payment_mode' => 'bank_transfer',
        ])->assertOk()->assertJsonPath('message', 'Paid 0 payslips.');

        $this->assertSame(Payslip::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_wps_validation_names_paid_employees_without_an_iban_and_nothing_more(): void
    {
        $period = $this->period('2026-01-01');

        $withoutIban = $this->employee();
        $withoutIban->forceFill(['bank_iban' => null])->save();
        $withIban = $this->employee();
        $withIban->forceFill(['bank_iban' => 'AE070331234567890123456'])->save();

        $this->payslip($period, Payslip::STATUS_PAID, $withoutIban);
        $this->payslip($period, Payslip::STATUS_PAID, $withIban);

        $response = $this->apiGet("{$this->baseUrl}/periods/{$period->id}/wps-validate");

        $response->assertOk()
            ->assertJsonPath('message', 'WPS validation complete.')
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.total_employees', 2)
            ->assertJsonPath('data.missing_iban', 1)
            ->assertJsonCount(1, 'data.warnings')
            ->assertJsonPath('data.warnings.0.employee_id', $withoutIban->id)
            ->assertJsonPath('data.warnings.0.employee_number', $withoutIban->employee_number)
            ->assertJsonPath('data.warnings.0.issues', ['Bank IBAN is missing.']);

        $this->assertSame(['employee_id', 'employee_number', 'name', 'issues'], array_keys($response->json('data.warnings.0')));
    }

    private function period(string $startDate, string $status = PayrollPeriod::STATUS_OPEN): PayrollPeriod
    {
        $start = \Illuminate\Support\Carbon::parse($startDate);

        return PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Payroll '.$start->format('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => $status,
        ]);
    }

    private function employee(?Organization $organization = null): Employee
    {
        return Employee::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'branch_id' => $organization === null ? $this->branch->id : null,
        ]);
    }

    private function payslip(PayrollPeriod $period, string $status, ?Employee $employee = null): Payslip
    {
        return Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $period->id,
            'employee_id' => ($employee ?? $this->employee())->id,
            'status' => $status,
        ]);
    }
}
