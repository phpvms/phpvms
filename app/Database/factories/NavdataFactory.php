<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Enums\NavaidType;
use App\Models\Navdata;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Navdata>
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
            'id'   => str_replace(' ', '', str_replace('.', '', fake()->unique()->text(5))),
            'name' => str_replace('.', '', fake()->unique()->word),
            'type' => fake()->randomElement([NavaidType::VOR, NavaidType::NDB]),
            'lat'  => fake()->latitude,
            'lon'  => fake()->longitude,
            'freq' => fake()->randomFloat(2, 100, 1000),
        ];
    }
}
