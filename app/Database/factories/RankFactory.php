<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Rank;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Rank>
 */
class RankFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Rank::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'                   => null,
            'name'                 => fake()->unique()->text(50),
            'hours'                => fake()->numberBetween(10, 50),
            'acars_base_pay_rate'  => fake()->numberBetween(10, 100),
            'manual_base_pay_rate' => fake()->numberBetween(10, 100),
            'auto_approve_acars'   => 0,
            'auto_approve_manual'  => 0,
            'auto_promote'         => 0,
        ];
    }
}
