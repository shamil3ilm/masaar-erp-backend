<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'bank_account_id' => BankAccount::factory(),
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reference' => fake()->bothify('TXN-####??'),
            'description' => fake()->sentence(),
            'transaction_type' => fake()->randomElement(['debit', 'credit']),
            'amount' => fake()->randomFloat(2, 1, 10000),
            'status' => BankTransaction::STATUS_UNMATCHED,
            'import_source' => 'manual',
        ];
    }
}
