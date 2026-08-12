<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\RelationManagers\FlightsRelationManager;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\CreateFlight;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    createAdminUser();
});

it('registers exactly one Flights navigation entry pointing to /admin/flights', function (): void {
    // FlightBundleResource is the only resource with navigation label 'Flights'.
    expect(FlightBundleResource::getNavigationLabel())
        ->toBe(__('filament.flights.navigation_label'));

    // FlightResource is nested under FlightBundleResource — no standalone nav entry.
    expect(FlightResource::getParentResource())
        ->toBe(FlightBundleResource::class);

    // Parent registration is the mechanism Filament uses to skip nav for nested resources
    // (see Filament\Resources\Resource\Concerns\HasNavigation::registerNavigationItems).
    expect(FlightResource::getParentResourceRegistration())
        ->not->toBeNull();

    // Slug confirms URL ends with /admin/flights.
    expect(FlightBundleResource::getSlug())
        ->toBe('flights');
});

it('renders the bundle edit page with an inline flights relation manager', function (): void {
    $bundle = FlightBundle::factory()->create();

    // Resource declares FlightsRelationManager.
    expect(FlightBundleResource::getRelations())
        ->toContain(FlightsRelationManager::class);

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->assertSuccessful();
});

it('renders the nested EditFlight page as a full-page Livewire form', function (): void {
    $bundle = FlightBundle::factory()->create();
    $flight = Flight::factory()->create(['bundle_id' => $bundle->id]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ])
        ->assertSuccessful()
        ->assertFormExists();
});

it('persists bundle_id from the parent route on create without a form selector', function (): void {
    $bundle = FlightBundle::factory()->create();

    $airline = Airline::factory()->create();
    $dpt = Airport::factory()->create();
    $arr = Airport::factory()->create();

    Livewire::test(CreateFlight::class, [
        'parentRecord' => $bundle,
    ])
        ->assertSuccessful()
        ->assertFormFieldDoesNotExist('bundle_id')
        ->fillForm([
            'airline_id'     => $airline->id,
            'flight_type'    => FlightType::SCHED_PAX->value,
            'flight_number'  => fake()->unique()->numberBetween(1000, 9999),
            'dpt_airport_id' => $dpt->id,
            'arr_airport_id' => $arr->id,
            'flight_time'    => '02:30',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Flight::query()->latest('id')->first()?->bundle_id)
        ->toBe($bundle->id);
});

it('creates the outbound flight and prepares a recalculated return flight', function (): void {
    $bundle = FlightBundle::factory()->create();
    $airline = Airline::factory()->create();
    $departure = Airport::factory()->create([
        'id'       => 'KAAA',
        'icao'     => 'KAAA',
        'timezone' => 'UTC',
        'lat'      => 0,
        'lon'      => 0,
    ]);
    $arrival = Airport::factory()->create([
        'id'       => 'KBBB',
        'icao'     => 'KBBB',
        'timezone' => 'UTC',
        'lat'      => 1,
        'lon'      => 0,
    ]);

    $component = Livewire::test(CreateFlight::class, [
        'parentRecord' => $bundle,
    ]);

    $component->assertSuccessful();
    $component->assertSee('Create Return Flight');
    $component->fillForm([
        'airline_id'     => $airline->id,
        'flight_type'    => FlightType::SCHED_PAX->value,
        'flight_number'  => 4321,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
        'departure_time' => '08:00',
        'arrival_time'   => '10:30',
        'flight_time'    => '09:59',
        'distance'       => 999,
        'route'          => 'DCT OUTBOUND',
        'notes'          => 'Copied to the return leg',
    ])
        ->call('createReturnFlight')
        ->assertHasNoFormErrors();

    $outbound = Flight::query()->where('bundle_id', $bundle->id)->sole();

    expect($outbound->dpt_airport_id)->toBe($departure->id)
        ->and($outbound->arr_airport_id)->toBe($arrival->id)
        ->and($component->get('data.dpt_airport_id'))->toBe($arrival->id)
        ->and($component->get('data.arr_airport_id'))->toBe($departure->id)
        ->and((int) $component->get('data.distance'))->toBe(60)
        ->and((string) $component->get('data.flight_time'))->toContain('02:30')
        ->and($component->get('data.route'))->toBeNull()
        ->and($component->get('data.notes'))->toBe('Copied to the return leg');
});
