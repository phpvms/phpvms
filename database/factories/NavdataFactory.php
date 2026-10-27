<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Enums\NavaidType;
use App\Models\Navdata;

/**
 * @extends Factory<Navdata>
 */
class NavdataFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Navdata::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'   => fake()->unique()->lexify('?????'),
            'name' => str_replace('.', '', fake()->unique()->word()),
            'type' => fake()->randomElement([NavaidType::VOR, NavaidType::NDB]),
            'lat'  => fake()->latitude(),
            'lon'  => fake()->longitude(),
            'freq' => fake()->randomFloat(2, 100, 1000),
        ];
    }
}
