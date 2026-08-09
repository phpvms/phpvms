<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Award;
use App\Models\AwardRule;

/**
 * @extends Factory<AwardRule>
 */
class AwardRuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AwardRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'award_id'   => Award::factory(),
            'conditions' => [
                'combinator' => 'and',
                'rules'      => [
                    ['field' => 'flight_time', 'operator' => '>=', 'value' => 100],
                ],
            ],
        ];
    }
}
