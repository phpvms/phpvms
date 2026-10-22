<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\MapLayer;

/**
 * @extends Factory<MapLayer>
 */
class MapLayerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MapLayer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => $this->faker->words(2, true),
            'type'              => MapLayer::TYPE_RASTER,
            'url_template'      => 'https://example.com/tiles/{z}/{x}/{y}.png',
            'url_template_dark' => null,
            'attribution'       => $this->faker->company(),
            'min_zoom'          => 0,
            'max_zoom'          => 22,
            'opacity'           => 1,
            'enabled'           => true,
            'order'             => 0,
            'api_key'           => null,
            'surfaces'          => null,
        ];
    }

    /**
     * A vector-type layer.
     */
    public function vector(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type'         => MapLayer::TYPE_VECTOR,
            'url_template' => 'https://example.com/tiles/{z}/{x}/{y}.pbf',
        ]);
    }

    /**
     * A disabled layer.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    /**
     * A `basemap` row: the map's own style, with the light/dark pair the
     * surface swaps between. Not an overlay — `MapLayer::overlays()` excludes
     * it on purpose.
     */
    public function basemap(): static
    {
        return $this->state(fn (): array => [
            'type'              => MapLayer::TYPE_BASEMAP,
            'url_template'      => 'https://tiles.example.com/styles/light/style.json',
            'url_template_dark' => 'https://tiles.example.com/styles/dark/style.json',
        ]);
    }
}
