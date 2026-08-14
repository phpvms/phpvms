<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\CreateFlight;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * The flight's identity (airline, type, number, route code, leg) is read in the
 * edit page's overview and edited in its drawer, so those fields are
 * dropped from the edit form — but the create page has no strip and still
 * needs them inline. Losing them from both is silent: the form just renders
 * without them and creation fails on a NOT NULL column.
 */
const IDENTITY_FIELDS = ['airline_id', 'flight_type', 'flight_number', 'route_code', 'route_leg'];

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    $this->bundle = FlightBundle::factory()->create([
        'start_date' => null,
        'end_date'   => null,
    ]);
});

it('keeps the identity fields on the create form', function (): void {
    $component = Livewire::test(CreateFlight::class, ['parentRecord' => $this->bundle])
        ->assertSuccessful();

    foreach (IDENTITY_FIELDS as $field) {
        $component->assertFormFieldExists($field);
    }
});

it('drops the identity fields from the edit form', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
    ]);

    $component = Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])->assertSuccessful();

    foreach (IDENTITY_FIELDS as $field) {
        $component->assertFormFieldDoesNotExist($field);
    }
});

it('shows the identity in the overview instead', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
        'flight_type'   => FlightType::SCHED_PAX,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])
        ->assertSuccessful()
        ->assertSee($flight->airline->name)
        ->assertSee('421')
        ->assertSee($flight->flight_type->getLabel());
});

/**
 * Filling the drawer from the whole record would push `distance` — a Distance
 * value object — into Livewire state, which throws on mount.
 */
it('opens the identity drawer without serialising the whole record', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'flight_number' => 421,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])
        ->mountAction('edit', ['recordKey' => $flight->getRouteKey()])
        ->assertHasNoActionErrors()
        ->assertActionDataSet([
            'flight_number' => $flight->flight_number,
            'airline_id'    => $flight->airline_id,
        ]);
});
