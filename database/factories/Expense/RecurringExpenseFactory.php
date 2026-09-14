<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Core\Organization;
use App\Models\Expense\ExpenseCategory;
use App\Models\Expense\RecurringExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringExpenseFactory extends Factory
{
    protected $model = RecurringExpense::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'organization_id' => Organization::factory(),
            'category_id' => ExpenseCategory::factory(),
            'supplier_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'amount' => fake()->randomFloat(2, 50, 10000),
            'currency_code' => 'SAR',
            'frequency' => fake()->randomElement(RecurringExpense::FREQUENCIES),
            'frequency_interval' => 1,
            'start_date' => $start,
            'end_date' => null,
            'next_occurrence' => $start,
            'occurrences_count' => 0,
            'max_occurrences' => null,
            'auto_approve' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
