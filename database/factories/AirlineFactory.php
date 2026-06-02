<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;
use Hashids\Hashids;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Airline>
 */
class AirlineFactory extends Factory
{
    protected $model = Airline::class;

    public function definition(): array
    {
        return [
            'icao' => function (array $apt): string {
                $hashids = new Hashids(microtime(), 5);
                $mt = str_replace('.', '', microtime(true));

                return $hashids->encode($mt);
            },
            'iata'    => fn (array $apt) => $apt['icao'],
            'name'    => fake()->company(),
            'country' => fake()->countryCode(),
            'active'  => 1,
        ];
    }
}
