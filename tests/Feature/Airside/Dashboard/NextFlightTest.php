<?php

declare(strict_types=1);

require_once __DIR__.'/../Flights/Support.php';

use App\Features\Bids\Actions\PlaceBid;
use App\Features\Dashboard\Livewire\NextFlight;
use App\Features\Themes\ThemeFields;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Pirep;
use App\Support\Units\Distance;
use Livewire\Livewire;

beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
});

it('picks the first bid in the order My bids lists them', function (): void {
    $first = airsideDispatchFixture();
    $second = airsideDispatchFixture($first['user']);

    Bid::query()->create(['user_id' => $first['user']->id, 'flight_id' => $second['flight']->id]);
    $older = Bid::query()->create(['user_id' => $first['user']->id, 'flight_id' => $first['flight']->id]);
    $older->forceFill(['created_at' => now()->subDay()])->save();

    Livewire::actingAs($first['user'])->test(NextFlight::class)
        ->assertSet('bid', fn ($bid): bool => $bid->flight->id === $first['flight']->id);
});

it('is empty without a bid', function (): void {
    $fixture = airsideDispatchFixture();

    Livewire::actingAs($fixture['user'])->test(NextFlight::class)
        ->assertSet('bid', null);
});

it('picks up a bid placed while the drawer was open', function (): void {
    $fixture = airsideDispatchFixture();

    $widget = Livewire::actingAs($fixture['user'])->test(NextFlight::class)
        ->assertSet('bid', null);

    app(PlaceBid::class)->handle($fixture['flight'], $fixture['user'], $fixture['aircraft']);

    $widget->dispatch('assignment-drawer.closed', flightId: $fixture['flight']->id, changed: true)
        ->assertSet('bid', fn ($bid): bool => $bid->flight->id === $fixture['flight']->id);
});

it('locates the pilot at the current airport, else home, else nowhere', function (): void {
    $fixture = airsideDispatchFixture();
    $user = $fixture['user'];
    $current = Airport::factory()->create();
    $home = Airport::factory()->create();

    $user->forceFill(['curr_airport_id' => $current->id, 'home_airport_id' => $home->id])->save();
    Livewire::actingAs($user)->test(NextFlight::class)
        ->assertSet('location', fn ($airport): bool => $airport->id === $current->id);

    $user->forceFill(['curr_airport_id' => null])->save();
    Livewire::actingAs($user)->test(NextFlight::class)
        ->assertSet('location', fn ($airport): bool => $airport->id === $home->id);

    $user->forceFill(['curr_airport_id' => null, 'home_airport_id' => null])->save();
    Livewire::actingAs($user)->test(NextFlight::class)
        ->assertSet('location', null);
});

it('falls back to the default map options for unknown theme values', function (): void {
    $fixture = airsideDispatchFixture();
    $fields = app(ThemeFields::class);

    $fields->set('airside', 'ready_map_scope', 'galaxy');
    $fields->set('airside', 'ready_map_style', 'neon');
    $fields->set('airside', 'ready_map_size', 'huge');
    Livewire::actingAs($fixture['user'])->test(NextFlight::class)
        ->assertSet('mapOptions', ['scope' => 'country', 'variant' => 'dotted', 'size' => 'compact']);

    $fields->set('airside', 'ready_map_scope', 'world');
    $fields->set('airside', 'ready_map_style', 'dotted');
    $fields->set('airside', 'ready_map_size', 'hero');
    Livewire::actingAs($fixture['user'])->test(NextFlight::class)
        ->assertSet('mapOptions', ['scope' => 'world', 'variant' => 'dotted', 'size' => 'hero']);
});

it('reads the last flight as route, time and distance in nautical miles', function (): void {
    $fixture = airsideDispatchFixture();
    $user = $fixture['user'];
    $pirep = Pirep::factory()->create([
        'user_id'        => $user->id,
        'dpt_airport_id' => 'KJFK',
        'arr_airport_id' => 'KBOS',
        'flight_time'    => 70,
        'distance'       => new Distance(187, 'nmi'),
    ]);
    $user->forceFill(['last_pirep_id' => $pirep->id])->save();

    Livewire::actingAs($user)->test(NextFlight::class)
        ->assertSet('lastFlight', [
            'dpt'      => 'KJFK',
            'arr'      => 'KBOS',
            'route'    => 'KJFK → KBOS',
            'time'     => '1h 10m',
            'distance' => '187 nm',
        ]);
});
