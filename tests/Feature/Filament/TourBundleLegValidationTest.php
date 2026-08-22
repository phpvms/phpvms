<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\CreateFlight;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * A tour is bid as an ordered chain, so its flights have to carry route_leg
 * 1..N with no gaps and no repeats. The flight form is where that is enforced;
 * a `flights` bundle keeps route_leg as free-form as it has always been.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    $this->tour = FlightBundle::factory()->create([
        'type'       => BundleType::Tour,
        'start_date' => null,
        'end_date'   => null,
    ]);
});

/**
 * Everything the flight form needs except the leg, so each test only has to
 * say what it is actually testing.
 *
 * @return array<string, mixed>
 */
function tourLegFormData(int|string|null $routeLeg, int $flightNumber = 900): array
{
    return [
        'airline_id'     => Airline::factory()->create()->id,
        'flight_type'    => 'J',
        'flight_number'  => $flightNumber,
        'route_leg'      => $routeLeg,
        'dpt_airport_id' => Airport::factory()->create()->id,
        'arr_airport_id' => Airport::factory()->create()->id,
        'flight_time'    => '01:30',
    ];
}

it('rejects a flight saved into a tour bundle without a leg', function (): void {
    Livewire::test(CreateFlight::class, ['parentRecord' => $this->tour])
        ->fillForm(tourLegFormData(null))
        ->call('create')
        ->assertHasFormErrors(['route_leg' => 'required']);

    expect($this->tour->flights()->count())->toBe(0);
});

it('rejects a flight whose leg is already taken in the same tour', function (): void {
    Flight::factory()->create([
        'bundle_id' => $this->tour->id,
        'route_leg' => 3,
    ]);

    Livewire::test(CreateFlight::class, ['parentRecord' => $this->tour])
        ->fillForm(tourLegFormData(3))
        ->call('create')
        ->assertHasFormErrors(['route_leg' => 'unique']);

    expect($this->tour->flights()->count())->toBe(1);
});

it('accepts a flight with a free leg in a tour bundle', function (): void {
    Flight::factory()->create([
        'bundle_id' => $this->tour->id,
        'route_leg' => 1,
    ]);

    Livewire::test(CreateFlight::class, ['parentRecord' => $this->tour])
        ->fillForm(tourLegFormData(2))
        ->call('create')
        ->assertHasNoFormErrors();

    expect($this->tour->flights()->count())->toBe(2);
});

/**
 * The uniqueness rule ignores the record being edited, or saving a flight
 * without touching its leg would collide with itself.
 */
it('lets a tour flight keep its own leg when edited', function (): void {
    // The drawer is prefilled from the record, and flight_number is capped at
    // four digits on the form -- the factory does not know that.
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->tour->id,
        'flight_number' => 421,
        'route_leg'     => 2,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->tour,
    ])
        ->mountAction('edit', ['recordKey' => $flight->getRouteKey()])
        ->setActionData(['route_leg' => 2])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($flight->fresh()->route_leg)->toBe(2);
});

it('leaves route_leg optional and repeatable on a flights bundle', function (): void {
    $bundle = FlightBundle::factory()->create([
        'type'       => BundleType::Flights,
        'start_date' => null,
        'end_date'   => null,
    ]);

    Flight::factory()->create(['bundle_id' => $bundle->id, 'route_leg' => 3]);

    Livewire::test(CreateFlight::class, ['parentRecord' => $bundle])
        ->fillForm(tourLegFormData(3))
        ->call('create')
        ->assertHasNoFormErrors();

    expect($bundle->flights()->count())->toBe(2);
});

it('warns on the bundle page when a tour has a gap in its legs', function (): void {
    foreach ([1, 2, 4] as $leg) {
        Flight::factory()->create(['bundle_id' => $this->tour->id, 'route_leg' => $leg]);
    }

    Livewire::test(EditFlightBundle::class, ['record' => $this->tour->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('filament.bundles.tour_legs.missing', ['leg' => 3, 'count' => 3]));
});

it('warns on the bundle page when a tour repeats a leg', function (): void {
    foreach ([1, 2, 2] as $leg) {
        Flight::factory()->create(['bundle_id' => $this->tour->id, 'route_leg' => $leg]);
    }

    Livewire::test(EditFlightBundle::class, ['record' => $this->tour->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('filament.bundles.tour_legs.duplicate', ['leg' => 2]));
});

it('says nothing on a tour whose legs are contiguous', function (): void {
    foreach ([1, 2, 3] as $leg) {
        Flight::factory()->create(['bundle_id' => $this->tour->id, 'route_leg' => $leg]);
    }

    Livewire::test(EditFlightBundle::class, ['record' => $this->tour->getRouteKey()])
        ->assertSuccessful()
        ->assertDontSee(__('filament.bundles.tour_legs.empty'))
        ->assertDontSee(__('filament.bundles.tour_legs.missing', ['leg' => 1, 'count' => 3]));
});

/**
 * The type select is `live()` so switching a bundle to tour raises the leg
 * warning in the drawer straight away. Without that, the admin has to save and
 * come back to find out the tour cannot be bid.
 *
 * Asserted against the schema rather than the rendered HTML: Livewire's test
 * HTML no longer carries the modal body after a `set()` round-trip, so an
 * assertSee here would fail whether or not the reveal worked.
 */
it('reveals the leg warning in the drawer as soon as the type is switched to tour', function (): void {
    $bundle = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    Flight::factory()->create(['bundle_id' => $bundle->id, 'route_leg' => null]);

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->mountAction('edit')
        ->assertSchemaComponentHidden('tour_leg_warning')
        ->setActionData(['type' => BundleType::Tour->value])
        ->assertSchemaComponentVisible('tour_leg_warning');
});

it('saves the type chosen in the drawer', function (): void {
    $bundle = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->callAction('edit', ['type' => BundleType::Tour->value])
        ->assertHasNoActionErrors();

    expect($bundle->fresh()->type)->toBe(BundleType::Tour);
});

/**
 * The same bundle typed `flights` has no leg sequence to be wrong about, so
 * the identical flights must raise nothing.
 */
it('says nothing about legs on a flights bundle', function (): void {
    $bundle = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    foreach ([1, 2, 4] as $leg) {
        Flight::factory()->create(['bundle_id' => $bundle->id, 'route_leg' => $leg]);
    }

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->assertSuccessful()
        ->assertDontSee(__('filament.bundles.tour_legs.missing', ['leg' => 3, 'count' => 3]));
});
