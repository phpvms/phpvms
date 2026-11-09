<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Enums\GlobalSlotPosition;
use App\Enums\GlobalSlotType;
use App\Models\GlobalSlot;

/**
 * @extends Factory<GlobalSlot>
 */
class GlobalSlotFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<GlobalSlot>
     */
    protected $model = GlobalSlot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'     => GlobalSlot::SITE_KEY,
            'slot'    => GlobalSlotPosition::HEAD->value,
            'type'    => GlobalSlotType::HTML,
            'name'    => fake()->unique()->words(2, true),
            'value'   => '<meta name="'.fake()->unique()->slug(2).'" content="x">',
            'order'   => 0,
            'enabled' => true,
        ];
    }

    public function css(): static
    {
        return $this->state(fn (): array => [
            'slot'  => GlobalSlotPosition::CSS->value,
            'type'  => GlobalSlotType::CSS,
            'value' => '.'.fake()->unique()->slug(2).'{color:red}',
        ]);
    }

    public function footer(): static
    {
        return $this->state(fn (): array => [
            'slot' => GlobalSlotPosition::FOOTER->value,
            'type' => GlobalSlotType::HTML,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
