<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvancePaymentFactory extends Factory
{
    protected $model = AdvancePayment::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 50000);

        return [
            'organization_id' => Organization::factory(),
            'payment_number' => 'ADV-' . fake()->unique()->numerify('######'),
            'payment_type' => AdvancePayment::TYPE_CUSTOMER,
            'contact_id' => Contact::factory(),
            'contact_name' => fake()->company(),
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'amount' => $amount,
            'base_amount' => $amount,
            'applied_amount' => 0,
            'available_amount' => $amount,
            'payment_method' => fake()->randomElement(['bank_transfer', 'cash', 'cheque', 'card']),
            'reference' => fake()->optional(0.4)->bothify('REF-####'),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => AdvancePayment::STATUS_RECEIVED,
            'received_by' => User::factory(),
        ];
    }
}
