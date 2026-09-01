<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Enums\PageStatus;
use App\Enums\PageType;
use App\Models\Page;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);

        return [
            'name'       => $name,
            'slug'       => Str::slug($name),
            'path'       => null,
            'type'       => PageType::PAGE,
            'icon'       => null,
            'public'     => true,
            'enabled'    => true,
            'status'     => PageStatus::DRAFT,
            'body'       => null,
            'draft_body' => $this->faker->paragraph(),
            'link'       => '',
            'new_window' => false,
        ];
    }

    /**
     * A page a visitor can actually reach: enabled, published, and with its
     * draft promoted to `body` exactly as `publish()` would leave it.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled'      => true,
            'status'       => PageStatus::PUBLISHED,
            'body'         => $attributes['draft_body'] ?? $this->faker->paragraph(),
            'published_at' => now(),
        ]);
    }

    public function claiming(string $path): static
    {
        return $this->state(fn (): array => ['path' => Page::normalisePath($path)]);
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['public' => false]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    /**
     * A `PageType::LINK` row — the legacy shape this change migrates away from.
     * Kept so the data migration has something to test against.
     */
    public function link(?string $url = null): static
    {
        return $this->state(fn (): array => [
            'type' => PageType::LINK,
            'link' => $url ?? $this->faker->url(),
            'body' => null,
        ]);
    }
}
