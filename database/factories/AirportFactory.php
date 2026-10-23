<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Airport;

/**
 * @extends Factory<Airport>
 */
class AirportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Airport::class;

    protected array $usedIcaos = [];

    /**
     * Generate a fake ICAO
     *
     * @throws \Exception
     */
    protected function createFactoryICAO(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($characters) - 1;
        $string = '';
        for ($i = 0; $i < 5; $i++) {
            try {
                $string .= $characters[random_int(0, $max)];
            } catch (Exception) {
            }
        }

        return $string;
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // An id and an ICAO are separate values now that the model's
            // `icao` mutator no longer writes both, but a caller that names
            // only one still expects the other to follow it -- so each of
            // these defaults to whichever was supplied. Laravel expands the
            // definition in key order and hands each closure what it has
            // resolved so far, so `id` sees an overridden `icao` as a literal
            // string while an unsupplied one is still an unresolved closure.
            'id' => function (array $apt): string {
                if (is_string($apt['icao'] ?? null)) {
                    return strtoupper($apt['icao']);
                }

                do {
                    $airport = $this->createFactoryICAO();
                } while (in_array($airport, $this->usedIcaos, true));

                return $airport;
            },
            'icao' => fn (array $apt) => $apt['id'],
            // From the ICAO, not the id: an id wide enough for its own column
            // would not fit in `iata`.
            'iata'                 => fn (array $apt) => $apt['icao'],
            'name'                 => fake()->sentence(3),
            'country'              => fake()->country(),
            'timezone'             => fake()->timezone(),
            'lat'                  => fake()->latitude(),
            'lon'                  => fake()->longitude(),
            'location'             => fake()->city(),
            'region'               => fake()->state(),
            'elevation'            => fake()->numberBetween(0, 10000),
            'hub'                  => false,
            'notes'                => null,
            'ground_handling_cost' => fake()->randomFloat(2, 0, 500),
            'fuel_100ll_cost'      => fake()->randomFloat(2, 1, 10),
            'fuel_jeta_cost'       => fake()->randomFloat(2, 1, 10),
            'fuel_mogas_cost'      => fake()->randomFloat(2, 1, 10),
        ];
    }
}
