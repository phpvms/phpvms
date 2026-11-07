<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\PirepsRelationManager;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\Subfleet;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * HasRelationManagerTabs puts the form in the first tab and every relation
 * manager in a tab after it; the manager tabs carry the counts.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();
});

it('badges each relation manager tab with its row count', function (): void {
    $subfleet = Subfleet::factory()->create();
    Aircraft::factory()->count(3)->create(['subfleet_id' => $subfleet->id]);

    $page = Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
        ->assertSuccessful();

    $tabs = invade($page->instance())->getTabComponents();

    // Tabs are keyed by relationship name; '' is the form tab.
    expect($tabs['aircraft']->getBadge())->toBe('3')
        ->and($tabs['fares']->getBadge())->toBe('0')
        ->and(array_key_first($tabs))->toBe('');
});

it('badges a tab with the rows its table shows, not the raw relationship', function (): void {
    $user = User::factory()->create();
    Pirep::factory()->count(2)->create(['user_id' => $user->id, 'state' => PirepState::ACCEPTED]);
    Pirep::factory()->create(['user_id' => $user->id, 'state' => PirepState::DRAFT]);
    Pirep::factory()->create(['user_id' => $user->id, 'state' => PirepState::CANCELLED]);

    $page = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful();

    $badge = invade($page->instance())->getTabComponents()['pireps']->getBadge();

    Livewire::test(PirepsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass'   => EditUser::class,
    ])->assertCountTableRecords(2);

    expect($user->pireps()->count())->toBe(4)
        ->and($badge)->toBe('2');
});

it('keys the tabs by relationship name for the query string', function (): void {
    $user = User::factory()->create();

    $page = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()]);

    expect(array_keys(invade($page->instance())->getTabComponents()))->toBe(['', 'fields', 'awards', 'typeratings', 'pireps']);
});
