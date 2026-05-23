<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\ShieldSeeder;
use Livewire\Livewire;

it('hides flight date pickers when bundle has dates and renders message', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create([
        'start_date' => now()->addDays(30),
        'end_date'   => now()->addDays(60),
    ]);

    $flight = Flight::factory()->create([
        'bundle_id' => $bundle->id,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ])
        ->assertFormFieldIsHidden('start_date')
        ->assertFormFieldIsHidden('end_date')
        // Message renders as HTML (anchor link), so escape=false.
        ->assertSee(
            __('filament.flights.bundle_owned_dates_message', [
                'bundle' => $bundle->name,
                'start'  => $bundle->start_date->toFormattedDateString(),
                'end'    => $bundle->end_date->toFormattedDateString(),
                'url'    => FlightBundleResource::getUrl('edit', ['record' => $bundle]),
            ]),
            escape: false,
        );
});

it('shows flight date pickers when bundle has no dates', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create([
        'start_date' => null,
        'end_date'   => null,
    ]);

    $flight = Flight::factory()->create([
        'bundle_id' => $bundle->id,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ])
        ->assertFormFieldExists('start_date')
        ->assertFormFieldExists('end_date');
});

it('escapes HTML in bundle name within bundle-owned-dates placeholder', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $bundle = FlightBundle::factory()->create([
        'name'       => '<script>alert("xss")</script>',
        'start_date' => now()->addDay(),
        'end_date'   => now()->addWeek(),
    ]);

    $flight = Flight::factory()->create([
        'bundle_id' => $bundle->id,
    ]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ])
        ->assertSuccessful()
        ->assertDontSee('<script>alert("xss")</script>', escape: false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', escape: false);
});
