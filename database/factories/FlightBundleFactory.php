<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\FlightBundle;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<FlightBundle>
 */
class FlightBundleFactory extends Factory
{
    protected $model = FlightBundle::class;

    public function definition(): array
    {
        return [
            'name'        => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'enabled'     => true,
            'visible'     => true,
            'start_date'  => null,
            'end_date'    => null,
            'is_default'  => false,
            'created_by'  => null,
        ];
    }
}
