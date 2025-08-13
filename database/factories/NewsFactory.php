<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\News;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = News::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'      => null,
            'user_id' => fn () => User::factory()->create()->id,
            'subject' => fake()->text(),
            'body'    => fake()->sentence,
        ];
    }
}
