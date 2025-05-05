<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Subfleet;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subfleet>
 */
class SubfleetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subfleet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'                         => null,
            'airline_id'                 => fn () => \App\Models\Airline::factory()->create()->id,
            'name'                       => fake()->unique()->text(50),
            'type'                       => fake()->unique()->text(7),
            'ground_handling_multiplier' => fake()->numberBetween(50, 200),
        ];
    }
}
