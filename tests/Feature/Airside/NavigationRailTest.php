<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\PhpvmsAirsideTheme\Support\NavigationRows;

/**
 * 6.2: the rail marks the longest matching path as current.
 */
function menuItem(string $url, array $children = [], string $label = 'Item'): array
{
    return ['label' => $label, 'url' => $url, 'icon' => null, 'new_window' => false, 'spa' => true, 'children' => $children];
}

function activeLabels(array $menu, string $path): array
{
    return collect(NavigationRows::build($menu, Request::create($path)))
        ->filter(fn (array $row): bool => $row['active'])
        ->map(fn (array $row): string => $row['item']['label'])
        ->values()
        ->all();
}

it('marks only the longest matching item as current', function (): void {
    $menu = [
        menuItem('http://localhost/flights', label: 'Flights'),
        menuItem('http://localhost/flights/bids', label: 'Bids'),
    ];

    expect(activeLabels($menu, '/flights/bids'))->toBe(['Bids'])
        ->and(activeLabels($menu, '/flights'))->toBe(['Flights'])
        ->and(activeLabels($menu, '/flights/12'))->toBe(['Flights']);
});

it('does not match on a partial path segment', function (): void {
    $menu = [menuItem('http://localhost/flights', label: 'Flights')];

    expect(activeLabels($menu, '/flights-extra'))->toBe([]);
});

it('ignores groups and other origins', function (): void {
    $menu = [
        menuItem('', [menuItem('http://localhost/tours', label: 'Tours')], 'Group'),
        menuItem('https://elsewhere.test/tours', label: 'Elsewhere'),
    ];

    expect(activeLabels($menu, '/tours'))->toBe(['Tours']);
});

it('indents nested items and flags groups', function (): void {
    $menu = [menuItem('', [menuItem('http://localhost/a'), menuItem('http://localhost/b')], 'Group')];

    $rows = NavigationRows::build($menu, Request::create('/a'));

    expect(array_column($rows, 'depth'))->toBe([0, 1, 1])
        ->and(array_column($rows, 'group'))->toBe([true, false, false])
        ->and(array_column($rows, 'key'))->toBe(['0', '0.0', '0.1']);
});
