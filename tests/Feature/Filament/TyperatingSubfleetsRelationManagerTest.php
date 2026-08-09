<?php

declare(strict_types=1);

use App\Filament\Resources\Typeratings\Pages\EditTyperating;
use App\Filament\Resources\Typeratings\RelationManagers\SubfleetsRelationManager;
use App\Models\Subfleet;
use App\Models\Typerating;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Filament guesses the inverse relationship as the plural camel of the owner
 * model — `typeratings` — which is what Subfleet happens to define. The manager
 * names it explicitly so a rename on Subfleet breaks here rather than in the
 * attach modal at runtime. These tests hold that line.
 */
it('renders the typerating subfleets relation manager', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    $subfleet = Subfleet::factory()->create();
    $typerating->subfleets()->attach($subfleet->id);

    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $typerating,
        'pageClass'   => EditTyperating::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$subfleet]);
});

it('opens the typerating subfleets attach modal without throwing', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    Subfleet::factory()->count(2)->create();

    // Regression guard: preloadRecordSelect() builds the option list the moment
    // the modal mounts, resolving the inverse relationship on Subfleet. A wrong
    // name throws BadMethodCallException here and nowhere earlier.
    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $typerating,
        'pageClass'   => EditTyperating::class,
    ])
        ->mountAction(TestAction::make('attach')->table())
        ->assertHasNoActionErrors()
        ->assertSuccessful();
});

it('opens the typerating subfleets attach modal when a subfleet has no airline', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    Subfleet::factory()->create(['airline_id' => null]);
    Subfleet::factory()->create();

    // subfleets.airline_id is nullable but the factory always fills it, so
    // nothing else covers this. recordTitle() runs over every option, so an
    // unguarded $record->airline->name kills the whole modal — not just the
    // airline-less row — which is why a normal subfleet is seeded alongside.
    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $typerating,
        'pageClass'   => EditTyperating::class,
    ])
        ->mountAction(TestAction::make('attach')->table())
        ->assertHasNoActionErrors()
        ->assertSuccessful();
});

it('can detach a subfleet from a typerating through the relation manager', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    $subfleet = Subfleet::factory()->create();
    $typerating->subfleets()->attach($subfleet->id);

    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $typerating,
        'pageClass'   => EditTyperating::class,
    ])
        ->callAction(TestAction::make('detach')->table($subfleet))
        ->assertHasNoActionErrors();

    expect($typerating->fresh()->subfleets)->toBeEmpty();
});
