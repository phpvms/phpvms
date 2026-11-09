<?php

declare(strict_types=1);

namespace App\Features\Tiles;

use Closure;
use InvalidArgumentException;

/**
 * Registry of the tiles that core, themes and addons provide.
 *
 * A service provider calls {@see self::register()} from `boot()`. Each
 * registration is a closure that builds the {@see Tile}. It runs once at
 * registration to read the id, then again whenever the registry is read. Core
 * ships no tiles and no page that lays them out; a theme reads this registry
 * and renders tiles with `<x-tile>`.
 *
 * The dashboard methods ({@see self::dashboard()}, {@see self::defaultLayout()},
 * {@see self::sanitize()}, {@see self::zone()}, {@see self::available()}) see
 * only tiles with `showInDashboard`.
 *
 * Duplicate ids: the last registration wins, so a theme can override a tile
 * registered earlier. {@see self::remove()} hides an id whatever the boot order.
 * A disabled addon's provider never boots, so its tiles are absent.
 *
 * Octane: bound as a singleton. Registrations happen at boot. The built tiles
 * are kept for one locale and rebuilt when the locale changes, so a closure may
 * call `__()` for its title, but must not read other request state (user,
 * session, request). Only one set is kept because the locale comes from a
 * client cookie: a cache keyed by locale would grow with every value sent.
 */
final class Tiles
{
    /** @var array<string, Closure(): Tile> */
    private array $registrations = [];

    /** @var array<string, true> */
    private array $removed = [];

    /** @var array<string, Tile>|null Built tiles for {@see self::$builtLocale}. */
    private ?array $built = null;

    private ?string $builtLocale = null;

    /**
     * Register a tile. The closure runs once now, only to read the tile's id,
     * and is kept under that id; a tile with the same id is replaced. The tile
     * is built again from the closure when the registry is read.
     *
     * @param Closure(): Tile $build
     */
    public function register(Closure $build): void
    {
        $id = $build()->id;

        unset($this->registrations[$id]);
        $this->registrations[$id] = $build;
        $this->built = null;
    }

    /**
     * Hide a tile, whether it is registered before or after this call.
     */
    public function remove(string $id): void
    {
        $this->removed[$id] = true;
        $this->built = null;
    }

    /**
     * Whether a tile is registered and not removed. Builds nothing.
     */
    public function has(string $id): bool
    {
        return isset($this->registrations[$id]) && !isset($this->removed[$id]);
    }

    /**
     * Every tile, keyed by id, in registration order.
     *
     * @return array<string, Tile>
     */
    public function all(): array
    {
        $locale = app()->getLocale();
        if ($this->built !== null && $this->builtLocale === $locale) {
            return $this->built;
        }

        $tiles = [];
        foreach ($this->registrations as $id => $build) {
            // PHP turns a numeric-string array key such as "123" into an int.
            $id = (string) $id;

            if (isset($this->removed[$id])) {
                continue;
            }

            $tile = $build();
            if ($tile->id !== $id) {
                throw new InvalidArgumentException("Tile [{$id}] must build a tile with the same id.");
            }

            $tiles[$id] = $tile;
        }

        $this->builtLocale = $locale;

        return $this->built = $tiles;
    }

    public function find(string $id): ?Tile
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * Map ids to tiles, in the given order. Unknown ids are skipped.
     *
     * @param  list<string> $ids
     * @return list<Tile>
     */
    public function resolve(array $ids): array
    {
        $tiles = [];
        foreach ($ids as $id) {
            $tile = $this->find($id);
            if ($tile instanceof Tile) {
                $tiles[] = $tile;
            }
        }

        return $tiles;
    }

    /**
     * The tiles offered on the pilot dashboard, keyed by id, in registration order.
     *
     * @return array<string, Tile>
     */
    public function dashboard(): array
    {
        return array_filter($this->all(), fn (Tile $tile): bool => $tile->showInDashboard);
    }

    /**
     * The dashboard's default tiles by zone, sorted by `order` then registration order.
     *
     * @return array{grid: list<string>, sidebar: list<string>}
     */
    public function defaultLayout(): array
    {
        $layout = ['grid' => [], 'sidebar' => []];

        $defaults = array_values(array_filter($this->dashboard(), fn (Tile $tile): bool => $tile->default));
        $positions = array_flip(array_map(fn (Tile $tile): string => $tile->id, $defaults));
        usort($defaults, fn (Tile $a, Tile $b): int => [$a->order, $positions[$a->id]] <=> [$b->order, $positions[$b->id]]);

        foreach ($defaults as $tile) {
            $layout[$tile->zone][] = $tile->id;
        }

        return $layout;
    }

    /**
     * Clean a dashboard layout that came from the browser.
     *
     * Drops unknown or non-dashboard ids, duplicates and non-strings, and keeps
     * each tile in the zone it was saved in. Returns the default layout for
     * broken input: not an array, no zone at all, or a zone that is not a list.
     *
     * @return array{grid: list<string>, sidebar: list<string>}
     */
    public function sanitize(mixed $layout): array
    {
        if (!is_array($layout) || (!isset($layout['grid']) && !isset($layout['sidebar']))) {
            return $this->defaultLayout();
        }

        foreach (Tile::ZONES as $zone) {
            if (isset($layout[$zone]) && !is_array($layout[$zone])) {
                return $this->defaultLayout();
            }
        }

        $dashboard = $this->dashboard();
        $clean = ['grid' => [], 'sidebar' => []];
        $seen = [];

        foreach (Tile::ZONES as $zone) {
            $ids = $layout[$zone] ?? [];

            foreach ($ids as $id) {
                if (!is_string($id) || isset($seen[$id]) || !isset($dashboard[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $clean[$zone][] = $id;
            }
        }

        return $clean;
    }

    /**
     * The dashboard tiles of one zone of a layout, in layout order.
     *
     * @param  array{grid?: list<string>, sidebar?: list<string>} $layout
     * @return list<Tile>
     */
    public function zone(array $layout, string $zone): array
    {
        return array_values(array_filter(
            $this->resolve($layout[$zone] ?? []),
            fn (Tile $tile): bool => $tile->showInDashboard,
        ));
    }

    /**
     * Dashboard tiles not placed in either zone, in registration order. For an "Add tile" menu.
     *
     * @param  array{grid?: list<string>, sidebar?: list<string>} $layout
     * @return list<Tile>
     */
    public function available(array $layout): array
    {
        $placed = array_flip([...($layout['grid'] ?? []), ...($layout['sidebar'] ?? [])]);

        return array_values(array_filter($this->dashboard(), fn (Tile $tile): bool => !isset($placed[$tile->id])));
    }
}
