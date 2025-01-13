<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\Subfleet;
use App\Support\ICAO;
use DateTime;

class AircraftFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Aircraft::class;

    /**
     * Define the model's default state.
     *
     * @throws \Exception
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'           => null,
            'subfleet_id'  => fn () => Subfleet::factory()->create()->id,
            'airport_id'   => fn () => Airport::factory()->create()->id,
            'iata'         => $this->faker->unique()->text(5),
            'icao'         => $this->faker->unique()->text(5),
            'name'         => $this->faker->text(50),
            'registration' => $this->faker->unique()->text(10),
            'hex_code'     => ICAO::createHexCode(),
            'mtow'         => $this->faker->randomFloat(2, 0, 50000),
            'zfw'          => $this->faker->randomFloat(2, 0, 50000),
            'status'       => AircraftStatus::ACTIVE,
            'state'        => AircraftState::PARKED,
            'created_at'   => $this->faker->dateTimeBetween('-1 week')->format(DateTime::ATOM),
            'updated_at'   => fn (array $pirep) => $pirep['created_at'],
        ];
    }
}
