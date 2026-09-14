<?php

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Expense\RecurringExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Processing raises each due recurring expense and moves it to its next date.
 */
class RecurringExpenseTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['expenses.recurring.view', 'expenses.recurring.process']);

        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_due_template_raises_an_expense_and_moves_to_its_next_date(): void
    {
        $template = $this->template(['frequency' => RecurringExpense::FREQUENCY_MONTHLY, 'next_occurrence' => '2026-01-15']);

        $this->process()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.errors', []);

        $this->assertSame(1, Expense::where('recurring_expense_id', $template->id)->count());

        $template->refresh();
        $this->assertSame('2026-02-15', $template->next_occurrence->toDateString());
        $this->assertSame(1, $template->occurrences_count);
        $this->assertTrue($template->is_active);
    }

    public function test_a_template_stops_at_its_last_occurrence(): void
    {
        $template = $this->template(['max_occurrences' => 1]);

        $this->process()->assertJsonPath('data.created', 1);

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_a_yearly_template_moves_a_year(): void
    {
        $template = $this->template(['frequency' => RecurringExpense::FREQUENCY_YEARLY, 'next_occurrence' => '2026-03-01']);

        $this->process()->assertJsonPath('data.created', 1);

        $this->assertSame('2027-03-01', $template->fresh()->next_occurrence->toDateString());
    }

    private function template(array $attributes): RecurringExpense
    {
        return RecurringExpense::create($attributes + [
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'name' => 'Office rent',
            'amount' => 1000,
            'currency_code' => 'SAR',
            'frequency' => RecurringExpense::FREQUENCY_MONTHLY,
            'frequency_interval' => 1,
            'start_date' => '2026-01-15',
            'next_occurrence' => '2026-01-15',
            'auto_approve' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    private function process(): \Illuminate\Testing\TestResponse
    {
        return $this->apiPost('/expenses/recurring/process')->assertOk();
    }
}
