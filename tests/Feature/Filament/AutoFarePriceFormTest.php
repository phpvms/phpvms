<?php

declare(strict_types=1);

use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Filament\Resources\Fares\Pages\EditFare;
use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Models\Airline;
use App\Models\Fare;
use App\Models\Subfleet;
use App\Services\FareService;
use Database\Seeders\ShieldSeeder;
use Livewire\Livewire;

it('shows and persists the auto-price fields on the fare form', function (): void {
    $this->seed(ShieldSeeder::class);

    createAdminUser();
    $fare = Fare::factory()->create();

    Livewire::test(EditFare::class, ['record' => $fare->id])
        ->assertSuccessful()
        ->assertSee('base_price')
        ->assertSee('per_nm')
        ->assertSee('multiplier')
        ->set('data.base_price', 15)
        ->set('data.per_nm', 0.2)
        ->set('data.multiplier', 3)
        ->call('save')
        ->assertHasNoFormErrors();

    $fare->refresh();

    expect((float) $fare->base_price)->toEqual(15.0)
        ->and((float) $fare->per_nm)->toEqual(0.2)
        ->and((float) $fare->multiplier)->toEqual(3.0);
});

it('shows and persists the low-cost flag on the airline form', function (): void {
    $this->seed(ShieldSeeder::class);

    createAdminUser();
    // Valid icao/iata/country so the form passes its own validation on save.
    $airline = Airline::factory()->create([
        'icao'     => 'ABC',
        'iata'     => 'AB',
        'country'  => 'us',
        'low_cost' => false,
    ]);

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->assertSuccessful()
        ->assertSee('low_cost')
        ->set('data.low_cost', true)
        ->call('save')
        ->assertHasNoFormErrors();

    $airline->refresh();

    expect($airline->low_cost)->toBeTrue();
});

it('hides the auto-price override columns by default when auto pricing is disabled', function (): void {
    $this->seed(ShieldSeeder::class);
    updateSetting('fares.auto_price', false);

    createAdminUser();
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create();
    app(FareService::class)->setForSubfleet($subfleet, $fare);

    Livewire::test(FaresRelationManager::class, [
        'ownerRecord' => $subfleet,
        'pageClass'   => EditSubfleet::class,
    ])
        ->assertSuccessful()
        ->assertCanNotRenderTableColumn('pivot.base_price')
        ->assertCanNotRenderTableColumn('pivot.per_nm')
        ->assertCanNotRenderTableColumn('pivot.multiplier')
        ->assertCanRenderTableColumn('pivot.price');
});

it('shows the auto-price override columns by default when auto pricing is enabled', function (): void {
    $this->seed(ShieldSeeder::class);
    updateSetting('fares.auto_price', true);

    createAdminUser();
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create();
    app(FareService::class)->setForSubfleet($subfleet, $fare);

    Livewire::test(FaresRelationManager::class, [
        'ownerRecord' => $subfleet,
        'pageClass'   => EditSubfleet::class,
    ])
        ->assertSuccessful()
        ->assertCanRenderTableColumn('pivot.base_price')
        ->assertCanRenderTableColumn('pivot.per_nm')
        ->assertCanRenderTableColumn('pivot.multiplier')
        ->assertCanNotRenderTableColumn('pivot.price');
});
