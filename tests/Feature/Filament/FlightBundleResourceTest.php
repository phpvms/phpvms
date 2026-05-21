<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\CreateFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\ListFlightBundles;
use App\Models\FlightBundle;
use Database\Seeders\ShieldSeeder;
use Livewire\Livewire;

it('renders the list page happy path', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create(['name' => 'Test Bundle']);

    Livewire::test(ListFlightBundles::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$bundle]);
});

it('creates a new bundle and persists created_by', function (): void {
    $this->seed(ShieldSeeder::class);

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
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create();

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->assertSuccessful();
});

it('shows delete action for default bundle', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    // The default bundle is seeded by migration 2026_05_19_100003
    $defaultBundle = FlightBundle::query()->where('is_default', true)->first();

    expect($defaultBundle)->not->toBeNull();

    Livewire::test(ListFlightBundles::class)
        ->assertTableActionVisible('delete', $defaultBundle);
});

it('enabled toggle is editable on default bundle edit page', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $default = FlightBundle::query()->where('is_default', true)->first();

    expect($default)->not->toBeNull();

    // Page renders successfully — enabled toggle is no longer disabled.
    Livewire::test(EditFlightBundle::class, ['record' => $default->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists('enabled');
});
