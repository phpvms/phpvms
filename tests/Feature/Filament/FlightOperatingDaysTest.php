<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Support\Days;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * `flights.days` is a bitmask column, but the day picker is a multi-select
 * whose state is a list of Days::* constants. The mask has to survive the trip
 * out to the form and back, in both directions, for more than one day at once.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    $this->bundle = FlightBundle::factory()->create([
        'start_date' => null,
        'end_date'   => null,
    ]);
});

it('fills the day picker from a multi-day bitmask', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
        'days'          => Days::MONDAY | Days::WEDNESDAY | Days::FRIDAY,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])
        ->assertFormSet([
            'days' => [Days::MONDAY, Days::WEDNESDAY, Days::FRIDAY],
        ]);
});

/**
 * The day checkboxes bind to the raw Livewire property, not to the resolved
 * form state. If that property holds the mask instead of a list, every box
 * renders checked while assertFormSet still reports the right days.
 */
it('exposes the days as a list on the Livewire property the checkboxes bind to', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
        'days'          => Days::MONDAY,
    ]);

    $component = Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ]);

    expect($component->get('data.days'))->toBe([Days::MONDAY]);
});

it('saves the day picker selection back to a bitmask', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
        'days'          => Days::MONDAY,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])
        ->fillForm(['days' => [Days::TUESDAY, Days::SATURDAY]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($flight->refresh()->days)->toBe(Days::TUESDAY | Days::SATURDAY);
});

it('treats an empty day picker as no days', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
        'days'          => Days::MONDAY | Days::TUESDAY,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])
        ->fillForm(['days' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($flight->refresh()->days)->toBe(0);
});
