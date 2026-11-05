<?php

declare(strict_types=1);

use App\Features\Maps\MapCatalog;
use App\Features\Maps\MapProjection;
use App\Features\Maps\MapResolver;
use App\Features\Maps\MapView;

it('projects the corners of every map onto the viewBox corners', function (string $id): void {
    $map = app(MapCatalog::class)->find($id);

    $origin = MapProjection::point($map, $map->latMax, $map->lonMin);
    $far = MapProjection::point($map, $map->latMin, $map->lonMax);

    // width and height are stored rounded to one decimal.
    expect(abs($origin['x']))->toBeLessThan(0.000001)
        ->and(abs($origin['y']))->toBeLessThan(0.000001)
        ->and(abs($far['x'] - $map->width))->toBeLessThan(0.1)
        ->and(abs($far['y'] - $map->height))->toBeLessThan(0.1);
})->with(['world', 'continent:europe', 'continent:oceania', 'country:us', 'country:ru', 'country:fr']);

it('places points across the antimeridian inside the map', function (string $id, float $lat, float $lon): void {
    $map = app(MapCatalog::class)->find($id);
    $point = MapProjection::point($map, $lat, $lon);

    expect($point['x'])->toBeBetween(0.0, $map->width)
        ->and($point['y'])->toBeBetween(0.0, $map->height);
})->with([
    'russia, chukotka' => ['country:ru', 66.0, -170.0],
    'russia, moscow'   => ['country:ru', 55.75, 37.6],
    'usa, new york'    => ['country:us', 40.64, -73.78],
    'fiji'             => ['country:fj', -17.7, 178.0],
    'oceania, tahiti'  => ['continent:oceania', -17.5, -149.6],
]);

it('resolves free-text country names to the right country', function (string $name, string $iso): void {
    expect(app(MapCatalog::class)->forCountryName($name)?->iso2)->toBe($iso);
})->with([
    ['United States', 'us'],
    ['  united states of america ', 'us'],
    ['Viet Nam', 'vn'],
    ['Vietnam', 'vn'],
    ["Côte d'Ivoire", 'ci'],
    ['TÜRKIYE', 'tr'],
]);

it('does not match an unknown country name', function (): void {
    expect(app(MapCatalog::class)->forCountryName('Atlantis'))->toBeNull()
        ->and(app(MapCatalog::class)->forCountryName(null))->toBeNull();
});

it('falls back to the continent holding the point, then the world', function (): void {
    $resolver = app(MapResolver::class);

    expect($resolver->forPoint('Atlantis', 48.85, 2.35)->id)->toBe('continent:europe')
        ->and($resolver->forPoint(null, null, null)->id)->toBe('world')
        ->and($resolver->forPoint('Atlantis', null, null)->id)->toBe('world');
});

it('honours the scope', function (): void {
    $resolver = app(MapResolver::class);

    expect($resolver->forPoint('France', 48.85, 2.35)->id)->toBe('country:fr')
        ->and($resolver->forPoint('France', 48.85, 2.35, 'continent')->id)->toBe('continent:europe')
        ->and($resolver->forPoint('France', 48.85, 2.35, 'world')->id)->toBe('world')
        ->and($resolver->forPoint('Atlantis', 48.85, 2.35, 'country')->id)->toBe('continent:europe');
});

it('shows Alaska and Hawaii on their own maps and the rest of the United States on the main one', function (string $lat, string $lon, string $id): void {
    expect(app(MapResolver::class)->forPoint('United States', (float) $lat, (float) $lon)->id)->toBe($id);
})->with([
    'anchorage'                     => ['61.17', '-149.99', 'country:us-ak'],
    'attu, across the antimeridian' => ['52.83', '173.18', 'country:us-ak'],
    'honolulu'                      => ['21.32', '-157.92', 'country:us-hi'],
    'new york'                      => ['40.64', '-73.78', 'country:us'],
]);

it('keeps the main United States map to the contiguous states', function (): void {
    $catalog = app(MapCatalog::class);
    $us = $catalog->find('country:us');

    expect($us->lonMin)->toBeGreaterThan(-130.0)
        ->and($us->lonShift)->toBe(0)
        ->and($us->regions)->toBe(['us-ak', 'us-hi'])
        ->and($catalog->find('country:us-ak')->parent)->toBe('us')
        ->and($catalog->find('country:us-ak')->file())->toBe('countries/us-ak.svg');
});

it('picks the narrowest map that holds every point', function (): void {
    $resolver = app(MapResolver::class);
    $paris = ['country' => 'France', 'lat' => 48.85, 'lon' => 2.35];

    expect($resolver->forPoints([$paris, ['country' => 'France', 'lat' => 45.76, 'lon' => 4.84]])->id)->toBe('country:fr')
        ->and($resolver->forPoints([$paris, ['country' => 'Germany', 'lat' => 52.52, 'lon' => 13.40]])->id)->toBe('continent:europe')
        ->and($resolver->forPoints([$paris, ['country' => 'United States', 'lat' => 40.64, 'lon' => -73.78]])->id)->toBe('world')
        ->and($resolver->forPoints([$paris, ['country' => null, 'lat' => null, 'lon' => null]])->id)->toBe('country:fr')
        ->and($resolver->forPoints([])->id)->toBe('world');
});

it('draws a great circle from one point to the other, split where it wraps the map', function (): void {
    $world = app(MapCatalog::class)->world();

    $atlantic = MapProjection::greatCircle($world, 40.64, -73.78, 51.47, -0.45);
    expect($atlantic)->toHaveCount(1)
        ->and($atlantic[0][0])->toEqualWithDelta(MapProjection::point($world, 40.64, -73.78), 0.01)
        ->and(end($atlantic[0]))->toEqualWithDelta(MapProjection::point($world, 51.47, -0.45), 0.01)
        // The great circle bends north of the straight line between the two airports.
        ->and(min(array_column($atlantic[0], 'y')))->toBeLessThan(MapProjection::point($world, 51.47, -0.45)['y']);

    expect(MapProjection::greatCircle($world, 33.94, -118.41, 35.55, 139.78))->toHaveCount(2);
});

it('crops a route across the antimeridian as one line past the edge of the world map', function (): void {
    $world = app(MapCatalog::class)->world();
    $view = MapView::make($world, [['lat' => 35.55, 'lon' => 139.78], ['lat' => 33.94, 'lon' => -118.41]], [[[35.55, 139.78], [33.94, -118.41]]], fit: true);

    [$x, , $width] = $view->viewBox;
    $route = $view->routeRuns[0];

    expect($view->routeRuns)->toHaveCount(1)
        ->and($width)->toBeLessThan($world->width / 2)
        ->and($x + $width)->toBeGreaterThan($world->width)
        ->and($view->landCopies())->toBe([$world->width])
        // Each pin sits on its end of the line, Los Angeles one map width east.
        ->and($route[0]['x'])->toEqualWithDelta($view->points[0]['x'], 0.01)
        ->and(end($route)['x'])->toEqualWithDelta($view->points[1]['x'], 0.01)
        ->and($view->points[1]['x'])->toBeGreaterThan($world->width);
});

it('frames the pins above a bottom inset', function (): void {
    $world = app(MapCatalog::class)->world();
    $pins = [['lat' => 32.73, 'lon' => -117.19], ['lat' => 33.94, 'lon' => -118.41]];

    $view = MapView::make($world, $pins, [[[32.73, -117.19], [33.94, -118.41]]], fit: true, fitAspect: 16 / 10, fitInsetBottom: 0.3);
    [, $y, , $height] = $view->viewBox;

    // The pins sit in the middle of the visible 70%, not of the whole view.
    $middle = (array_sum(array_column($view->points, 'y')) / 2 - $y) / $height;
    expect($middle)->toEqualWithDelta(0.35, 0.02);
});
