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
 * Build it with {@see self::livewire()}, {@see self::view()} or
 * {@see self::element()}. Named constructors are used instead of a fluent
 * builder: the render target is the only mutually exclusive input, so each
 * constructor sets exactly one of livewire/view/tag, and the value stays
 * immutable. The constructor enforces that too.
 */
final readonly class Tile
{
    public const string ZONE_GRID = 'grid';

    public const string ZONE_SIDEBAR = 'sidebar';

    /** @var list<string> */
    public const array ZONES = [self::ZONE_GRID, self::ZONE_SIDEBAR];

    public const string KIND_LIVEWIRE = 'livewire';

    public const string KIND_VIEW = 'view';

    public const string KIND_ELEMENT = 'element';

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $attributes Attributes of an element tile; scalars only.
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
        public ?string $tag = null,
        public ?string $module = null,
        public array $attributes = [],
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Tile id must not be empty.');
        }

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

        if (count(array_filter([$livewire, $view, $tag], fn (?string $target): bool => $target !== null)) !== 1) {
            throw new InvalidArgumentException("Tile [{$id}] needs exactly one of livewire, view or tag.");
        }

        if (trim((string) ($livewire ?? $view ?? $tag)) === '') {
            throw new InvalidArgumentException("Tile [{$id}] needs a non-empty livewire component, view name or tag.");
        }

        if ($tag !== null) {
            if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)+$/', $tag) !== 1) {
                throw new InvalidArgumentException("Tile [{$id}] tag [{$tag}] must be a custom element name such as [pv-hero].");
            }

            if (trim((string) $module) === '') {
                throw new InvalidArgumentException("Tile [{$id}] needs a non-empty module url.");
            }

            foreach ($attributes as $name => $value) {
                if (preg_match('/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/', (string) $name) !== 1) {
                    throw new InvalidArgumentException("Tile [{$id}] attribute name [{$name}] is not valid.");
                }

                if (!is_scalar($value)) {
                    throw new InvalidArgumentException("Tile [{$id}] attribute [{$name}] must be a scalar value.");
                }
            }
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
     * A tile rendered as a custom element, e.g. `<pv-hero>`, loaded from the
     * ES module at `$module`. `$attributes` are scalar values written onto the
     * element; a module shared by several tiles is loaded once per page.
     *
     * @param array<string, mixed> $attributes
     */
    public static function element(
        string $id,
        string $tag,
        string $module,
        string $title,
        string $icon,
        array $attributes = [],
        bool $showInDashboard = true,
        string $zone = self::ZONE_GRID,
        int $span = 1,
        bool $default = false,
        int $order = 100,
    ): self {
        return new self($id, $title, $icon, $showInDashboard, $zone, $span, $default, false, null, null, [], $order, $tag, $module, $attributes);
    }

    /**
     * Which render target the tile has: `livewire`, `view` or `element`.
     */
    public function kind(): string
    {
        return match (true) {
            $this->tag !== null  => self::KIND_ELEMENT,
            $this->view !== null => self::KIND_VIEW,
            default              => self::KIND_LIVEWIRE,
        };
    }

    /**
     * The data a tile receives when rendered.
     *
     * Livewire tiles get `$runtime` as mount params. Element tiles ignore it;
     * their attributes are declared. View tiles get the
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
