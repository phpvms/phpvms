<?php

declare(strict_types=1);

use App\Support\MapTrackSimplifier;

/**
 * Builds a dense, perfectly straight-line-ground-track flight: a linear climb
 * to $cruiseAltitude, a flat cruise, then a linear descent back to 0. Lat and
 * lon move identically with index, so the ground track alone gives
 * Douglas-Peucker nothing to detect — only altitude marks the phases.
 *
 * @return list<array{lat: float, lon: float, altitude: ?float, phase: ?string}>
 */
function denseClimbCruiseDescentTrack(int $pointsPerLeg = 100, float $cruiseAltitude = 35000.0): array
{
    $points = [];
    $totalPoints = $pointsPerLeg * 3;

    for ($i = 0; $i < $totalPoints; $i++) {
        $lat = ($i / ($totalPoints - 1)) * 3.0;
        $lon = ($i / ($totalPoints - 1)) * 3.0;

        if ($i < $pointsPerLeg) {
            $altitude = ($i / ($pointsPerLeg - 1)) * $cruiseAltitude;
            $phase = 'climb';
        } elseif ($i < $pointsPerLeg * 2) {
            $altitude = $cruiseAltitude;
            $phase = 'cruise';
        } else {
            $legIndex = $i - $pointsPerLeg * 2;
            $altitude = $cruiseAltitude - ($legIndex / ($pointsPerLeg - 1)) * $cruiseAltitude;
            $phase = 'descent';
        }

        $points[] = ['lat' => $lat, 'lon' => $lon, 'altitude' => $altitude, 'phase' => $phase];
    }

    return $points;
}

test('a dense track is reduced to materially fewer points', function (): void {
    $raw = denseClimbCruiseDescentTrack();
    $simplified = new MapTrackSimplifier()->simplify($raw);

    expect($simplified)->not->toBeEmpty()
        ->and(count($simplified))->toBeLessThan((int) (count($raw) / 10));
});

test('the climb, cruise, and descent altitudes survive simplification', function (): void {
    $raw = denseClimbCruiseDescentTrack(pointsPerLeg: 100, cruiseAltitude: 35000.0);
    $simplified = new MapTrackSimplifier()->simplify($raw);

    $altitudes = array_column($simplified, 'altitude');

    // The cruise plateau is not flattened away by simplification.
    expect(max($altitudes))->toBeGreaterThan(34900.0);

    // The flight starts and ends on the ground.
    expect(reset($altitudes))->toBeLessThan(100.0)
        ->and(end($altitudes))->toBeLessThan(100.0);

    // More than just the two endpoints survive: the climb-to-cruise and
    // cruise-to-descent corners are both distinct kept points, so the cruise
    // plateau leaves a trace rather than being flattened into a single
    // ground-to-ground line.
    expect(count($simplified))->toBeGreaterThanOrEqual(4);
});

test('a short track is returned as-is rather than emptied', function (): void {
    $short = [
        ['lat' => 10.0, 'lon' => 20.0, 'altitude' => 1000.0, 'phase' => 'climb'],
        ['lat' => 10.1, 'lon' => 20.1, 'altitude' => 2000.0, 'phase' => 'climb'],
    ];

    expect(new MapTrackSimplifier()->simplify($short))->toBe($short)
        ->and(new MapTrackSimplifier()->simplify([]))->toBe([])
        ->and(new MapTrackSimplifier()->simplify([$short[0]]))->toBe([$short[0]]);
});

test('a point exactly on the 3D line is dropped', function (): void {
    // Point 1 is the exact linear interpolation of point 0 and point 2 in
    // lat, lon, and altitude — zero perpendicular distance from the line.
    $points = [
        ['lat' => 0.0, 'lon' => 0.0, 'altitude' => 0.0, 'phase' => null],
        ['lat' => 1.0, 'lon' => 1.0, 'altitude' => 1000.0, 'phase' => null],
        ['lat' => 2.0, 'lon' => 2.0, 'altitude' => 2000.0, 'phase' => null],
    ];

    expect(new MapTrackSimplifier()->simplify($points))->toBe([$points[0], $points[2]]);
});

test('a point far off the 3D line is kept', function (): void {
    // Straight line start->end holds lon at 0; point 1 jumps to lon 5°,
    // roughly 556 km off that line — far beyond the default 250 m tolerance.
    $points = [
        ['lat' => 0.0, 'lon' => 0.0, 'altitude' => 0.0, 'phase' => null],
        ['lat' => 1.0, 'lon' => 5.0, 'altitude' => 0.0, 'phase' => null],
        ['lat' => 2.0, 'lon' => 0.0, 'altitude' => 0.0, 'phase' => null],
    ];

    expect(new MapTrackSimplifier()->simplify($points))->toBe($points);
});

test('the tolerance is configurable', function (): void {
    // Point 1 deviates from the start->end line by ~111 m (lon 0.001° off a
    // straight lon-0 line at the equator: deg2rad(0.001) * 6_371_000 m ≈ 111 m).
    $points = [
        ['lat' => 0.0, 'lon' => 0.0, 'altitude' => 0.0, 'phase' => null],
        ['lat' => 1.0, 'lon' => 0.001, 'altitude' => 0.0, 'phase' => null],
        ['lat' => 2.0, 'lon' => 0.0, 'altitude' => 0.0, 'phase' => null],
    ];

    // Below the ~111 m deviation: the point survives.
    expect(new MapTrackSimplifier(toleranceMeters: 50.0)->simplify($points))->toHaveCount(3);

    // Above it: the point is dropped.
    expect(new MapTrackSimplifier(toleranceMeters: 150.0)->simplify($points))->toHaveCount(2);
});

test('a missing altitude between two same-altitude points is not retained as a vertical excursion', function (): void {
    // Same ground track throughout (lat/lon advance linearly); point 10 alone
    // has no recorded altitude, flanked by points at a constant 35,000 ft. A
    // buggy `?? 0.0` default would read that as a ~10,668 m plunge to ground
    // and wrongly keep it (map-api spec, "Missing altitude is not a
    // deviation").
    $points = [];
    for ($i = 0; $i < 20; $i++) {
        $points[] = [
            'lat'      => $i * 0.01,
            'lon'      => $i * 0.01,
            'altitude' => $i === 10 ? null : 35000.0,
            'phase'    => 'cruise',
        ];
    }

    $simplified = new MapTrackSimplifier()->simplify($points);

    expect($simplified)->toHaveCount(2); // collinear ground track — only the two endpoints survive
    foreach ($simplified as $point) {
        expect($point['altitude'])->not->toBeNull();
    }
});

test('the feet-to-metres altitude conversion is pinned, not just >0', function (): void {
    // All three points share one lat/lon (a duplicate-position, degenerate
    // ground track), so only the altitude term can move the distance:
    // 500 ft * 0.3048 = 152.4 m. That must land strictly between the two
    // tolerances below — a wrong conversion constant (e.g. treating feet as
    // metres, giving 500 m) would make both assertions pass together
    // trivially, or the 250 m one fail; this pair only holds for ~152.4 m.
    $points = [
        ['lat' => 10.0, 'lon' => 20.0, 'altitude' => 0.0, 'phase' => null],
        ['lat' => 10.0, 'lon' => 20.0, 'altitude' => 500.0, 'phase' => null],
        ['lat' => 10.0, 'lon' => 20.0, 'altitude' => 0.0, 'phase' => null],
    ];

    // 152.4 m < 250 m default tolerance: dropped.
    expect(new MapTrackSimplifier()->simplify($points))->toHaveCount(2);

    // 152.4 m > 100 m tolerance: kept.
    expect(new MapTrackSimplifier(toleranceMeters: 100.0)->simplify($points))->toHaveCount(3);
});

test('an adversarial zigzag track terminates and keeps its altitude range', function (): void {
    // Every point alternates between two altitudes far enough apart that
    // (almost) every point is individually a genuine "keep" — the worst case
    // for Douglas-Peucker's O(n²) recursive behaviour, unlike the smooth
    // climb/cruise/descent fixture above. Bounded by MapTrackSimplifier's
    // internal comparison budget (map-api spec, "simplification does not
    // recurse without limit").
    //
    // Deliberately no wall-clock assertion: the run is ~0.1s locally but
    // ~2.3s on a loaded CI runner, so any threshold tight enough to catch a
    // regression was tight enough to fail at random. Termination is what this
    // asserts now — an unbounded recursion would hang the suite outright.
    $points = [];
    for ($i = 0; $i < 5000; $i++) {
        $points[] = [
            'lat'      => $i * 0.0001,
            'lon'      => $i * 0.0001,
            'altitude' => $i % 2 === 0 ? 0.0 : 40000.0,
            'phase'    => null,
        ];
    }

    $simplified = new MapTrackSimplifier()->simplify($points);

    expect($simplified)->not->toBeEmpty();

    $altitudes = array_column($simplified, 'altitude');
    expect(min($altitudes))->toEqual(0.0)
        ->and(max($altitudes))->toEqual(40000.0);
});
