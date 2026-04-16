<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'type'        => fake()->numberBetween(),
            'name'        => fake()->text(50),
            'description' => fake()->text(150),
            'start_date'  => $startDate->format('Y-m-d'),
            'end_date'    => fake()->dateTimeBetween($startDate, '+2 months')->format('Y-m-d'),
            'active'      => true,
        ];
    }
}
