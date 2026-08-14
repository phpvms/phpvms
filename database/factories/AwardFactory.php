<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Enums\AwardTrigger;
use App\Models\Award;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Award::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'             => fake()->name(),
            'description'      => fake()->text(10),
            'ref_model_type'   => null,
            'ref_model_params' => null,
        ];
    }

    /**
     * A rules-based award: an AwardRule row holds the conditions tree, no
     * legacy ref_model_type.
     */
    public function rules(?array $conditions = null, AwardTrigger $trigger = AwardTrigger::Pirep): static
    {
        $tree = $conditions ?? [
            'r1' => ['type' => 'flight_time', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 100]]],
        ];

        return $this
            ->state(fn (array $attributes): array => [
                'ref_model_type'   => null,
                'ref_model_params' => null,
                'trigger'          => $trigger,
            ])
            ->afterCreating(function (Award $award) use ($tree): void {
                $award->saveConditionsTree($tree);
            });
    }
}
