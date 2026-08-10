<?php

declare(strict_types=1);

use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Models\Aircraft;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * StacksRelationManagers replaces Filament's relation-manager tab bar with
 * anchored sections plus a jump bar. The bar's relation-manager half is
 * derived from the resource's managers, so the interesting assertions are
 * that every manager reaches the page and that the counts are real.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();
});

it('lists the form sections and every relation manager in the jump bar', function (): void {
    $subfleet = Subfleet::factory()->create();

    Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
        ->assertSuccessful()
        // Form sections, declared by the page.
        ->assertSee(__('filament.subfleet_information'))
        ->assertSee(__('filament.subfleets.sections.operational_capability'))
        // Anchors the jump bar links to, derived from the manager class names.
        ->assertSee('id="subfleet-information"', escape: false)
        ->assertSee('id="operational-capability"', escape: false)
        ->assertSee('id="aircraft"', escape: false)
        ->assertSee('id="ranks"', escape: false)
        ->assertSee('id="typeratings"', escape: false)
        ->assertSee('id="fares"', escape: false)
        ->assertSee('id="expenses"', escape: false)
        ->assertSee('id="files"', escape: false);
});

it('counts each relation in the jump bar', function (): void {
    $subfleet = Subfleet::factory()->create();
    Aircraft::factory()->count(3)->create(['subfleet_id' => $subfleet->id]);

    $page = Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
        ->assertSuccessful();

    $links = collect(invade($page->instance())->getSectionLinks())->keyBy('id');

    expect($links['aircraft']['count'])->toBe(3)
        ->and($links['fares']['count'])->toBe(0)
        // Form sections carry no count.
        ->and($links['subfleet-information']['count'])->toBeNull();
});

it('renders relation managers stacked rather than in a tab bar', function (): void {
    $subfleet = Subfleet::factory()->create();

    Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
        ->assertSuccessful()
        // Filament's own relation-manager tab bar is gone.
        ->assertDontSee('fi-resource-relation-managers-tabs', escape: false);
});
