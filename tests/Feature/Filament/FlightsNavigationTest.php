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
use Database\Seeders\ShieldSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);

    createAdminUser();
});

it('registers exactly one Flights navigation entry pointing to /admin/flights', function (): void {
    // FlightBundleResource is the only resource with navigation label 'Flights'.
    expect(FlightBundleResource::getNavigationLabel())
        ->toBe(__('filament.flights.navigation_label'));

    // FlightResource is nested under FlightBundleResource — no standalone nav entry.
    $parentResource = (new ReflectionClass(FlightResource::class))->getStaticPropertyValue('parentResource');
    expect($parentResource)
        ->toBe(FlightBundleResource::class);

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
