<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\SimBrief;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SimBrief>
 */
class SimBriefFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SimBrief::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'id'         => fake()->unique()->numberBetween(10, 10000000),
            'user_id'    => null,
            'flight_id'  => null,
            'pirep_id'   => null,
            'acars_xml'  => '',
            'ofp_xml'    => '',
            'created_at' => Carbon::now('UTC')->toDateTimeString(),
            'updated_at' => fn (array $sb) => $sb['created_at'],
        ];
    }
}
