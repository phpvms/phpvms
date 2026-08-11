<?php

declare(strict_types=1);

use App\Filament\Resources\Subfleets\Pages\CreateSubfleet;
use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\CreateAircraft;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\EditAircraft;
use App\Models\Aircraft;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * The subfleet's and aircraft's identity is read in the edit page's overview
 * and edited in its drawer, so those fields are dropped from the edit form —
 * but the create pages have no overview and still need them inline. Losing
 * them from both is silent: the form just renders without them and creation
 * fails on a NOT NULL column.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();
});

it('keeps the subfleet identity fields on the create form', function (): void {
    $component = Livewire::test(CreateSubfleet::class)->assertSuccessful();

    foreach (['airline_id', 'type', 'name'] as $field) {
        $component->assertFormFieldExists($field);
    }
});

it('drops the subfleet identity fields from the edit form', function (): void {
    $subfleet = Subfleet::factory()->create();

    $component = Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
        ->assertSuccessful();

    foreach (['airline_id', 'type', 'name'] as $field) {
        $component->assertFormFieldDoesNotExist($field);
    }

    $component->assertSee($subfleet->type);
});

it('keeps the aircraft identity fields on the create form', function (): void {
    $subfleet = Subfleet::factory()->create();

    $component = Livewire::test(CreateAircraft::class, ['parentRecord' => $subfleet])
        ->assertSuccessful();

    foreach (['name', 'registration', 'icao', 'status'] as $field) {
        $component->assertFormFieldExists($field);
    }
});

it('drops the aircraft identity fields from the edit form', function (): void {
    $aircraft = Aircraft::factory()->create();

    $component = Livewire::test(EditAircraft::class, [
        'record'       => $aircraft->getRouteKey(),
        'parentRecord' => $aircraft->subfleet,
    ])->assertSuccessful();

    foreach (['name', 'registration', 'icao', 'status'] as $field) {
        $component->assertFormFieldDoesNotExist($field);
    }

    $component->assertSee($aircraft->registration);
});

/**
 * Filling the drawer from the whole record would push the weights — Mass value
 * objects — into Livewire state, which throws on mount.
 */
it('opens the aircraft identity drawer without serialising the whole record', function (): void {
    $aircraft = Aircraft::factory()->create();

    Livewire::test(EditAircraft::class, [
        'record'       => $aircraft->getRouteKey(),
        'parentRecord' => $aircraft->subfleet,
    ])
        ->mountAction('edit', ['recordKey' => $aircraft->getRouteKey()])
        ->assertHasNoActionErrors()
        ->assertActionDataSet([
            'registration' => $aircraft->registration,
            'name'         => $aircraft->name,
        ]);
});
