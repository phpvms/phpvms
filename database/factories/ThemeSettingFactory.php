<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\ThemeSetting;

/**
 * @extends Factory<ThemeSetting>
 */
class ThemeSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ThemeSetting>
     */
    protected $model = ThemeSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'theme'   => 'airside',
            'name'    => fake()->unique()->slug(2),
            'content' => fake()->sentence(),
            'active'  => true,
        ];
    }
}
