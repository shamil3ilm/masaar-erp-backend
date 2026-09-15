<?php

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Models\Core\Organization;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseBudget;
use App\Models\Expense\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The setup expenses are booked against: the category tree and the budgets
 * set per category and period.
 */
class ExpenseSetupEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'expenses.categories.view', 'expenses.categories.create', 'expenses.categories.update', 'expenses.categories.delete',
        'expenses.budgets.view', 'expenses.budgets.create', 'expenses.budgets.update', 'expenses.budgets.delete',
    ];

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(self::PERMISSIONS);
        $this->otherOrganization = Organization::factory()->create();
    }

    // ----------------------------------------------------------------
    // Categories
    // ----------------------------------------------------------------

    public function test_categories_are_listed_as_a_tree_or_flat(): void
    {
        $travel = $this->category(['name' => 'Travel']);
        $this->category(['name' => 'Flights', 'parent_id' => $travel->id]);
        $this->category(['name' => 'Foreign'], $this->otherOrganization->id);

        $this->apiGet('/expenses/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Travel')
            ->assertJsonCount(1, 'data.0.children');

        $this->apiGet('/expenses/categories?flat=1')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_category_is_created_shown_and_updated(): void
    {
        $id = $this->apiPost('/expenses/categories', ['name' => 'Meals', 'code' => 'MEAL'])
            ->assertCreated()
            ->assertJsonPath('message', 'Expense category created successfully')
            ->json('data.id');

        $this->apiGet("/expenses/categories/{$id}")->assertOk()->assertJsonPath('data.code', 'MEAL');

        $this->apiPut("/expenses/categories/{$id}", ['name' => 'Meals and entertainment'])
            ->assertOk()
            ->assertJsonPath('message', 'Expense category updated successfully')
            ->assertJsonPath('data.name', 'Meals and entertainment');
    }

    public function test_a_category_refuses_a_parent_of_another_organization(): void
    {
        $foreign = $this->category(['name' => 'Foreign'], $this->otherOrganization->id);

        $this->apiPost('/expenses/categories', ['name' => 'Probe', 'parent_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(1, DB::table('expense_categories')->count());
    }

    public function test_a_category_is_placed_neither_under_itself_nor_under_its_sub_category(): void
    {
        $root = $this->category(['name' => 'Travel']);
        $child = $this->category(['name' => 'Flights', 'parent_id' => $root->id]);
        $grandchild = $this->category(['name' => 'Upgrades', 'parent_id' => $child->id]);

        $this->apiPut("/expenses/categories/{$root->id}", ['parent_id' => $root->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->apiPut("/expenses/categories/{$root->id}", ['parent_id' => $grandchild->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_a_category_in_use_is_not_deleted_and_an_unused_one_is(): void
    {
        $withExpenses = $this->category(['name' => 'Travel']);
        Expense::factory()->create(['organization_id' => $this->organization->id, 'category_id' => $withExpenses->id, 'created_by' => $this->user->id]);
        $withChildren = $this->category(['name' => 'Office']);
        $this->category(['name' => 'Stationery', 'parent_id' => $withChildren->id]);
        $unused = $this->category(['name' => 'Misc']);

        foreach ([$withExpenses, $withChildren] as $category) {
            $this->apiDelete("/expenses/categories/{$category->id}")
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'HAS_DEPENDENCIES');
        }

        $this->apiDelete("/expenses/categories/{$unused->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Expense category deleted successfully');
    }

    // ----------------------------------------------------------------
    // Budgets
    // ----------------------------------------------------------------

    public function test_a_budget_is_saved_once_per_period_and_listed(): void
    {
        $category = $this->category();
        $payload = ['category_id' => $category->id, 'year' => 2026, 'month' => 3, 'budget_amount' => 1000];

        $id = $this->apiPost('/expenses/budgets', $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'Budget saved successfully')
            ->assertJsonPath('data.category.id', $category->id)
            ->json('data.id');

        $again = $this->apiPost('/expenses/budgets', ['budget_amount' => 1500] + $payload)->assertCreated();

        $this->assertSame($id, $again->json('data.id'));
        $this->assertEquals(1500, $again->json('data.budget_amount'));

        $this->apiGet('/expenses/budgets?year=2026')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_budget_refuses_a_category_of_another_organization(): void
    {
        $foreign = $this->category(['name' => 'Foreign'], $this->otherOrganization->id);

        $this->apiPost('/expenses/budgets', ['category_id' => $foreign->id, 'year' => 2026, 'budget_amount' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('expense_budgets')->count());
    }

    public function test_a_budget_is_shown_with_its_utilization_updated_and_deleted(): void
    {
        $budget = $this->budget($this->category(), ['budget_amount' => 1000, 'spent_amount' => 900]);

        $shown = $this->apiGet("/expenses/budgets/{$budget->id}")->assertOk()->assertJsonPath('data.is_exceeded', false);
        $this->assertEquals(100, $shown->json('data.remaining'));
        $this->assertEquals(90, $shown->json('data.utilization_percentage'));

        $updated = $this->apiPut("/expenses/budgets/{$budget->id}", ['budget_amount' => 800])
            ->assertOk()
            ->assertJsonPath('message', 'Budget updated successfully');
        $this->assertEquals(800, $updated->json('data.budget_amount'));

        $this->apiDelete("/expenses/budgets/{$budget->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Budget deleted successfully');
    }

    public function test_checking_a_budget_warns_when_an_expense_would_exceed_it(): void
    {
        $category = $this->category();
        $this->budget($category, ['budget_amount' => 1000, 'spent_amount' => 900]);

        $this->apiGet("/expenses/budgets/check?category_id={$category->id}&amount=200&date=2026-03-15")
            ->assertOk()
            ->assertJsonPath('data.within_budget', false)
            ->assertJsonCount(1, 'data.warnings');
    }

    public function test_utilization_totals_the_periods_budgets(): void
    {
        $this->budget($this->category(), ['budget_amount' => 1000]);
        $this->budget($this->category(), ['budget_amount' => 500]);

        $response = $this->apiGet('/expenses/budgets/utilization?year=2026&month=3')
            ->assertOk()
            ->assertJsonCount(2, 'data.categories');

        $this->assertEquals(1500, $response->json('data.total_budget'));
    }

    private function category(array $attributes = [], ?int $organizationId = null): ExpenseCategory
    {
        return ExpenseCategory::factory()->create(array_merge([
            'organization_id' => $organizationId ?? $this->organization->id,
        ], $attributes));
    }

    private function budget(ExpenseCategory $category, array $attributes = []): ExpenseBudget
    {
        return ExpenseBudget::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'year' => 2026,
            'month' => 3,
            'budget_amount' => 1000,
            'spent_amount' => 0,
            'committed_amount' => 0,
        ], $attributes));
    }
}
