<?php

declare(strict_types=1);

use App\Exceptions\AirportNotFound;
use App\Exceptions\DuplicateFlight;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\AirportService;
use App\Services\FlightService;
use App\Services\PirepService;
use Illuminate\Support\Facades\Http;

/**
 * An airport id is becoming a generated value rather than a copy of the ICAO,
 * and the catalog at `phpvms.api_url` already hands one back: a lookup of
 * `KJFK` answers with `{"id": "dRFXpqlxMeoq", "icao": "KJFK", ...}`. So a row
 * added from a lookup lands under that id, and anything that holds a reference
 * to it has to carry the id the lookup resolved to rather than the code it
 * asked about.
 */

/**
 * Fake the catalog for the ICAOs given, keyed ICAO => catalog id. Faked rather
 * than reached: `general.auto_airport_lookup` is off for every test
 * (TestCase::setUp) precisely so no test depends on the network.
 */
function fakeAirportCatalog(array $ids): void
{
    updateSetting('general.auto_airport_lookup', true);

    Http::fake(function ($request) use ($ids) {
        $icao = strtoupper(basename(parse_url((string) $request->url(), PHP_URL_PATH)));

        if (!array_key_exists($icao, $ids)) {
            return Http::response([], 404);
        }

        return Http::response([
            'id'        => $ids[$icao],
            'icao'      => $icao,
            'iata'      => substr($icao, 1, 3),
            'name'      => $icao.' International',
            'city'      => 'Somewhere',
            'country'   => 'US',
            'region'    => 'US-XX',
            'tz'        => 'America/Chicago',
            'elevation' => 500,
            'lat'       => 41.9,
            'lon'       => -87.9,
        ]);
    });
}

it('stores an airport under the id the catalog returns', function (): void {
    fakeAirportCatalog(['KORD' => 'dRFXpqlxMeoq']);

    $airport = app(AirportService::class)->lookupAirportIfNotFound('KORD');

    expect($airport->id)->toBe('dRFXpqlxMeoq')
        ->and($airport->icao)->toBe('KORD');

    $this->assertDatabaseHas('airports', ['id' => 'dRFXpqlxMeoq', 'icao' => 'KORD']);
});

/**
 * The ICAO fallback in `findAirport()`. Without it a caller naming `KORD`
 * would miss the row stored under the generated id and add a second airport
 * for the same place.
 */
it('resolves an icao to an airport already stored under a generated id', function (): void {
    Airport::factory()->create(['id' => 'dRFXpqlxMeoq', 'icao' => 'KORD']);

    fakeAirportCatalog(['KORD' => 'dRFXpqlxMeoq']);

    $airport = app(AirportService::class)->lookupAirportIfNotFound('KORD');

    expect($airport->id)->toBe('dRFXpqlxMeoq')
        ->and(Airport::count())->toBe(1);

    Http::assertNothingSent();
});

/**
 * `FlightService::createFlight` has no caller in the repo today -- it is the
 * service-level entry a module or a future controller would use -- so this is
 * also the only exercise it gets.
 */
it('points a new flight at the id the lookup resolved to', function (): void {
    fakeAirportCatalog(['KORD' => 'dRFXpqlxMeoq', 'KAUS' => 'Wm4kZ2tQ8bYn']);

    $airline = Airline::factory()->create();

    $flight = app(FlightService::class)->createFlight([
        'airline_id' => $airline->id,
        // `createFlight` does not fill `bundle_id`, which the column requires.
        'bundle_id'      => FlightBundle::factory()->create()->id,
        'flight_number'  => 100,
        'dpt_airport_id' => 'KORD',
        'arr_airport_id' => 'KAUS',
        'hours'          => 2,
        'minutes'        => 0,
        'active'         => true,
    ]);

    // The flight asked for two ICAOs and has to end up holding the two ids,
    // or its airport relations resolve to nothing.
    expect($flight->dpt_airport_id)->toBe('dRFXpqlxMeoq')
        ->and($flight->arr_airport_id)->toBe('Wm4kZ2tQ8bYn')
        ->and($flight->dpt_airport->icao)->toBe('KORD')
        ->and($flight->arr_airport->icao)->toBe('KAUS');
});

it('points a prefiled pirep at the id the lookup resolved to', function (): void {
    updateSetting('general.allow_unadded_airports', true);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    fakeAirportCatalog(['KORD' => 'dRFXpqlxMeoq', 'KAUS' => 'Wm4kZ2tQ8bYn']);

    $user = User::factory()->create();
    $aircraft = Aircraft::factory()->create();

    $pirep = app(PirepService::class)->prefile($user, [
        'airline_id'     => $aircraft->subfleet->airline_id ?? Airline::factory()->create()->id,
        'aircraft_id'    => $aircraft->id,
        'dpt_airport_id' => 'KORD',
        'arr_airport_id' => 'KAUS',
        'flight_time'    => 120,
        'source_name'    => 'phpunit',
    ]);

    expect($pirep->dpt_airport_id)->toBe('dRFXpqlxMeoq')
        ->and($pirep->arr_airport_id)->toBe('Wm4kZ2tQ8bYn');
});

/**
 * `AircraftObserver::creating()` used to upper-case this. An aircraft's
 * `airport_id` holds an airport id, and an id is stored as it was given.
 */
it('trims an aircraft airport id instead of folding its case', function (): void {
    $airport = Airport::factory()->create(['id' => 'dRFXpqlxMeoq', 'icao' => 'KORD']);

    $aircraft = Aircraft::factory()->create(['airport_id' => ' '.$airport->id.' ']);

    expect($aircraft->airport_id)->toBe('dRFXpqlxMeoq')
        ->and($aircraft->airport->icao)->toBe('KORD');
});

/**
 * `prefile()` with `general.allow_unadded_airports` off only validates that
 * the airports are already here -- it is not allowed to add one. It still has
 * to accept the same id-or-ICAO value the adding branch accepts, or an airport
 * the catalog stored under a generated id becomes unreachable by its own ICAO.
 */
it('prefiles a pirep against an icao when unadded airports are not allowed', function (): void {
    updateSetting('general.allow_unadded_airports', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    Airport::factory()->create(['id' => 'dRFXpqlxMeoq', 'icao' => 'KORD']);
    Airport::factory()->create(['id' => 'Wm4kZ2tQ8bYn', 'icao' => 'KAUS']);

    $user = User::factory()->create();
    $aircraft = Aircraft::factory()->create();

    $pirep = app(PirepService::class)->prefile($user, [
        'airline_id'     => $aircraft->subfleet->airline_id ?? Airline::factory()->create()->id,
        'aircraft_id'    => $aircraft->id,
        'dpt_airport_id' => 'KORD',
        'arr_airport_id' => 'KAUS',
        'flight_time'    => 120,
        'source_name'    => 'phpunit',
    ]);

    // Resolved, not just accepted: the PIREP has to hold the ids, or both
    // airport relations come back null.
    expect($pirep->dpt_airport_id)->toBe('dRFXpqlxMeoq')
        ->and($pirep->arr_airport_id)->toBe('Wm4kZ2tQ8bYn')
        ->and($pirep->dpt_airport->icao)->toBe('KORD')
        ->and($pirep->arr_airport->icao)->toBe('KAUS');
});

/**
 * With `general.auto_airport_lookup` off the lookup invents a generic airport
 * named after the value it was given. `airports.icao` is varchar(8) and an id
 * now holds 16, so a value too wide to be an ICAO has no ICAO to be given and
 * the row cannot be written honestly.
 */
it('refuses to invent an airport for an id too wide to be an icao', function (): void {
    expect(setting('general.auto_airport_lookup'))->toBeFalsy();

    $airport = app(AirportService::class)->lookupAirportIfNotFound('V1a2B3c4D5e6F7g8');

    expect($airport)->toBeNull()
        ->and(Airport::count())->toBe(0);
});

it('still invents a generic airport for a value that fits the icao column', function (): void {
    expect(setting('general.auto_airport_lookup'))->toBeFalsy();

    $airport = app(AirportService::class)->lookupAirportIfNotFound('KXYZ');

    expect($airport)->not->toBeNull()
        ->and($airport->id)->toBe('KXYZ')
        ->and($airport->icao)->toBe('KXYZ');
});

/**
 * The duplicate check has to run on the resolved ids. A caller naming two
 * ICAOs is describing the same flight as an existing row that holds the
 * generated ids those ICAOs resolve to, and `isFlightDuplicate()` compares
 * `dpt_airport_id` / `arr_airport_id` directly.
 */
it('detects a duplicate flight named by icao', function (): void {
    $dpt = Airport::factory()->create(['id' => 'dRFXpqlxMeoq', 'icao' => 'KORD']);
    $arr = Airport::factory()->create(['id' => 'Wm4kZ2tQ8bYn', 'icao' => 'KAUS']);
    $airline = Airline::factory()->create();

    Flight::factory()->create([
        'airline_id'     => $airline->id,
        'flight_number'  => 100,
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'route_code'     => null,
        'route_leg'      => null,
        'days'           => 0,
    ]);

    app(FlightService::class)->createFlight([
        'airline_id'     => $airline->id,
        'bundle_id'      => FlightBundle::factory()->create()->id,
        'flight_number'  => 100,
        'dpt_airport_id' => 'KORD',
        'arr_airport_id' => 'KAUS',
        'hours'          => 2,
        'minutes'        => 0,
        'active'         => true,
    ]);
})->throws(DuplicateFlight::class);

/**
 * `updateFlight()` resolves too. An update that names an ICAO would otherwise
 * store it verbatim and leave the relation pointing at nothing.
 */
it('points an updated flight at the id the lookup resolved to', function (): void {
    Airport::factory()->create(['id' => 'dRFXpqlxMeoq', 'icao' => 'KORD']);
    $flight = Flight::factory()->create();

    $updated = app(FlightService::class)->updateFlight($flight, [
        'dpt_airport_id' => 'KORD',
        // `transformFlightFields` reads all of these unconditionally, so
        // `updateFlight` has never taken a partial field set.
        'arr_airport_id' => $flight->arr_airport_id,
        'hours'          => 2,
        'minutes'        => 0,
        'active'         => true,
    ]);

    expect($updated->dpt_airport_id)->toBe('dRFXpqlxMeoq')
        ->and($updated->dpt_airport->icao)->toBe('KORD');
});

/**
 * The permissive prefile branch is allowed to *add* an airport, not to accept
 * one it could not add. There is no foreign key on `pireps.dpt_airport_id`, so
 * keeping the submitted value would save a PIREP whose airport relation is
 * null for the rest of its life.
 */
it('refuses to prefile a pirep against an airport it cannot add', function (): void {
    updateSetting('general.allow_unadded_airports', true);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    expect(setting('general.auto_airport_lookup'))->toBeFalsy();

    $user = User::factory()->create();
    $aircraft = Aircraft::factory()->create();

    app(PirepService::class)->prefile($user, [
        'airline_id'     => $aircraft->subfleet->airline_id ?? Airline::factory()->create()->id,
        'aircraft_id'    => $aircraft->id,
        'dpt_airport_id' => 'V1a2B3c4D5e6F7g8',
        'arr_airport_id' => 'KAUS',
        'flight_time'    => 120,
        'source_name'    => 'phpunit',
    ]);
})->throws(AirportNotFound::class);
