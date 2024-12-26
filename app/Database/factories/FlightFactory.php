<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use DateTime;

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
            'id'            => $this->faker->unique()->numberBetween(10, 10000000),
            'airline_id'    => fn () => Airline::factory()->create()->id,
            'flight_number' => $this->faker->unique()->numberBetween(10, 1000000),
            'route_code'    => $this->faker->randomElement(['', $this->faker->text(5)]),
            'route_leg'     => $this->faker->randomElement(
                ['', $this->faker->numberBetween(0, 1000)]
            ),
            'dpt_airport_id'       => static fn () => Airport::factory()->create()->id,
            'arr_airport_id'       => static fn () => Airport::factory()->create()->id,
            'alt_airport_id'       => static fn () => Airport::factory()->create()->id,
            'distance'             => $this->faker->numberBetween(1, 1000),
            'route'                => null,
            'level'                => 0,
            'dpt_time'             => $this->faker->time(),
            'arr_time'             => $this->faker->time(),
            'flight_time'          => $this->faker->numberBetween(60, 360),
            'load_factor'          => $this->faker->randomElement([15, 20, 50, 90, 100]),
            'load_factor_variance' => $this->faker->randomElement([15, 20, 50, 90, 100]),
            'has_bid'              => false,
            'active'               => true,
            'visible'              => true,
            'days'                 => 0,
            'start_date'           => null,
            'end_date'             => null,
            'created_at'           => $this->faker->dateTimeBetween('-1 week')->format(
                DateTime::ATOM
            ),
            'updated_at' => static fn (array $flight) => $flight['created_at'],
            'owner_type' => null,
            'owner_id'   => null,
        ];
    }
}
