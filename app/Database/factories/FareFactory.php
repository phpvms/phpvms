<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Fare;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fare>
 */
class FareFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Fare::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'       => null,
            'code'     => fake()->unique()->text(50),
            'name'     => fake()->text(50),
            'price'    => fake()->randomFloat(2, 100, 1000),
            'cost'     => fn (array $fare) => round($fare['price'] / 2),
            'capacity' => fake()->randomFloat(0, 20, 500),
        ];
    }
}
