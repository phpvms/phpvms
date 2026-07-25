<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\RelationManagers\SubfleetsRelationManager;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * The relation manager is the only admin entry point for bundle-level
 * subfleets. Filament guesses the inverse relationship as the plural camel of
 * the owner model — `flightBundles` — while Subfleet defines `bundles()`, so
 * without an explicit inverseRelationship() the attach modal throws the moment
 * it opens and the feature is unusable. These tests exist to catch that.
 */
it('renders the bundle subfleets relation manager', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    $subfleet = Subfleet::factory()->create();
    $bundle->subfleets()->attach($subfleet->id);

    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$subfleet]);
});

it('opens the attach modal without throwing', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    Subfleet::factory()->count(2)->create();

    // This is the regression guard. preloadRecordSelect() builds the option list
    // the moment the modal mounts, using Filament's guessed inverse relationship.
    // Without ->inverseRelationship('bundles') it guesses `flightBundles`, which
    // Subfleet does not define, and this throws BadMethodCallException. It also
    // runs SELECT DISTINCT subfleets.*, which fails on PostgreSQL if any subfleet
    // column is `json` rather than `jsonb`.
    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->mountAction(TestAction::make('attach')->table())
        ->assertHasNoActionErrors()
        ->assertSuccessful();
});

it('opens the bundle subfleets attach modal when a subfleet has no airline', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    Subfleet::factory()->create(['airline_id' => null]);
    Subfleet::factory()->create();

    // subfleets.airline_id is nullable but the factory always fills it, so
    // nothing else covers this. recordTitle() runs over every option, so an
    // unguarded $record->airline->name kills the whole modal — not just the
    // airline-less row — which is why a normal subfleet is seeded alongside.
    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->mountAction(TestAction::make('attach')->table())
        ->assertHasNoActionErrors()
        ->assertSuccessful();
});

it('attaches a subfleet to a bundle', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    $subfleet = Subfleet::factory()->create();

    // The relation itself, exercised the way the action ultimately does it.
    $bundle->subfleets()->attach($subfleet->id);

    expect($bundle->fresh()->subfleets->pluck('id')->all())->toBe([$subfleet->id]);
});

it('can detach a subfleet from a bundle through the relation manager', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    $subfleet = Subfleet::factory()->create();
    $bundle->subfleets()->attach($subfleet->id);

    Livewire::test(SubfleetsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->callAction(TestAction::make('detach')->table($subfleet))
        ->assertHasNoActionErrors();

    expect($bundle->fresh()->subfleets)->toBeEmpty();
});
