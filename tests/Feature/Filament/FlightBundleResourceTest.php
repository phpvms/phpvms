<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\CreateFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\ListFlightBundles;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

it('renders the list page happy path', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create(['name' => 'Test Bundle']);

    Livewire::test(ListFlightBundles::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$bundle]);
});

it('creates a new bundle and persists created_by', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();

    $this->actingAs($admin);

    Livewire::test(CreateFlightBundle::class)
        ->fillForm([
            'name'    => 'Promo Q3',
            'enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('flight_bundles', [
        'name'       => 'Promo Q3',
        'created_by' => $admin->id,
    ]);
});

it('renders the edit page happy path', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create();

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->assertSuccessful();
});

it('shows delete action for the seeded Default bundle', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();

    // The "Default" bundle is seeded by migration 2026_05_19_100002.
    $defaultBundle = FlightBundle::query()->where('name', 'Default')->first();

    expect($defaultBundle)->not->toBeNull();

    Livewire::test(ListFlightBundles::class)
        ->assertTableActionVisible('delete', $defaultBundle);
});

it('enabled toggle is editable on the seeded Default bundle edit page', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();

    $default = FlightBundle::query()->where('name', 'Default')->first();

    expect($default)->not->toBeNull();

    Livewire::test(EditFlightBundle::class, ['record' => $default->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists('enabled');
});
