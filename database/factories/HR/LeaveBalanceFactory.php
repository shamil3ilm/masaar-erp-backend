<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        $opening = fake()->randomFloat(2, 0, 10);
        $accrued = fake()->randomFloat(2, 10, 30);
        $taken = fake()->randomFloat(2, 0, $accrued / 2);

        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'year' => now()->year,
            'opening_balance' => $opening,
            'entitled' => 0,
            'accrued' => $accrued,
            'taken' => $taken,
            'adjustment' => 0,
            'encashed' => 0,
            'lapsed' => 0,
            'closing_balance' => round($opening + $accrued - $taken, 2),
        ];
    }
}
