<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Promotion;
use App\Models\Sales\PromotionUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionUsageFactory extends Factory
{
    protected $model = PromotionUsage::class;

    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'contact_id' => Contact::factory(),
            'order_type' => 'invoice',
            'order_id' => fake()->numberBetween(1, 100000),
            'discount_amount' => fake()->randomFloat(2, 5, 1000),
        ];
    }
}
