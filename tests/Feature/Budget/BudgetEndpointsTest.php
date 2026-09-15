<?php

declare(strict_types=1);

namespace Tests\Feature\Budget;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use App\Models\Budget\Budget;
use App\Models\Budget\BudgetCommitment;
use App\Models\Budget\BudgetLine;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The budget endpoints: listing and editing budgets and their lines, the
 * submit, approve and activate lifecycle, revisions, commitments and the
 * budget-versus-actual report.
 */
class BudgetEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'budget.budgets.view', 'budget.budgets.create', 'budget.budgets.edit',
        'budget.budgets.delete', 'budget.budgets.approve',
    ];

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'budget',
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $this->setUpAuthenticatedUser(self::PERMISSIONS);

        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_index_lists_this_organizations_budgets_filtered_by_status(): void
    {
        $this->budget(['name' => 'Operations']);
        $this->budget(['name' => 'Capex', 'status' => Budget::STATUS_ACTIVE]);
        $this->budget(['name' => 'Foreign', 'status' => Budget::STATUS_ACTIVE], $this->otherOrganization->id);

        $response = $this->apiGet('/budget/budgets?status=active')->assertOk();

        $this->assertSame(['Capex'], array_column($response->json('data'), 'name'));
    }

    public function test_store_creates_a_budget_with_its_lines_and_total(): void
    {
        $response = $this->apiPost('/budget/budgets', [
            'name' => 'FY Operations',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'lines' => [
                ['name' => 'Rent', 'q1_amount' => 100, 'q2_amount' => 100],
                ['name' => 'Travel', 'q3_amount' => 150],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.status', Budget::STATUS_DRAFT)
            ->assertJsonPath('data.total_amount', '350.00')
            ->assertJsonCount(2, 'data.lines');
    }

    public function test_store_refuses_references_of_another_organization(): void
    {
        $account = Account::factory()->create(['organization_id' => $this->otherOrganization->id]);
        $costCenter = CostCenter::factory()->create(['organization_id' => $this->otherOrganization->id]);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/budget/budgets', [
            'name' => 'FY Operations',
            'fiscal_year_id' => $fiscalYear->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'lines' => [
                ['name' => 'Rent', 'account_id' => $account->id, 'cost_center_id' => $costCenter->id],
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('budgets')->count());
    }

    public function test_show_returns_a_budget_with_its_lines_and_not_another_organizations(): void
    {
        $budget = $this->budget();
        $this->line($budget, ['name' => 'Rent']);
        $foreign = $this->budget([], $this->otherOrganization->id);

        $this->apiGet("/budget/budgets/{$budget->id}")
            ->assertOk()
            ->assertJsonPath('data.lines.0.name', 'Rent');

        $this->apiGet("/budget/budgets/{$foreign->id}")->assertNotFound();
    }

    public function test_update_changes_a_draft_and_refuses_an_active_budget(): void
    {
        $draft = $this->budget();
        $active = $this->budget(['status' => Budget::STATUS_ACTIVE]);

        $this->apiPut("/budget/budgets/{$draft->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->apiPut("/budget/budgets/{$active->id}", ['name' => 'Renamed'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_NOT_EDITABLE');
    }

    public function test_destroy_deletes_a_draft_and_refuses_an_active_budget(): void
    {
        $draft = $this->budget();
        $active = $this->budget(['status' => Budget::STATUS_ACTIVE]);

        $this->apiDelete("/budget/budgets/{$draft->id}")->assertOk();
        $this->assertSoftDeleted('budgets', ['id' => $draft->id]);

        $this->apiDelete("/budget/budgets/{$active->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_NOT_DELETABLE');
    }

    public function test_a_budget_is_submitted_approved_and_activated_in_order(): void
    {
        $budget = $this->budget(['total_amount' => 500]);

        $this->apiPost("/budget/budgets/{$budget->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TRANSITION');

        $this->apiPost("/budget/budgets/{$budget->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->apiPost("/budget/budgets/{$budget->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount', '500.00');
        $this->apiPost("/budget/budgets/{$budget->id}/activate")->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_lines_are_added_and_removed_on_a_draft_keeping_the_total(): void
    {
        $budget = $this->budget();
        $kept = $this->line($budget, ['total_amount' => 40]);

        $added = $this->apiPost("/budget/budgets/{$budget->id}/lines", [
            'name' => 'Travel', 'q1_amount' => 10, 'q2_amount' => 20,
        ])->assertCreated()->json('data');

        $this->assertEquals(70, $budget->fresh()->total_amount);

        $this->apiDelete("/budget/budgets/{$budget->id}/lines/{$added['id']}")->assertOk();

        $this->assertEquals(40, $budget->fresh()->total_amount);
        $this->assertDatabaseHas('budget_lines', ['id' => $kept->id]);
    }

    public function test_update_line_recalculates_a_draft_line(): void
    {
        $budget = $this->budget();
        $line = $this->line($budget, ['q1_amount' => 10, 'total_amount' => 10]);

        $this->apiPut("/budget/budgets/{$budget->id}/lines/{$line->id}", ['q2_amount' => 25])
            ->assertOk()
            ->assertJsonPath('data.total_amount', '35.00');

        $this->assertEquals(35, $budget->fresh()->total_amount);
    }

    public function test_update_line_refuses_a_line_of_an_active_budget(): void
    {
        $budget = $this->budget(['status' => Budget::STATUS_ACTIVE]);
        $line = $this->line($budget, ['q1_amount' => 10, 'total_amount' => 10]);

        $this->apiPut("/budget/budgets/{$budget->id}/lines/{$line->id}", ['q2_amount' => 25])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUDGET_NOT_EDITABLE');

        $this->assertEquals(10, $line->fresh()->total_amount);
    }

    public function test_a_line_of_another_budget_is_not_found_under_this_one(): void
    {
        $budget = $this->budget();
        $otherLine = $this->line($this->budget());

        $this->apiDelete("/budget/budgets/{$budget->id}/lines/{$otherLine->id}")->assertNotFound();
    }

    public function test_a_revision_changes_the_requested_quarter_and_records_it(): void
    {
        $budget = $this->budget(['status' => Budget::STATUS_ACTIVE, 'total_amount' => 40]);
        $line = $this->line($budget, ['q1_amount' => 10, 'q2_amount' => 30, 'total_amount' => 40]);

        $this->apiPost("/budget/budgets/{$budget->id}/revisions", [
            'reason' => 'Rent increase',
            'line_changes' => [
                ['budget_line_id' => $line->id, 'field_changed' => 'q1_amount', 'new_value' => 25],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.new_total', '55.00')
            ->assertJsonCount(1, 'data.lines');

        $line->refresh();
        $this->assertEquals(25, $line->q1_amount);
        $this->assertEquals(55, $line->total_amount);
        $this->assertEquals(55, $budget->fresh()->total_amount);
    }

    public function test_a_revision_refuses_a_line_of_another_organization(): void
    {
        $budget = $this->budget(['status' => Budget::STATUS_ACTIVE]);
        $foreignLine = $this->line($this->budget([], $this->otherOrganization->id), ['q1_amount' => 10, 'total_amount' => 10]);

        $this->apiPost("/budget/budgets/{$budget->id}/revisions", [
            'reason' => 'Probe',
            'line_changes' => [
                ['budget_line_id' => $foreignLine->id, 'field_changed' => 'q1_amount', 'new_value' => 99],
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertEquals(10, $foreignLine->fresh()->q1_amount);
    }

    public function test_commitments_lists_the_commitments_on_the_budgets_lines(): void
    {
        $budget = $this->budget(['status' => Budget::STATUS_ACTIVE]);
        $line = $this->line($budget, ['total_amount' => 100]);
        $otherLine = $this->line($this->budget(['status' => Budget::STATUS_ACTIVE]), ['total_amount' => 100]);

        $this->commitment($line, 30);
        $this->commitment($otherLine, 20);

        $this->apiGet("/budget/budgets/{$budget->id}/commitments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.budget_line.id', $line->id)
            ->assertJsonPath('data.0.creator.name', $this->user->name);
    }

    public function test_vs_actual_reports_a_budget_and_refuses_another_organizations(): void
    {
        $budget = $this->budget(['status' => Budget::STATUS_ACTIVE]);
        $this->line($budget, ['name' => 'Rent', 'total_amount' => 100]);
        $foreign = $this->budget([], $this->otherOrganization->id);

        $this->apiGet("/budget/budgets/vs-actual?budget_id={$budget->id}")
            ->assertOk()
            ->assertJsonPath('data.budget_id', $budget->id)
            ->assertJsonPath('data.lines.0.name', 'Rent');

        $this->apiGet("/budget/budgets/vs-actual?budget_id={$foreign->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    private function budget(array $attributes = [], ?int $organizationId = null): Budget
    {
        return Budget::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId ?? $this->organization->id,
            'name' => 'Operations',
            'status' => Budget::STATUS_DRAFT,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'total_amount' => 0,
        ], $attributes));
    }

    private function line(Budget $budget, array $attributes = []): BudgetLine
    {
        return BudgetLine::create(array_merge([
            'budget_id' => $budget->id,
            'name' => 'Rent',
            'q1_amount' => 0,
            'q2_amount' => 0,
            'q3_amount' => 0,
            'q4_amount' => 0,
            'total_amount' => 0,
            'committed_amount' => 0,
            'actual_amount' => 0,
        ], $attributes));
    }

    private function commitment(BudgetLine $line, float $amount): BudgetCommitment
    {
        return BudgetCommitment::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'budget_line_id' => $line->id,
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'committed_amount' => $amount,
            'status' => BudgetCommitment::STATUS_OPEN,
            'committed_at' => now(),
            'created_by' => $this->user->id,
        ]);
    }
}
