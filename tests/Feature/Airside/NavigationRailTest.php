<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Models\NavigationItem;
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

it('renders the current item with aria-current, groups as nested items, and the rest as links', function (): void {
    $pilot = useAirside();
    $this->withoutVite();

    NavigationItem::query()->delete();
    $group = NavigationItem::factory()->create(['label' => 'Operations', 'target_value' => '']);
    NavigationItem::factory()->forRoute('frontend.flights.index')->create(['label' => 'Flights']);
    NavigationItem::factory()->forRoute('frontend.flights.bids')->create(['label' => 'My bids', 'parent_id' => $group->id]);

    $html = $this->actingAs($pilot)->get('/flights/bids/probe')->getContent();

    // TallStackUI renders the sidebar twice (mobile drawer and desktop panel), so every item appears in both.
    expect($html)->toMatch('/<a\s+href="[^"]*\/flights\/bids"[^>]*aria-current="page"/s')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(2)
        ->and($html)->not->toMatch('/<a[^>]*>\s*Operations\s*<\/a>/s')
        ->and($html)->toMatch('/<button[^>]*>.*Operations.*<\/button>/s')
        ->and($html)->toContain('My bids');
});
