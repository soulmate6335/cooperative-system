<?php

namespace Database\Factories;

use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    protected $model = LoanProduct::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'minimum_membership_months' => 6,
            'minimum_amount_minor' => 10000,
            'maximum_amount_minor' => null,
            'interest_rate_basis_points' => null,
            'interest_method' => null,
            'repayment_months' => 11,
            'required_guarantors' => 2,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }
}
