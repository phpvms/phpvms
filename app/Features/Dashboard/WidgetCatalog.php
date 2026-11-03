<?php

declare(strict_types=1);

namespace App\Features\Dashboard;

/**
 * The dashboard widgets airside can place: the bundled `route`, `activity`
 * and `news` widgets. Addon widgets are not shown yet, so an id that is not listed here is
 * dropped from a saved layout.
 *
 * A layout is two ordered id lists, one per zone: `grid` and `sidebar`.
 */
final class WidgetCatalog
{
    public const string ZONE_GRID = 'grid';

    public const string ZONE_SIDEBAR = 'sidebar';

    /**
     * @return array<string, array{id: string, title: string, icon: string, zone: string, span: int, default: bool}>
     */
    public static function all(): array
    {
        return [
            'route' => [
                'id'      => 'route',
                'title'   => 'Nav display',
                'icon'    => 'globe-alt',
                'zone'    => self::ZONE_GRID,
                'span'    => 2,
                'default' => true,
            ],
            'activity' => [
                'id'      => 'activity',
                'title'   => (string) __('ui.activity'),
                'icon'    => 'bolt',
                'zone'    => self::ZONE_GRID,
                'span'    => 2,
                'default' => true,
            ],
            'news' => [
                'id'      => 'news',
                'title'   => (string) __('ui.latest_news'),
                'icon'    => 'newspaper',
                'zone'    => self::ZONE_SIDEBAR,
                'span'    => 1,
                'default' => true,
            ],
        ];
    }

    /**
     * @return array{id: string, title: string, icon: string, zone: string, span: int, default: bool}|null
     */
    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /**
     * @return array{grid: list<string>, sidebar: list<string>}
     */
    public static function defaultLayout(): array
    {
        $layout = ['grid' => [], 'sidebar' => []];

        foreach (self::all() as $widget) {
            if ($widget['default']) {
                $layout[$widget['zone']][] = $widget['id'];
            }
        }

        return $layout;
    }

    /**
     * A layout from the browser, cleaned: unknown ids are dropped and an id is
     * placed once. Anything that is not a layout gives the default.
     *
     * @param  array<mixed>|null                                $layout
     * @return array{grid: list<string>, sidebar: list<string>}
     */
    public static function sanitize(?array $layout): array
    {
        if (!is_array($layout['grid'] ?? null) || !is_array($layout['sidebar'] ?? null)) {
            return self::defaultLayout();
        }

        $seen = [];
        $clean = ['grid' => [], 'sidebar' => []];

        foreach (['grid', 'sidebar'] as $zone) {
            foreach ($layout[$zone] as $id) {
                if (is_string($id) && self::find($id) !== null && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $clean[$zone][] = $id;
                }
            }
        }

        return $clean;
    }
}
