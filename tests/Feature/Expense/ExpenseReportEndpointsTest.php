<?php

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Models\Core\Organization;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Expense\ExpenseReport;
use App\Models\HR\Employee;
use App\Services\Expense\ExpenseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An employee's expense report, from collecting expenses to reimbursement.
 */
class ExpenseReportEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'expenses.reports.view', 'expenses.reports.create', 'expenses.reports.update',
        'expenses.reports.submit', 'expenses.reports.approve', 'expenses.reports.reimburse',
    ];

    private Organization $otherOrganization;

    private Employee $employee;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(self::PERMISSIONS);
        $this->otherOrganization = Organization::factory()->create();

        $this->employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_report_is_created_with_expenses_and_listed(): void
    {
        $approved = $this->expense(100, Expense::STATUS_APPROVED);
        $draft = $this->expense(50);

        $this->apiPost('/expenses/reports', [
            'employee_id' => $this->employee->id,
            'title' => 'March travel',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'expense_ids' => [$approved->id, $draft->id],
        ])->assertCreated()
            ->assertJsonPath('message', 'Expense report created successfully')
            ->assertJsonCount(2, 'data.report_items');

        $this->apiGet('/expenses/reports?status=draft')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_report_refuses_an_employee_of_another_organization(): void
    {
        $foreign = Employee::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/expenses/reports', [
            'employee_id' => $foreign->id,
            'title' => 'Probe',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('expense_reports')->count());
    }

    public function test_a_report_is_submitted_approved_and_reimbursed(): void
    {
        $first = $this->expense(100, Expense::STATUS_APPROVED);
        $second = $this->expense(50, Expense::STATUS_APPROVED);
        $report = $this->report();

        $this->apiPost($this->url($report, 'add-expenses'), ['expense_ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Expenses added to report successfully');

        $this->apiPost($this->url($report, 'submit'))
            ->assertOk()
            ->assertJsonPath('data.status', ExpenseReport::STATUS_SUBMITTED);

        $approved = $this->apiPost($this->url($report, 'approve'))
            ->assertOk()
            ->assertJsonPath('message', 'Report approved successfully')
            ->assertJsonPath('data.status', ExpenseReport::STATUS_APPROVED);
        $this->assertEquals(150, $approved->json('data.approved_amount'));

        $this->apiPost($this->url($report, 'reimburse'))
            ->assertOk()
            ->assertJsonPath('data.status', ExpenseReport::STATUS_PAID);

        $this->assertSame(Expense::STATUS_PAID, $first->fresh()->status);
    }

    public function test_an_empty_report_is_not_submitted_and_a_submitted_one_is_rejected_with_its_reason(): void
    {
        $empty = $this->report();

        $this->apiPost($this->url($empty, 'submit'))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'SUBMIT_FAILED');

        $submitted = $this->report(['status' => ExpenseReport::STATUS_SUBMITTED]);

        $this->apiPost($this->url($submitted, 'reject'), ['reason' => 'Missing receipts'])
            ->assertOk()
            ->assertJsonPath('data.status', ExpenseReport::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', 'Missing receipts');
    }

    public function test_reimbursing_from_two_stale_copies_keeps_the_first_reimbursement(): void
    {
        $this->actingAs($this->user, 'api');
        $report = $this->report(['status' => ExpenseReport::STATUS_APPROVED, 'approved_amount' => 150]);
        $service = app(ExpenseReportService::class);

        $first = ExpenseReport::find($report->id);
        $second = ExpenseReport::find($report->id);

        $service->reimburse($first, ['reimbursed_amount' => 150]);

        try {
            $service->reimburse($second, ['reimbursed_amount' => 90]);
            $this->fail('A reimbursed report was reimbursed again.');
        } catch (InvalidArgumentException) {
            // Refused on the locked row.
        }

        $this->assertEquals(150, $report->fresh()->reimbursed_amount);
    }

    private function expense(float $total, string $status = Expense::STATUS_DRAFT): Expense
    {
        return Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'expense_date' => '2026-03-10',
            'status' => $status,
            'amount' => $total,
            'tax_amount' => 0,
            'total_amount' => $total,
            'base_amount' => $total,
            'exchange_rate' => 1,
            'created_by' => $this->user->id,
        ]);
    }

    private function report(array $attributes = []): ExpenseReport
    {
        return ExpenseReport::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'employee_id' => $this->employee->id,
            'status' => ExpenseReport::STATUS_DRAFT,
            'total_amount' => 0,
            'approved_amount' => 0,
            'reimbursed_amount' => 0,
        ], $attributes));
    }

    private function url(ExpenseReport $report, string $action): string
    {
        return '/expenses/reports/'.$report->getRouteKey().'/'.$action;
    }
}
