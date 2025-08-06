<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;
use Hashids\Hashids;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Airline>
 */
class AirlineFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Airline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'   => null,
            'icao' => function (array $apt): string {
                $hashids = new Hashids(microtime(), 5);
                $mt = str_replace('.', '', microtime(true));

                return $hashids->encode($mt);
            },
            'iata'    => fn (array $apt) => $apt['icao'],
            'name'    => fake()->sentence(3),
            'country' => fake()->country,
            'active'  => 1,
        ];
    }
}
