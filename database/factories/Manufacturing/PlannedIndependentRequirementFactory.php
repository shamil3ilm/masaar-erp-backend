<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\PlannedIndependentRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedIndependentRequirement>
 */
class PlannedIndependentRequirementFactory extends Factory
{
    protected $model = PlannedIndependentRequirement::class;

    public function definition(): array
    {
        return [
            'organization_id'   => Organization::factory(),
            'product_id'        => Product::factory(),
            'version'           => 1,
            'is_active'         => true,
            'quantity'          => fake()->randomFloat(2, 10, 500),
            'consumed_quantity' => 0,
            'requirement_date'  => now()->addDays(fake()->numberBetween(1, 28))->toDateString(),
            'valid_from'        => null,
            'valid_to'          => null,
            'notes'             => null,
            'created_by'        => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Mark the requirement as fully consumed.
     *
     * Applied after the attributes are resolved so it matches the final
     * quantity, including one passed to make() or create().
     */
    public function fullyConsumed(): static
    {
        return $this
            ->afterMaking(function (PlannedIndependentRequirement $requirement): void {
                $requirement->consumed_quantity = $requirement->quantity;
            })
            ->afterCreating(function (PlannedIndependentRequirement $requirement): void {
                $requirement->forceFill(['consumed_quantity' => $requirement->quantity])->save();
            });
    }
}
