<?php

namespace Database\Factories;

use App\Models\FlightField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlightField>
 */
class FlightFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->slug(),
        ];
    }
}
