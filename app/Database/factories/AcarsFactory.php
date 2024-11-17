<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Acars;
use DateTime;

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
            'log'          => $this->faker->text(100),
            'lat'          => $this->faker->latitude,
            'lon'          => $this->faker->longitude,
            'distance'     => $this->faker->randomFloat(2, 0, 6000),
            'heading'      => $this->faker->numberBetween(0, 359),
            'altitude_agl' => $this->faker->numberBetween(20, 400),
            'altitude_msl' => $this->faker->numberBetween(20, 400),
            'vs'           => $this->faker->numberBetween(-5000, 5000),
            'gs'           => $this->faker->numberBetween(300, 500),
            'transponder'  => $this->faker->numberBetween(200, 9999),
            'autopilot'    => $this->faker->text(10),
            'fuel'         => $this->faker->randomFloat(2, 100, 1000),
            'fuel_flow'    => $this->faker->randomFloat(2, 100, 1000),
            'sim_time'     => $this->faker->dateTime('now', 'UTC')->format(DateTime::ATOM),
        ];
    }
}
