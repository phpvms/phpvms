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
use Exception;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Aircraft>
 */
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
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function definition(): array
    {
        return [
            'id'           => null,
            'subfleet_id'  => fn () => Subfleet::factory()->create()->id,
            'airport_id'   => fn () => Airport::factory()->create()->id,
            'iata'         => fake()->unique()->text(5),
            'icao'         => fake()->unique()->text(5),
            'name'         => fake()->text(50),
            'registration' => fake()->unique()->text(10),
            'hex_code'     => ICAO::createHexCode(),
            'mtow'         => fake()->randomFloat(2, 0, 50000),
            'zfw'          => fake()->randomFloat(2, 0, 50000),
            'status'       => AircraftStatus::ACTIVE,
            'state'        => AircraftState::PARKED,
            'created_at'   => fake()->dateTimeBetween('-1 week')->format(DateTime::ATOM),
            'updated_at'   => fn (array $pirep) => $pirep['created_at'],
        ];
    }
}
