<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Enums\NavigationTarget;
use App\Models\NavigationItem;
use App\Models\Page;

/**
 * @extends Factory<NavigationItem>
 */
class NavigationItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = NavigationItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'         => fake()->words(2, true),
            'label'        => fake()->unique()->word(),
            'icon'         => null,
            'parent_id'    => null,
            'sort'         => 0,
            'target_type'  => NavigationTarget::URL,
            'target_value' => fake()->url(),
            'new_window'   => false,
            'public'       => true,
            'visible'      => true,
        ];
    }

    public function forPage(Page|int $page): static
    {
        return $this->state(fn (): array => [
            'target_type'  => NavigationTarget::PAGE,
            'target_value' => (string) ($page instanceof Page ? $page->id : $page),
        ]);
    }

    public function forRoute(string $name): static
    {
        return $this->state(fn (): array => [
            'target_type'  => NavigationTarget::ROUTE,
            'target_value' => $name,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['visible' => false]);
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['public' => false]);
    }
}
