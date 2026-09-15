<?php

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseBudget;
use App\Models\Expense\ExpenseCategory;
use App\Services\Expense\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Expenses from entry to approval, the budget amounts they move on the way,
 * and recurring expense templates.
 */
class ExpenseEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
        'expenses.submit', 'expenses.approve', 'expenses.recurring.view', 'expenses.recurring.create',
    ];

    private Organization $otherOrganization;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(self::PERMISSIONS);
        $this->otherOrganization = Organization::factory()->create();

        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_lists_this_organizations_expenses_filtered_by_status_and_search(): void
    {
        $this->expense(['description' => 'Taxi to airport']);
        $this->expense(['description' => 'Taxi to client', 'status' => Expense::STATUS_SUBMITTED]);
        $this->expense(['description' => 'Hotel']);
        $this->expense(['description' => 'Taxi abroad', 'category_id' => ExpenseCategory::factory()->create(['organization_id' => $this->otherOrganization->id])->id], $this->otherOrganization->id);

        $this->apiGet('/expenses?status=draft&search=Taxi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Taxi to airport');
    }

    public function test_an_expense_is_created_with_its_items(): void
    {
        $response = $this->apiPost('/expenses', [
            'category_id' => $this->category->id,
            'expense_date' => '2026-03-10',
            'description' => 'Client dinner',
            'amount' => 100,
            'tax_amount' => 15,
            'items' => [
                ['description' => 'Food', 'amount' => 80],
                ['description' => 'Drinks', 'amount' => 20],
            ],
        ])->assertCreated()
            ->assertJsonPath('message', 'Expense created successfully')
            ->assertJsonPath('data.category.id', $this->category->id)
            ->assertJsonCount(2, 'data.items');

        $this->assertEquals(115, $response->json('data.total_amount'));
    }

    public function test_an_expense_refuses_references_of_another_organization(): void
    {
        $foreignCategory = ExpenseCategory::factory()->create(['organization_id' => $this->otherOrganization->id]);
        $foreignAccount = Account::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/expenses', [
            'category_id' => $foreignCategory->id,
            'account_id' => $foreignAccount->id,
            'expense_date' => '2026-03-10',
            'description' => 'Probe',
            'amount' => 10,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->apiPost('/expenses/recurring', [
            'category_id' => $foreignCategory->id,
            'name' => 'Probe',
            'amount' => 10,
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('expenses')->count());
        $this->assertSame(0, DB::table('recurring_expenses')->count());
    }

    public function test_an_expense_is_shown_and_updated_with_its_items_replaced(): void
    {
        $expense = $this->expense(['total_amount' => 50]);

        $this->apiGet('/expenses/'.$expense->getRouteKey())
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id);

        $this->apiPut('/expenses/'.$expense->getRouteKey(), [
            'description' => 'Updated',
            'items' => [['description' => 'One', 'amount' => 30]],
        ])->assertOk()
            ->assertJsonPath('message', 'Expense updated successfully')
            ->assertJsonPath('data.description', 'Updated')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_updating_a_rejected_expense_returns_it_to_draft(): void
    {
        $expense = $this->expense(['status' => Expense::STATUS_REJECTED]);

        $this->apiPut('/expenses/'.$expense->getRouteKey(), ['description' => 'Receipt attached'])
            ->assertOk()
            ->assertJsonPath('data.status', Expense::STATUS_DRAFT);
    }

    public function test_a_draft_expense_is_deleted_and_a_submitted_one_is_not(): void
    {
        $draft = $this->expense();
        $submitted = $this->expense(['status' => Expense::STATUS_SUBMITTED]);

        $this->apiDelete('/expenses/'.$draft->getRouteKey())
            ->assertOk()
            ->assertJsonPath('message', 'Expense deleted successfully');
        $this->assertSoftDeleted('expenses', ['id' => $draft->id]);

        $this->apiDelete('/expenses/'.$submitted->getRouteKey())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_submitting_and_approving_moves_the_amount_through_the_budget(): void
    {
        $budget = $this->budget();
        $expense = $this->expense(['total_amount' => 200]);

        $this->apiPost('/expenses/'.$expense->getRouteKey().'/submit')
            ->assertOk()
            ->assertJsonPath('message', 'Expense submitted for approval')
            ->assertJsonPath('data.status', Expense::STATUS_SUBMITTED);

        $this->assertEquals(200, $budget->fresh()->committed_amount);

        $this->apiPost('/expenses/'.$expense->getRouteKey().'/review', ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('message', 'Expense approved successfully')
            ->assertJsonPath('data.status', Expense::STATUS_APPROVED);

        $budget->refresh();
        $this->assertEquals(0, $budget->committed_amount);
        $this->assertEquals(200, $budget->spent_amount);
    }

    public function test_rejecting_releases_the_committed_amount(): void
    {
        $budget = $this->budget();
        $expense = $this->expense(['total_amount' => 200]);

        $this->apiPost('/expenses/'.$expense->getRouteKey().'/submit')->assertOk();

        $response = $this->apiPost('/expenses/'.$expense->getRouteKey().'/review', ['action' => 'reject', 'reason' => 'No receipt'])
            ->assertOk()
            ->assertJsonPath('message', 'Expense rejected')
            ->assertJsonPath('data.status', Expense::STATUS_REJECTED);

        $this->assertStringContainsString('Rejection: No receipt', $response->json('data.notes'));
        $this->assertEquals(0, $budget->fresh()->committed_amount);
    }

    public function test_approving_a_draft_is_refused(): void
    {
        $expense = $this->expense();

        $this->apiPost('/expenses/'.$expense->getRouteKey().'/review', ['action' => 'approve'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'REJECTION_FAILED');
    }

    public function test_approving_from_two_stale_copies_moves_the_budget_once(): void
    {
        $this->actingAs($this->user, 'api');
        $budget = $this->budget();
        $service = app(ExpenseService::class);
        $service->submit($this->expense(['total_amount' => 200]));

        $first = Expense::latest('id')->first();
        $second = Expense::latest('id')->first();

        $service->approve($first, $this->user->id);

        try {
            $service->approve($second, $this->user->id);
            $this->fail('An approved expense was approved again.');
        } catch (InvalidArgumentException) {
            // Refused on the locked row.
        }

        $budget->refresh();
        $this->assertEquals(200, $budget->spent_amount);
        $this->assertEquals(0, $budget->committed_amount);
    }

    public function test_recurring_expenses_are_created_and_listed(): void
    {
        $this->apiPost('/expenses/recurring', [
            'category_id' => $this->category->id,
            'name' => 'Office rent',
            'amount' => 5000,
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
        ])->assertCreated()
            ->assertJsonPath('message', 'Recurring expense created successfully')
            ->assertJsonPath('data.name', 'Office rent');

        $this->apiGet('/expenses/recurring?is_active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category.id', $this->category->id);
    }

    private function expense(array $attributes = [], ?int $organizationId = null): Expense
    {
        return Expense::factory()->create(array_merge([
            'organization_id' => $organizationId ?? $this->organization->id,
            'category_id' => $this->category->id,
            'expense_date' => '2026-03-10',
            'status' => Expense::STATUS_DRAFT,
            'amount' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'base_amount' => 100,
            'exchange_rate' => 1,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function budget(): ExpenseBudget
    {
        return ExpenseBudget::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'year' => 2026,
            'month' => 3,
            'budget_amount' => 1000,
            'spent_amount' => 0,
            'committed_amount' => 0,
        ]);
    }
}
