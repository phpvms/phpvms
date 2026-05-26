<?php

declare(strict_types=1);

use App\Support\Geo;

test('haversineNm returns 0 for identical endpoints', function (): void {
    expect(Geo::haversineNm(37.6, -122.4, 37.6, -122.4))->toBe(0.0);
});

test('haversineNm approximates KSFO → KLAX great-circle distance (~293 nm)', function (): void {
    // KSFO (37.6213°N, 122.3790°W) → KLAX (33.9416°N, 118.4085°W)
    $d = Geo::haversineNm(37.6213, -122.379, 33.9416, -118.4085);

    expect($d)->toBeGreaterThan(290.0)
        ->and($d)->toBeLessThan(300.0);
});

test('haversineNm approximates KSFO → EGLL long-haul (~4625 nm)', function (): void {
    // KSFO → London Heathrow (51.4775°N, 0.4614°W)
    $d = Geo::haversineNm(37.6213, -122.379, 51.4775, -0.4614);

    expect($d)->toBeGreaterThan(4600.0)
        ->and($d)->toBeLessThan(4700.0);
});

test('haversineNm is symmetric — distance(A, B) === distance(B, A)', function (): void {
    $ab = Geo::haversineNm(40.6413, -73.7781, 51.4775, -0.4614);
    $ba = Geo::haversineNm(51.4775, -0.4614, 40.6413, -73.7781);

    expect(abs($ab - $ba))->toBeLessThan(1e-10);
});

test('haversineNm matches the client-side geo.ts pinned reference value (KSFO → KORD ~1600 nm)', function (): void {
    // Same input the client-side `geo.test.ts` pins: KSFO (37.6213°N,
    // 122.3790°W) → KORD (41.9786°N, 87.9048°W). geo.ts expects 1590 < d <
    // 1610 with EARTH_RADIUS_NM = 3440.065 — assert the PHP side falls in
    // the same band. Drift here means the formulas diverged.
    $d = Geo::haversineNm(37.6213, -122.379, 41.9786, -87.9048);

    expect($d)->toBeGreaterThan(1590.0)
        ->and($d)->toBeLessThan(1610.0);
});

test('haversineNm exposes the canonical Earth radius constant', function (): void {
    expect(Geo::EARTH_RADIUS_NM)->toBe(3440.065);
});
