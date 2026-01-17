<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'link' => fake()->optional()->url(),
            'unit_price' => fake()->randomFloat(2, 5, 100),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
