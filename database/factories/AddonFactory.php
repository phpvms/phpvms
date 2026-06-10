<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Addon;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        $name = Str::studly(fake()->unique()->word());

        return [
            'name'         => $name,
            'registry_id'  => null,
            'type'         => 'module',
            'version'      => fake()->optional()->semver(),
            'namespace'    => 'Modules\\'.$name,
            'path'         => base_path('modules/'.$name),
            'enabled'      => true,
            'installed_at' => now(),
        ];
    }
}
