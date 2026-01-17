<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
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
            'name' => fake()->city(),
            'price' => fake()->randomFloat(2, 10, 500),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'link' => fake()->optional()->url(),
            'picture' => fake()->optional()->imageUrl(),
            'accepted' => fake()->boolean(30),
        ];
    }
}
