<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\TypeRatingsRelationManager;
use App\Models\Typerating;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('renders the type ratings a user holds', function (): void {
    $user = User::factory()->create();
    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    $user->typeratings()->attach($typerating->id);

    Livewire::test(TypeRatingsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass'   => EditUser::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$typerating]);
});

/**
 * The assignment path itself. While this manager delegated its table to the
 * standalone resource's `TyperatingsTable`, it had no attach action at all, so
 * a type rating could not be granted through the admin panel by any means.
 */
it('can attach a type rating to a user through the relation manager', function (): void {
    $user = User::factory()->create();
    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);

    Livewire::test(TypeRatingsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass'   => EditUser::class,
    ])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => $typerating->id])
        ->assertHasNoActionErrors();

    expect($user->fresh()->typeratings->pluck('id')->all())->toBe([$typerating->id]);
});

it('can detach a type rating from a user through the relation manager', function (): void {
    $user = User::factory()->create();
    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    $user->typeratings()->attach($typerating->id);

    Livewire::test(TypeRatingsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass'   => EditUser::class,
    ])
        ->callAction(TestAction::make('detach')->table($typerating))
        ->assertHasNoActionErrors();

    expect($user->fresh()->typeratings)->toBeEmpty();
    $this->assertModelExists($typerating);
});

/**
 * The shared `TyperatingsTable` carries edit and delete actions that operate on
 * the `Typerating` record itself. Offered here they read as "remove this rating
 * from this user" but delete it for the whole airline, so this manager must not
 * inherit them.
 */
it('does not offer actions that alter the type rating itself', function (): void {
    $user = User::factory()->create();
    $typerating = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    $user->typeratings()->attach($typerating->id);

    Livewire::test(TypeRatingsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass'   => EditUser::class,
    ])
        ->assertActionDoesNotExist(TestAction::make('delete')->table($typerating))
        ->assertActionDoesNotExist(TestAction::make('edit')->table($typerating));
});
