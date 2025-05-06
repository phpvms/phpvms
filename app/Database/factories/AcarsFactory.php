<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Acars;
use DateTime;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Acars>
 */
class AcarsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Acars::class;

    /**
     * @return array <string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'           => null,
            'pirep_id'     => null,
            'log'          => fake()->text(100),
            'lat'          => fake()->latitude,
            'lon'          => fake()->longitude,
            'distance'     => fake()->randomFloat(2, 0, 6000),
            'heading'      => fake()->numberBetween(0, 359),
            'altitude_agl' => fake()->numberBetween(20, 400),
            'altitude_msl' => fake()->numberBetween(20, 400),
            'vs'           => fake()->numberBetween(-5000, 5000),
            'gs'           => fake()->numberBetween(300, 500),
            'transponder'  => fake()->numberBetween(200, 9999),
            'autopilot'    => fake()->text(10),
            'fuel'         => fake()->randomFloat(2, 100, 1000),
            'fuel_flow'    => fake()->randomFloat(2, 100, 1000),
            'sim_time'     => fake()->dateTime('now', 'UTC')->format(DateTime::ATOM),
        ];
    }
}
