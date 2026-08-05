<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Pirep;
use App\Models\PirepArchive;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<PirepArchive>
 */
class PirepArchiveFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PirepArchive::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pirep_id' => Pirep::factory(),
            'data'     => [],
        ];
    }
}
