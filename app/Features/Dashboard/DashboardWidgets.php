<?php

declare(strict_types=1);

namespace App\Features\Dashboard;

use Closure;
use InvalidArgumentException;

/**
 * Registry of the pilot-dashboard widgets.
 *
 * Core, a theme or an addon calls {@see self::register()} from its service
 * provider's `boot()`. Each registration is a closure that builds the
 * {@see DashboardWidget}; closures run when the registry is first read, never
 * at boot. Core ships no first-party widgets and there is no dashboard board in
 * core; a theme reads this registry to build its "Add widget" menu, default
 * layout and widget rendering.
 *
 * Duplicate ids: the last registration wins, so a theme can override a widget
 * registered earlier. {@see self::remove()} hides an id whatever the boot order.
 * A disabled addon's provider never boots, so its widgets are absent.
 *
 * Octane: bound as a singleton. Registrations happen at boot. Built widgets
 * are kept per locale, so a closure may call `__()` for its title, but must
 * not read other request state (user, session, request).
 */
final class DashboardWidgets
{
    /** @var array<string, Closure(): DashboardWidget> */
    private array $registrations = [];

    /** @var array<string, true> */
    private array $removed = [];

    /** @var array<string, array<string, DashboardWidget>> Built widgets, keyed by locale. */
    private array $built = [];

    /**
     * @param Closure(): DashboardWidget $build Returns the widget; its id must equal $id.
     */
    public function register(string $id, Closure $build): void
    {
        unset($this->registrations[$id]);
        $this->registrations[$id] = $build;
        $this->built = [];
    }

    /**
     * Hide a widget, whether it is registered before or after this call.
     */
    public function remove(string $id): void
    {
        $this->removed[$id] = true;
        $this->built = [];
    }

    /**
     * Whether a widget is registered and not removed. Builds nothing.
     */
    public function has(string $id): bool
    {
        return isset($this->registrations[$id]) && !isset($this->removed[$id]);
    }

    /**
     * @return array<string, DashboardWidget>
     */
    public function all(): array
    {
        $locale = app()->getLocale();
        if (isset($this->built[$locale])) {
            return $this->built[$locale];
        }

        $widgets = [];
        foreach ($this->registrations as $id => $build) {
            // PHP turns a numeric-string array key such as "123" into an int.
            $id = (string) $id;

            if (isset($this->removed[$id])) {
                continue;
            }

            $widget = $build();
            if ($widget->id !== $id) {
                throw new InvalidArgumentException("Dashboard widget [{$id}] must build a widget with the same id.");
            }

            $widgets[$id] = $widget;
        }

        return $this->built[$locale] = $widgets;
    }

    public function find(string $id): ?DashboardWidget
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * Default widgets by zone, sorted by `order` then registration order.
     *
     * @return array{grid: list<string>, sidebar: list<string>}
     */
    public function defaultLayout(): array
    {
        $layout = ['grid' => [], 'sidebar' => []];

        $defaults = array_values(array_filter($this->all(), fn (DashboardWidget $w): bool => $w->default));
        $positions = array_flip(array_map(fn (DashboardWidget $w): string => $w->id, $defaults));
        usort($defaults, fn (DashboardWidget $a, DashboardWidget $b): int => [$a->order, $positions[$a->id]] <=> [$b->order, $positions[$b->id]]);

        foreach ($defaults as $widget) {
            $layout[$widget->zone][] = $widget->id;
        }

        return $layout;
    }

    /**
     * Clean a layout that came from the browser.
     *
     * Drops unknown ids, duplicates and non-strings, keeps each widget in the
     * zone it was saved in, and returns the default layout for broken input.
     *
     * @return array{grid: list<string>, sidebar: list<string>}
     */
    public function sanitize(mixed $layout): array
    {
        if (!is_array($layout) || (!isset($layout['grid']) && !isset($layout['sidebar']))) {
            return $this->defaultLayout();
        }

        $clean = ['grid' => [], 'sidebar' => []];
        $seen = [];

        foreach (DashboardWidget::ZONES as $zone) {
            $ids = $layout[$zone] ?? [];
            if (!is_array($ids)) {
                continue;
            }

            foreach ($ids as $id) {
                if (!is_string($id) || isset($seen[$id]) || !isset($this->all()[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $clean[$zone][] = $id;
            }
        }

        return $clean;
    }

    /**
     * Map ids to widgets, in the given order. Unknown ids are skipped.
     *
     * @param  list<string>          $ids
     * @return list<DashboardWidget>
     */
    public function resolve(array $ids): array
    {
        $widgets = [];
        foreach ($ids as $id) {
            $widget = $this->find($id);
            if ($widget instanceof DashboardWidget) {
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    /**
     * The widgets of one zone of a (sanitized) layout.
     *
     * @param  array{grid?: list<string>, sidebar?: list<string>} $layout
     * @return list<DashboardWidget>
     */
    public function zone(array $layout, string $zone): array
    {
        return $this->resolve($layout[$zone] ?? []);
    }

    /**
     * Registered widgets not placed in either zone, in catalog order. For an "Add widget" menu.
     *
     * @param  array{grid?: list<string>, sidebar?: list<string>} $layout
     * @return list<DashboardWidget>
     */
    public function available(array $layout): array
    {
        $placed = array_flip([...($layout['grid'] ?? []), ...($layout['sidebar'] ?? [])]);

        return array_values(array_filter($this->all(), fn (DashboardWidget $w): bool => !isset($placed[$w->id])));
    }
}
