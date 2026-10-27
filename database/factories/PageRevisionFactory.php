<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Page;
use App\Models\PageRevision;

/**
 * @extends Factory<PageRevision>
 */
class PageRevisionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PageRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id'      => Page::factory(),
            'body'         => $this->faker->paragraph(),
            'published_at' => now(),
            'published_by' => null,
        ];
    }
}
