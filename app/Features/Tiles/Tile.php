<?php

declare(strict_types=1);

namespace App\Features\Tiles;

use InvalidArgumentException;

/**
 * One tile: a self-contained piece of pilot UI, rendered by a Livewire component
 * or a Blade view, that a theme can place on any page.
 *
 * `showInDashboard` offers the tile on the pilot dashboard. `zone`, `span`,
 * `default` and `order` are dashboard layout hints and are ignored elsewhere.
 *
 * Build it with {@see self::livewire()} or {@see self::view()}. Two named
 * constructors are used instead of a fluent builder: the render target is the
 * only mutually exclusive input, so each constructor makes the invalid state
 * (both or neither target) impossible to express, and the value stays immutable.
 */
final readonly class Tile
{
    public const string ZONE_GRID = 'grid';

    public const string ZONE_SIDEBAR = 'sidebar';

    /** @var list<string> */
    public const array ZONES = [self::ZONE_GRID, self::ZONE_SIDEBAR];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public string $id,
        public string $title,
        public string $icon,
        public bool $showInDashboard,
        public string $zone,
        public int $span,
        public bool $default,
        public bool $bare,
        public ?string $livewire,
        public ?string $view,
        public array $data,
        public int $order,
    ) {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException("Tile id [{$id}] must be lower-kebab-case.");
        }

        if (!in_array($zone, self::ZONES, true)) {
            throw new InvalidArgumentException("Tile [{$id}] has invalid zone [{$zone}].");
        }

        if ($span !== 1 && $span !== 2) {
            throw new InvalidArgumentException("Tile [{$id}] span must be 1 or 2, got [{$span}].");
        }

        if ($default && !$showInDashboard) {
            throw new InvalidArgumentException("Tile [{$id}] is default but not shown in the dashboard.");
        }

        if (trim((string) ($livewire ?? $view)) === '') {
            throw new InvalidArgumentException("Tile [{$id}] needs a non-empty livewire component or view name.");
        }
    }

    /**
     * A tile rendered by a Livewire component, e.g. `phpvmswidgets::weather`.
     */
    public static function livewire(
        string $id,
        string $component,
        string $title,
        string $icon,
        bool $showInDashboard = false,
        string $zone = self::ZONE_GRID,
        int $span = 1,
        bool $default = false,
        bool $bare = false,
        int $order = 100,
    ): self {
        return new self($id, $title, $icon, $showInDashboard, $zone, $span, $default, $bare, $component, null, [], $order);
    }

    /**
     * A tile rendered by a Blade view, which receives `$data`.
     *
     * @param array<string, mixed> $data
     */
    public static function view(
        string $id,
        string $view,
        string $title,
        string $icon,
        array $data = [],
        bool $showInDashboard = false,
        string $zone = self::ZONE_GRID,
        int $span = 1,
        bool $default = false,
        bool $bare = false,
        int $order = 100,
    ): self {
        return new self($id, $title, $icon, $showInDashboard, $zone, $span, $default, $bare, null, $view, $data, $order);
    }

    /**
     * The data a tile receives when rendered.
     *
     * Livewire tiles get `$runtime` as mount params. View tiles get the
     * declared `data` with `$runtime` merged over it, so render-time values win.
     *
     * @param  array<string, mixed> $runtime
     * @return array<string, mixed>
     */
    public function dataWith(array $runtime = []): array
    {
        return $this->livewire !== null ? $runtime : [...$this->data, ...$runtime];
    }
}
