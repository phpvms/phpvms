<?php

declare(strict_types=1);

use App\Enums\AircraftStatus;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Support\Metrics\Facades\Metrics;

it('registers the first-party catalog', function (): void {
    expect(Metrics::has('pilots.total'))->toBeTrue()
        ->and(Metrics::has('flights.total'))->toBeTrue()
        ->and(Metrics::has('pireps.total'))->toBeTrue()
        ->and(Metrics::has('hours.total'))->toBeTrue()
        ->and(Metrics::has('distance.total'))->toBeTrue()
        ->and(Metrics::has('aircraft.total'))->toBeTrue()
        ->and(Metrics::has('aircraft.active'))->toBeTrue();
});

it('counts pilots.total excluding both soft-deleted and UserState::DELETED users', function (): void {
    User::factory()->count(2)->create(['state' => UserState::ACTIVE]);
    User::factory()->create(['state' => UserState::DELETED]);
    User::factory()->create()->delete(); // soft-deleted

    expect(Metrics::resolve('pilots.total'))->toBe(2);
});

it('counts flights.total as enabled and visible flight legs only', function (): void {
    Flight::factory()->create(['enabled' => true, 'visible' => true]);
    Flight::factory()->create(['enabled' => false, 'visible' => true]);
    Flight::factory()->create(['enabled' => true, 'visible' => false]);

    expect(Metrics::resolve('flights.total'))->toBe(1);
});

it('counts pireps.total testing state === ACCEPTED positively, not by exclusion', function (): void {
    Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    Pirep::factory()->create(['state' => PirepState::PENDING]);
    Pirep::factory()->create(['state' => PirepState::REJECTED]);
    Pirep::factory()->create(['state' => PirepState::PAUSED]);
    Pirep::factory()->create(['state' => PirepState::DRAFT]);

    expect(Metrics::resolve('pireps.total'))->toBe(1);
});

it('sums hours.total in hours, rounded to 1 decimal, over accepted PIREPs only', function (): void {
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'flight_time' => 120]);
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'flight_time' => 180]);
    Pirep::factory()->create(['state' => PirepState::PENDING, 'flight_time' => 999]);

    // (120 + 180) minutes / 60 = 5.0 hours.
    expect(Metrics::resolve('hours.total'))->toBe(5.0);
});

it('resolves hours.total as 0.0, not null, when there are no accepted PIREPs', function (): void {
    Pirep::factory()->create(['state' => PirepState::PENDING, 'flight_time' => 999]);

    expect(Metrics::resolve('hours.total'))->toBe(0.0);
});

it('sums distance.total rounded to the nearest whole unit over accepted PIREPs only', function (): void {
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'distance' => 100.4]);
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'distance' => 200.4]);
    Pirep::factory()->create(['state' => PirepState::PENDING, 'distance' => 9999]);

    // round(100.4 + 200.4) = round(300.8) = 301.
    expect(Metrics::resolve('distance.total'))->toBe(301);
});

it('counts aircraft.total excluding soft-deleted aircraft', function (): void {
    Aircraft::factory()->count(3)->create();
    Aircraft::factory()->create()->delete();

    expect(Metrics::resolve('aircraft.total'))->toBe(3);
});

it('counts aircraft.active by the status column, not the separate state column', function (): void {
    Aircraft::factory()->count(2)->create(['status' => AircraftStatus::ACTIVE]);
    Aircraft::factory()->create(['status' => AircraftStatus::MAINTENANCE]);

    expect(Metrics::resolve('aircraft.active'))->toBe(2);
});

it('resolves the same value for two independent callers of the same metric id', function (): void {
    Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    Pirep::factory()->create(['state' => PirepState::PENDING]);

    // Two "surfaces" (e.g. a landing page tile and a Filament widget) each
    // resolving the id independently must see the same number -- correctness
    // lives in one resolver, not one per call site (design.md D7).
    $firstSurface = Metrics::resolve('pireps.total');
    $secondSurface = Metrics::resolve('pireps.total');

    expect($firstSurface)->toBe($secondSurface)->toBe(1);
});
