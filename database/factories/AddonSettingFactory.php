<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Addon;
use App\Models\AddonSetting;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<AddonSetting>
 */
class AddonSettingFactory extends Factory
{
    protected $model = AddonSetting::class;

    public function definition(): array
    {
        $key = AddonSetting::formatKey(fake()->unique()->word());

        return [
            'addon_id'    => Addon::factory(),
            'alias'       => fake()->slug(1),
            'order'       => 0,
            'key'         => $key,
            'name'        => fake()->words(2, true),
            'value'       => fake()->word(),
            'default'     => fake()->word(),
            'group'       => 'general',
            'type'        => 'text',
            'options'     => '',
            'description' => fake()->sentence(),
        ];
    }
}
