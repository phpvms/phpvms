<?php

declare(strict_types=1);

require_once __DIR__.'/../Flights/Support.php';

use App\Features\Bids\Actions\PlaceBid;
use App\Features\Dashboard\Livewire\NextFlight;
use App\Models\Bid;
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
