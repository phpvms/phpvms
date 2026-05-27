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

/*
 * Cross-language parity:
 *
 * `tests/fixtures/routeforge/geo-haversine.json` defines a set of
 * (lat/lon, lat/lon) → expected_nm cases. The companion vitest spec
 * (`resources/js/admin/routeforge/lib/geo.test.ts`) loads the same JSON
 * and asserts the TS implementation matches `expected_nm` to within
 * `tolerance_nm`. Both implementations passing means PHP `Geo` and TS
 * `geo.ts` agree at floating-point precision — drift in either
 * direction surfaces as a fixture mismatch in its own suite.
 *
 * Add new edge cases to the JSON and both halves pick them up; no test
 * code change required.
 */
test('haversineNm matches the cross-language parity fixture', function (): void {
    /** @var array{earth_radius_nm: float, tolerance_nm: float, cases: list<array{name: string, lat_a: float, lon_a: float, lat_b: float, lon_b: float, expected_nm: float}>} $fixture */
    $fixture = json_decode(
        (string) file_get_contents(base_path('tests/fixtures/routeforge/geo-haversine.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($fixture['earth_radius_nm'])->toBe(Geo::EARTH_RADIUS_NM);

    $tolerance = $fixture['tolerance_nm'];
    foreach ($fixture['cases'] as $case) {
        $actual = Geo::haversineNm(
            $case['lat_a'],
            $case['lon_a'],
            $case['lat_b'],
            $case['lon_b'],
        );

        expect(abs($actual - $case['expected_nm']))
            ->toBeLessThanOrEqual($tolerance, sprintf(
                'haversineNm(%s) drifted from fixture: got %.12f, expected %.12f',
                $case['name'],
                $actual,
                $case['expected_nm'],
            ));
    }
});
