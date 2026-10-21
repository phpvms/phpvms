<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The jump-bar switcher on the edit page lists sibling flights of the bundle.
 * It used to list every one of them, and resolved each flight's airline on its
 * own for the ident — a bundle with ~180k imported flights needed ~350k
 * queries and >1 GB of memory and the page died with a 500. The switcher now
 * shows a window of flights around the current flight number instead.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    $this->bundle = FlightBundle::factory()->create([
        'start_date' => null,
        'end_date'   => null,
    ]);

    $this->airline = Airline::factory()->create();

    $this->flights = collect(range(1, 80))->mapWithKeys(fn (int $number): array => [
        $number => Flight::factory()->create([
            'bundle_id'     => $this->bundle->id,
            'airline_id'    => $this->airline->id,
            'flight_number' => $number,
        ]),
    ]);
});

function switcherUrl(Flight $flight): string
{
    return '/flight/'.$flight->getRouteKey().'/edit';
}

it('lists only a window of sibling flights around the current one', function (): void {
    $current = $this->flights[40];

    $component = Livewire::test(EditFlight::class, [
        'record'       => $current->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])->assertSuccessful();

    // 25 below and 25 above the current flight number.
    foreach (range(15, 39) as $number) {
        $component->assertSee(switcherUrl($this->flights[$number]), escape: false);
    }

    foreach (range(41, 65) as $number) {
        $component->assertSee(switcherUrl($this->flights[$number]), escape: false);
    }

    $component
        ->assertDontSee(switcherUrl($this->flights[14]), escape: false)
        ->assertDontSee(switcherUrl($this->flights[66]), escape: false);
});

/**
 * Several flights in a bundle can share a flight number (other airlines, route
 * codes or legs). Ordering by flight number alone left the rows at the window
 * edge up to the query plan, so ties are broken by id.
 */
it('breaks flight number ties by id at the edge of the window', function (): void {
    $siblings = collect(range(1, 30))->map(fn (int $leg): Flight => Flight::factory()->create([
        'bundle_id'     => $this->bundle->id,
        'airline_id'    => $this->airline->id,
        'flight_number' => 39,
        'route_code'    => '',
        'route_leg'     => $leg,
    ]))->push($this->flights[39]);

    $byIdDesc = Flight::query()
        ->whereKey($siblings->pluck('id'))
        ->orderByDesc('id')
        ->pluck('id');

    $component = Livewire::test(EditFlight::class, [
        'record'       => $this->flights[40]->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])->assertSuccessful();

    foreach ($byIdDesc->take(25) as $id) {
        $component->assertSee(switcherUrl($siblings->firstWhere('id', $id)), escape: false);
    }

    foreach ($byIdDesc->skip(25) as $id) {
        $component->assertDontSee(switcherUrl($siblings->firstWhere('id', $id)), escape: false);
    }

    $component->assertDontSee(switcherUrl($this->flights[38]), escape: false);
});

it('keeps the switcher query count independent of the bundle size', function (): void {
    DB::enableQueryLog();

    Livewire::test(EditFlight::class, [
        'record'       => $this->flights[40]->getRouteKey(),
        'parentRecord' => $this->bundle,
    ])->assertSuccessful();

    $airlineLookups = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'from "airlines"')
            || str_contains($query['query'], 'from `airlines`'))
        ->count();

    // Eager-loaded once for the switcher, plus the page's own record — never
    // one lookup per listed flight.
    expect($airlineLookups)->toBeLessThan(5);
});
