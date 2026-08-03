<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Enums\PirepPhase;
use App\Models\Pirep;
use App\Models\PirepPosition;

/**
 * @extends Factory<PirepPosition>
 */
class PirepPositionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PirepPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pirep = Pirep::factory();

        return [
            'pirep_id'     => $pirep,
            'user_id'      => fn (array $attrs): int => Pirep::find($attrs['pirep_id'])->user_id,
            'phase'        => PirepPhase::ENROUTE->value,
            'lat'          => $this->faker->latitude(),
            'lon'          => $this->faker->longitude(),
            'heading'      => $this->faker->numberBetween(0, 359),
            'distance'     => $this->faker->randomFloat(2, 0, 3000),
            'altitude_agl' => $this->faker->numberBetween(0, 38000),
            'altitude_msl' => $this->faker->numberBetween(0, 38000),
            'vs'           => $this->faker->numberBetween(-3000, 3000),
            'gs'           => $this->faker->numberBetween(0, 550),
            'ias'          => $this->faker->numberBetween(0, 350),
            'flight_time'  => $this->faker->numberBetween(0, 600),
            'fuel_used'    => $this->faker->randomFloat(2, 0, 20000),
        ];
    }
}
