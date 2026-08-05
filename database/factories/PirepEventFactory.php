<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PirepEvent;
use DateTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PirepEvent>
 */
class PirepEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'              => null,
            'pirep_id'        => null,
            'client_event_id' => fake()->uuid(),
            'type'            => fake()->word(),
            'category'        => fake()->randomElement(['message', 'aircraft', 'systems', 'phase', 'milestone', 'violation']),
            'lat'             => fake()->latitude(),
            'lon'             => fake()->longitude(),
            'altitude_msl'    => fake()->numberBetween(20, 400),
            'log'             => fake()->text(100),
            'sim_time'        => fake()->dateTime('now', 'UTC')->format(DateTime::ATOM),
        ];
    }
}
