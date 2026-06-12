<?php

declare(strict_types=1);

use App\Http\Resources\RouteForge\RouteForgeAirportResource;
use App\Models\Airport;
use Illuminate\Http\Request;

/*
 * Verifies the resource's decoration logic.
 *
 * Decoration context flows in via $request->attributes (Symfony attribute
 * bag) so the resource is decoupled from controller-side model mutation.
 * Tests cover: no decoration, distance-only, distance + range, missing
 * coordinates, and confirmation that the Airport model attribute bag is
 * untouched after the resource runs.
 */

function rfReq(?Airport $origin = null, ?int $maxRangeNm = null): Request
{
    $request = Request::create('/admin/route-forge/api/preview-airports', 'GET');
    if ($origin instanceof Airport) {
        $request->attributes->set('routeforge.origin', $origin);
    }

    if ($maxRangeNm !== null) {
        $request->attributes->set('routeforge.max_range_nm', $maxRangeNm);
    }

    return $request;
}

function makeAirport(string $id, ?float $lat, ?float $lon): Airport
{
    $airport = new Airport();
    $airport->forceFill([
        'id'       => $id,
        'icao'     => $id,
        'iata'     => substr($id, 1, 3),
        'name'     => $id,
        'lat'      => $lat,
        'lon'      => $lon,
        'timezone' => 'UTC',
        'hub'      => false,
    ]);

    return $airport;
}

it('emits no decoration keys when no origin attribute is set', function (): void {
    $resource = new RouteForgeAirportResource(makeAirport('KOAK', 37.7, -122.5));

    $output = $resource->toArray(rfReq());

    expect($output)->not->toHaveKey('distance_from_origin_nm')
        ->and($output)->not->toHaveKey('in_subfleet_range');
});

it('adds distance_from_origin_nm when origin is set and both endpoints have coordinates', function (): void {
    $origin = makeAirport('KSFO', 37.6213, -122.379);
    $row = makeAirport('KOAK', 37.7, -122.5);
    $resource = new RouteForgeAirportResource($row);

    $output = $resource->toArray(rfReq(origin: $origin));

    expect($output)->toHaveKey('distance_from_origin_nm')
        ->and($output['distance_from_origin_nm'])->toBeFloat()
        // KSFO ↔ KOAK is well under 20 nm.
        ->and($output['distance_from_origin_nm'])->toBeLessThan(20.0)
        ->and($output)->not->toHaveKey('in_subfleet_range');
});

it('adds in_subfleet_range: true when max_range_nm is supplied and the row is within range', function (): void {
    $origin = makeAirport('KSFO', 37.6213, -122.379);
    $row = makeAirport('KOAK', 37.7, -122.5);
    $resource = new RouteForgeAirportResource($row);

    $output = $resource->toArray(rfReq(origin: $origin, maxRangeNm: 500));

    expect($output)->toHaveKey('in_subfleet_range')
        ->and($output['in_subfleet_range'])->toBeTrue();
});

it('adds in_subfleet_range: false when the row is outside max_range_nm', function (): void {
    $origin = makeAirport('KSFO', 37.6213, -122.379);
    // KLAX ~293 nm from KSFO — outside a 100 nm range.
    $row = makeAirport('KLAX', 33.9416, -118.4085);
    $resource = new RouteForgeAirportResource($row);

    $output = $resource->toArray(rfReq(origin: $origin, maxRangeNm: 100));

    expect($output['in_subfleet_range'])->toBeFalse();
});

it('omits decoration keys when the row has no lat/lon', function (): void {
    $origin = makeAirport('KSFO', 37.6213, -122.379);
    $row = makeAirport('XXXX', null, null);
    $resource = new RouteForgeAirportResource($row);

    $output = $resource->toArray(rfReq(origin: $origin, maxRangeNm: 500));

    expect($output)->not->toHaveKey('distance_from_origin_nm')
        ->and($output)->not->toHaveKey('in_subfleet_range');
});

it('omits decoration keys when the origin has no lat/lon', function (): void {
    $origin = makeAirport('KSFO', null, null);
    $row = makeAirport('KOAK', 37.7, -122.5);
    $resource = new RouteForgeAirportResource($row);

    $output = $resource->toArray(rfReq(origin: $origin, maxRangeNm: 500));

    expect($output)->not->toHaveKey('distance_from_origin_nm')
        ->and($output)->not->toHaveKey('in_subfleet_range');
});

it('does not mutate the underlying Airport model attribute bag', function (): void {
    $origin = makeAirport('KSFO', 37.6213, -122.379);
    $row = makeAirport('KOAK', 37.7, -122.5);
    $before = array_keys($row->getAttributes());

    new RouteForgeAirportResource($row)->toArray(rfReq(origin: $origin, maxRangeNm: 500));

    $after = array_keys($row->getAttributes());

    expect($after)->toBe($before)
        // Specifically: the decoration keys must not leak into the model.
        ->and($row->getAttributes())->not->toHaveKey('distance_from_origin_nm')
        ->and($row->getAttributes())->not->toHaveKey('in_subfleet_range');
});

it('rounds distance_from_origin_nm to 1 decimal', function (): void {
    $origin = makeAirport('KSFO', 37.6213, -122.379);
    $row = makeAirport('KLAX', 33.9416, -118.4085);
    $resource = new RouteForgeAirportResource($row);

    $output = $resource->toArray(rfReq(origin: $origin));

    $rendered = (string) $output['distance_from_origin_nm'];
    $decimals = str_contains($rendered, '.') ? strlen(substr($rendered, strpos($rendered, '.') + 1)) : 0;

    expect($decimals)->toBeLessThanOrEqual(1);
});
