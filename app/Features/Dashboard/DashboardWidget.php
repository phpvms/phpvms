<?php

declare(strict_types=1);

namespace App\Features\Dashboard;

use InvalidArgumentException;

/**
 * One pilot-dashboard widget, as registered by a service provider.
 *
 * Build it with {@see self::livewire()} or {@see self::view()}. Two named
 * constructors are used instead of a fluent builder: the render target is the
 * only mutually exclusive input, so each constructor makes the invalid state
 * (both or neither target) impossible to express, and the value stays immutable.
 */
final readonly class DashboardWidget
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
            throw new InvalidArgumentException("Dashboard widget id [{$id}] must be lower-kebab-case.");
        }

        if (!in_array($zone, self::ZONES, true)) {
            throw new InvalidArgumentException("Dashboard widget [{$id}] has invalid zone [{$zone}].");
        }

        if ($span !== 1 && $span !== 2) {
            throw new InvalidArgumentException("Dashboard widget [{$id}] span must be 1 or 2, got [{$span}].");
        }

        if (trim((string) ($livewire ?? $view)) === '') {
            throw new InvalidArgumentException("Dashboard widget [{$id}] needs a non-empty livewire component or view name.");
        }
    }

    /**
     * A widget rendered by a Livewire component, e.g. `dashboard::next-flight`.
     */
    public static function livewire(
        string $id,
        string $component,
        string $title,
        string $icon,
        string $zone = self::ZONE_GRID,
        int $span = 1,
        bool $default = false,
        bool $bare = false,
        int $order = 100,
    ): self {
        return new self($id, $title, $icon, $zone, $span, $default, $bare, $component, null, [], $order);
    }

    /**
     * A widget rendered by a Blade view, which receives `$data`.
     *
     * @param array<string, mixed> $data
     */
    public static function view(
        string $id,
        string $view,
        string $title,
        string $icon,
        string $zone = self::ZONE_GRID,
        int $span = 1,
        bool $default = false,
        bool $bare = false,
        array $data = [],
        int $order = 100,
    ): self {
        return new self($id, $title, $icon, $zone, $span, $default, $bare, null, $view, $data, $order);
    }

    /**
     * The data a widget receives when rendered.
     *
     * Livewire widgets get `$runtime` as mount params. View widgets get the
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
