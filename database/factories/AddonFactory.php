<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Addon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        return [
            'registry_id'  => null,
            'type'         => 'module',
            'version'      => fake()->optional()->semver(),
            'namespace'    => 'Modules\\'.fake()->unique()->word(),
            'path'         => base_path('modules/'.fake()->unique()->word()),
            'enabled'      => true,
            'installed_at' => now(),
        ];
    }
}
