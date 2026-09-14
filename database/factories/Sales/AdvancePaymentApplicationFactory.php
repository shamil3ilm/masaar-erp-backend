<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\AdvancePayment;
use App\Models\Sales\AdvancePaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvancePaymentApplicationFactory extends Factory
{
    protected $model = AdvancePaymentApplication::class;

    public function definition(): array
    {
        return [
            'advance_payment_id' => AdvancePayment::factory(),
            'applied_to_type' => (new Invoice)->getMorphClass(),
            'applied_to_id' => Invoice::factory(),
            'applied_amount' => fake()->randomFloat(2, 100, 10000),
            'applied_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'applied_by' => User::factory(),
        ];
    }
}
