<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Airport;

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
     * Generate a fake ICAO.
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
            } catch (Exception $e) {
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
            'id' => function () {
                do {
                    $airport = $this->createFactoryICAO();
                } while (in_array($airport, $this->usedIcaos, true));

                return $airport;
            },
            'icao'                 => fn (array $apt) => $apt['id'],
            'iata'                 => fn (array $apt) => $apt['id'],
            'name'                 => $this->faker->sentence(3),
            'country'              => $this->faker->country,
            'timezone'             => $this->faker->timezone,
            'lat'                  => $this->faker->latitude,
            'lon'                  => $this->faker->longitude,
            'hub'                  => false,
            'notes'                => null,
            'ground_handling_cost' => $this->faker->randomFloat(2, 0, 500),
            'fuel_100ll_cost'      => $this->faker->randomFloat(2, 1, 10),
            'fuel_jeta_cost'       => $this->faker->randomFloat(2, 1, 10),
            'fuel_mogas_cost'      => $this->faker->randomFloat(2, 1, 10),
        ];
    }
}
