<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Addons\Support\AddonRoots;
use App\Contracts\Factory;
use App\Models\Addon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        $name = Str::studly(fake()->unique()->word());

        return [
            'name'         => $name,
            'registry_id'  => 'phpvms/'.Str::kebab($name),
            'type'         => 'module',
            'version'      => fake()->optional()->semver(),
            'namespace'    => 'Modules\\'.$name,
            'path'         => AddonRoots::installed().'/'.$name,
            'enabled'      => true,
            'installed_at' => now(),
        ];
    }

    /**
     * An addon that ships inside the deployment image.
     *
     * Its files belong to the image layer, so the runtime lifecycle treats it
     * differently: update() refuses it, and delete() disables it instead of
     * removing anything. Use this state to exercise those branches.
     *
     * The default is the writable root instead, because a factory-made addon
     * stands in for an ordinary installed one and most tests expect the full
     * lifecycle to apply to it.
     */
    public function imageManaged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'path' => AddonRoots::image().'/'.($attributes['name'] ?? 'Addon'),
        ]);
    }
}
