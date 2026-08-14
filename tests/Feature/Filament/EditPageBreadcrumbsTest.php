<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\EditAircraft;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * Filament's own chain for the nested aircraft page reads "Subfleets ›
 * Subfleet › Aircraft › N852AL › Edit": the parent crumb falls back to the
 * model label, the nested resource adds a crumb linking to an index page it
 * does not have, and the trailing page label repeats the heading.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();
});

// Type as well as name: a B738 and a B738-WL are both "Boeing 737-800", so the
// name alone does not identify which subfleet the chain is pointing at.
it('names the subfleet in its own breadcrumb chain', function (): void {
    $subfleet = Subfleet::factory()->create(['type' => 'B738-WL', 'name' => 'Boeing 737-800']);

    $crumbs = array_values(Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
        ->assertSuccessful()
        ->instance()
        ->getBreadcrumbs());

    expect($crumbs)->toBe(['Subfleets', 'B738-WL - Boeing 737-800']);
});

it('names the subfleet and the aircraft in the nested chain', function (): void {
    $subfleet = Subfleet::factory()->create(['type' => 'B738-WL', 'name' => 'Boeing 737-800']);
    $aircraft = Aircraft::factory()->create([
        'subfleet_id'  => $subfleet->id,
        'registration' => 'N852AL',
        'name'         => 'Ship 852',
    ]);

    $crumbs = array_values(Livewire::test(EditAircraft::class, [
        'record'       => $aircraft->getRouteKey(),
        'parentRecord' => $subfleet,
    ])->assertSuccessful()->instance()->getBreadcrumbs());

    expect($crumbs)->toBe(['Subfleets', 'B738-WL - Boeing 737-800', 'N852AL - Ship 852']);
});

it('names the bundle in its own breadcrumb chain', function (): void {
    $bundle = FlightBundle::factory()->create([
        'name'       => 'Summer schedule',
        'start_date' => null,
        'end_date'   => null,
    ]);

    $crumbs = array_values(Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->assertSuccessful()
        ->instance()
        ->getBreadcrumbs());

    expect($crumbs)->toBe(['Flight Bundles', 'Summer schedule']);
});

it('names the bundle and the flight in the nested chain', function (): void {
    $bundle = FlightBundle::factory()->create([
        'name'       => 'Summer schedule',
        'start_date' => null,
        'end_date'   => null,
    ]);
    $flight = Flight::factory()->create(['bundle_id' => $bundle->id]);

    $crumbs = array_values(Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ])->assertSuccessful()->instance()->getBreadcrumbs());

    expect($crumbs)->toBe([
        'Flight Bundles',
        'Summer schedule',
        sprintf('%s - %s → %s', $flight->ident, $flight->dpt_airport->icao, $flight->arr_airport->icao),
    ]);
});
