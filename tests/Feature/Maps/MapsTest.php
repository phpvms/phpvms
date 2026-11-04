<?php

declare(strict_types=1);

use App\Features\Maps\MapCatalog;
use App\Features\Maps\MapProjection;
use App\Features\Maps\MapResolver;

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
