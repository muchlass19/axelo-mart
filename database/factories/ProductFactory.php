<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10, 500) * 1000,
            'stock' => fake()->numberBetween(10, 50),
            'status' => Product::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Product::STATUS_INACTIVE]);
    }
}
