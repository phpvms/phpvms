<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Award;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Award>
 */
class AwardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Award::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'               => null,
            'name'             => fake()->name,
            'description'      => fake()->text(10),
            'ref_model'        => null,
            'ref_model_params' => null,
        ];
    }
}
