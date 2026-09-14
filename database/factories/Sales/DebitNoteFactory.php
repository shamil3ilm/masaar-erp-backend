<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\DebitNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebitNoteFactory extends Factory
{
    protected $model = DebitNote::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 50000);
        $tax = round($subtotal * 0.15, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'debit_note_number' => 'DN-' . fake()->unique()->numerify('######'),
            'bill_id' => null,
            'contact_id' => Contact::factory(),
            'contact_name' => fake()->company(),
            'debit_note_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => $total,
            'applied_amount' => 0,
            'available_amount' => $total,
            'reason_code' => null,
            'reason' => fake()->sentence(),
            'status' => DebitNote::STATUS_DRAFT,
            'journal_entry_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
