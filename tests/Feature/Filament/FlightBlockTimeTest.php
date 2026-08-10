<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * Block time is the elapsed time between two local clocks in different
 * timezones, so it is only correct once both ends are pulled to UTC. The raw
 * clock difference is wrong by the offset between them, which is the whole
 * reason this is calculated rather than typed.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    $this->bundle = FlightBundle::factory()->create([
        'start_date' => null,
        'end_date'   => null,
    ]);
});

/** The TimePicker's raw state is a clock time, sometimes with a date ahead of it. */
function blockTimeOn(object $component): string
{
    $raw = (string) $component->get('data.flight_time');

    return str_contains($raw, ' ')
        ? substr((string) strrchr($raw, ' '), 1, 5)
        : substr($raw, 0, 5);
}

function editFlightWith(array $attributes, object $bundle): object
{
    $flight = Flight::factory()->create([
        'bundle_id'     => $bundle->id,
        'flight_number' => 421,
        ...$attributes,
    ]);

    return Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ]);
}

it('spans the timezone offset rather than the clock difference', function (): void {
    // 06:25 in New York is 10:25Z; 08:15 in Chicago is 13:15Z. The clocks are
    // 1h50 apart, the aeroplane is in the air for 2h50.
    $dpt = Airport::factory()->create(['timezone' => 'America/New_York']);
    $arr = Airport::factory()->create(['timezone' => 'America/Chicago']);

    $component = editFlightWith([
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'departure_time' => '06:25',
        'arrival_time'   => '08:15',
    ], $this->bundle)
        ->assertSuccessful()
        ->fillForm(['arrival_time' => '08:15'])
        ->assertHasNoFormErrors();

    expect(blockTimeOn($component))->toBe('02:50');
});

it('treats an arrival at or before departure as the next day', function (): void {
    $zone = 'UTC';
    $dpt = Airport::factory()->create(['timezone' => $zone]);
    $arr = Airport::factory()->create(['timezone' => $zone]);

    $component = editFlightWith([
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'departure_time' => '23:30',
        'arrival_time'   => '01:10',
    ], $this->bundle)
        ->assertSuccessful()
        ->fillForm(['arrival_time' => '01:10'])
        ->assertHasNoFormErrors();

    expect(blockTimeOn($component))->toBe('01:40');
});

it('leaves the existing block time alone when an airport has no timezone', function (): void {
    $dpt = Airport::factory()->create(['timezone' => null]);
    $arr = Airport::factory()->create(['timezone' => 'UTC']);

    $component = editFlightWith([
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'departure_time' => '06:00',
        'arrival_time'   => '09:00',
        'flight_time'    => 110,
    ], $this->bundle)
        ->assertSuccessful()
        ->fillForm(['arrival_time' => '09:00'])
        ->assertHasNoFormErrors();

    // Untouched: a required field is worse blanked than stale.
    expect(blockTimeOn($component))->toBe('01:50');
});

/**
 * Distance belongs to the pair of airports, so a leg that is re-routed keeps
 * quoting the old leg's mileage until it is recalculated.
 */
it('recalculates the distance when an airport changes', function (): void {
    $dpt = Airport::factory()->create(['timezone' => 'UTC', 'lat' => 0.0, 'lon' => 0.0]);
    $arr = Airport::factory()->create(['timezone' => 'UTC', 'lat' => 10.0, 'lon' => 0.0]);
    $diverted = Airport::factory()->create(['timezone' => 'UTC', 'lat' => 1.0, 'lon' => 0.0]);

    $component = editFlightWith([
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'distance'       => 600,
    ], $this->bundle)
        ->assertSuccessful()
        ->fillForm(['arr_airport_id' => $diverted->id])
        ->assertHasNoFormErrors();

    // A degree of latitude is a nautical mile a minute: 60 nm, not the 600 the
    // flight was carrying before it was re-routed.
    expect((int) $component->get('data.distance'))->toBe(60);
});

/**
 * The band sits directly above the airport selects it describes, so it has to
 * move in the same round trip rather than lagging until save.
 */
it('redraws the route band from live state when an airport changes', function (): void {
    $dpt = Airport::factory()->create(['icao' => 'KATL', 'timezone' => 'America/New_York']);
    $arr = Airport::factory()->create(['icao' => 'KAUS', 'timezone' => 'America/Chicago']);
    $diverted = Airport::factory()->create(['icao' => 'KLAX', 'timezone' => 'America/Los_Angeles']);

    editFlightWith([
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'departure_time' => '06:25',
        'arrival_time'   => '08:15',
    ], $this->bundle)
        ->assertSuccessful()
        ->assertSee('KAUS')
        ->fillForm(['arr_airport_id' => $diverted->id])
        ->assertSee('KLAX')
        // The old arrival is gone from the band, not merely joined by the new one.
        ->assertDontSee('Austin');
});
