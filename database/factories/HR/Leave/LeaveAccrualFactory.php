<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\LeaveBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveAccrualFactory extends Factory
{
    protected $model = LeaveAccrual::class;

    public function definition(): array
    {
        return [
            'leave_balance_id' => LeaveBalance::factory(),
            'employee_id' => Employee::factory(),
            'accrual_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'accrual_type' => fake()->randomElement([LeaveAccrual::TYPE_MONTHLY, LeaveAccrual::TYPE_YEARLY]),
            'days' => fake()->randomFloat(2, 0.5, 5),
            'description' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}
