<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use DateTime;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Flight>
 */
class FlightFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Flight::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'            => fake()->unique()->numberBetween(10, 10000000),
            'airline_id'    => fn () => Airline::factory()->create()->id,
            'flight_number' => fake()->unique()->numberBetween(10, 1000000),
            'route_code'    => fake()->randomElement(['', fake()->text(5)]),
            'route_leg'     => fake()->randomElement(
                ['', fake()->numberBetween(0, 1000)]
            ),
            'dpt_airport_id'       => static fn () => Airport::factory()->create()->id,
            'arr_airport_id'       => static fn () => Airport::factory()->create()->id,
            'alt_airport_id'       => static fn () => Airport::factory()->create()->id,
            'distance'             => fake()->numberBetween(1, 1000),
            'route'                => null,
            'level'                => 0,
            'dpt_time'             => fake()->time(),
            'arr_time'             => fake()->time(),
            'flight_time'          => fake()->numberBetween(60, 360),
            'load_factor'          => fake()->randomElement([15, 20, 50, 90, 100]),
            'load_factor_variance' => fake()->randomElement([15, 20, 50, 90, 100]),
            'has_bid'              => false,
            'active'               => true,
            'visible'              => true,
            'days'                 => 0,
            'start_date'           => null,
            'end_date'             => null,
            'created_at'           => fake()->dateTimeBetween('-1 week')->format(
                DateTime::ATOM
            ),
            'updated_at' => static fn (array $flight) => $flight['created_at'],
            'owner_type' => null,
            'owner_id'   => null,
        ];
    }
}
