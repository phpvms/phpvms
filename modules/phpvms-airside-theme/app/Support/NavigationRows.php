<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

use Illuminate\Http\Request;

/**
 * Flattens the shell's navigation tree into the rows the rail renders, and
 * marks the active one.
 *
 * The active row is the one whose same-origin path equals the page path or is
 * a prefix of it, and the longest such path wins, so `/flights/bids` beats `/flights`.
 *
 * @phpstan-type Item array{label: string, url: string, icon: string|null, new_window: bool, spa: bool, children: array<int, mixed>}
 * @phpstan-type Row array{key: string, item: Item, depth: int, active: bool, group: bool, path: string|null}
 */
final class NavigationRows
{
    /**
     * @param  array<int, array<string, mixed>> $items
     * @return list<Row>
     */
    public static function build(array $items, Request $request): array
    {
        $pagePath = self::normalize($request->getPathInfo());
        $origin = $request->getSchemeAndHttpHost();

        $rows = [];
        self::walk($items, 0, '', $origin, $rows);

        $active = self::activePath($rows, $pagePath);

        return array_map(
            static fn (array $row): array => [...$row, 'active' => $active !== null && $row['path'] === $active],
            $rows,
        );
    }

    /**
     * What every layout needs to draw one menu entry: its icon, children, whether
     * it is a group, current, opened (a descendant is current) and an in-app link.
     *
     * @param  array<string, mixed>                                                                                                   $item
     * @param  list<string>                                                                                                           $activeKeys
     * @return array{icon: string|null, children: list<array<string, mixed>>, isGroup: bool, active: bool, opened: bool, inApp: bool}
     */
    public static function entry(array $item, string $key, array $activeKeys, string $origin): array
    {
        return [
            'icon'     => NavigationIcon::tallstack($item['icon'] ?? null),
            'children' => array_values((array) ($item['children'] ?? [])),
            'isGroup'  => ($item['url'] ?? '') === '',
            'active'   => in_array($key, $activeKeys, true),
            'opened'   => collect($activeKeys)->contains(fn (string $activeKey): bool => str_starts_with($activeKey, $key.'.')),
            'inApp'    => self::menuPath((string) ($item['url'] ?? ''), $origin) !== null,
        ];
    }

    /**
     * The links under a group, nested groups flattened, each with its menu key.
     * A dropdown cannot nest, so the top bar lists these.
     *
     * @param  array<int, array<string, mixed>>                     $children
     * @return list<array{key: string, item: array<string, mixed>}>
     */
    public static function leaves(array $children, string $prefix): array
    {
        $leaves = [];

        foreach (array_values($children) as $index => $child) {
            $key = $prefix.'.'.$index;

            if (($child['url'] ?? '') === '') {
                array_push($leaves, ...self::leaves((array) ($child['children'] ?? []), $key));

                continue;
            }

            $leaves[] = ['key' => $key, 'item' => $child];
        }

        return $leaves;
    }

    /**
     * The path part of a menu URL when it points at this site, else null.
     */
    public static function menuPath(string $url, string $origin): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        if (isset($parts['host'])) {
            $itsOrigin = ($parts['scheme'] ?? 'http').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

            if (strcasecmp($itsOrigin, $origin) !== 0) {
                return null;
            }
        }

        return self::normalize($parts['path'] ?? '/');
    }

    private static function normalize(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param list<array<string, mixed>>       $rows
     */
    private static function walk(array $items, int $depth, string $prefix, string $origin, array &$rows): void
    {
        foreach (array_values($items) as $index => $item) {
            $key = $prefix.$index;

            $rows[] = [
                'key'    => $key,
                'item'   => $item,
                'depth'  => $depth,
                'active' => false,
                'group'  => ($item['url'] ?? '') === '',
                'path'   => self::menuPath((string) ($item['url'] ?? ''), $origin),
            ];

            self::walk((array) ($item['children'] ?? []), $depth + 1, $key.'.', $origin, $rows);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function activePath(array $rows, string $pagePath): ?string
    {
        $best = null;

        foreach ($rows as $row) {
            $path = $row['path'];

            if ($path === null) {
                continue;
            }

            $matches = $pagePath === $path || str_starts_with($pagePath, $path === '/' ? '/' : $path.'/');

            if ($matches && ($best === null || strlen($path) > strlen($best))) {
                $best = $path;
            }
        }

        return $best;
    }
}
