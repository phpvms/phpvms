<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\AwardSnippet;
use Illuminate\Support\Str;

/**
 * @extends Factory<AwardSnippet>
 */
class AwardSnippetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AwardSnippet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);

        return [
            'name'        => Str::slug($label),
            'label'       => Str::title($label),
            'description' => $this->faker->sentence(),
            'conditions'  => [
                'r1' => [
                    'type' => 'state',
                    // `values` (plural) because the state constraint is
                    // multiple(); SelectConstraint reads `value` only when it
                    // is not. The singular key silently matches nothing.
                    'data' => ['operator' => 'is', 'settings' => ['values' => [1]]],
                ],
            ],
        ];
    }
}
