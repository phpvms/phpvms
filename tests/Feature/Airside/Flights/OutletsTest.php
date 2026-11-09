<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Bids\Livewire\AssignmentDrawer;
use App\Features\Bids\Livewire\BidList;
use App\Features\Flights\Livewire\FlightActions;
use App\Features\Flights\Livewire\FlightManifest;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingBidDrawerActions;
use App\Features\Hooks\Points\RenderingBidRowActions;
use App\Features\Hooks\Points\RenderingFlightActions;
use App\Features\Hooks\Points\RenderingFlightRowActions;
use App\Models\Bid;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

/**
 * The addon render hooks the flights and bids pages carry.
 */
final class FlightsProbeRow
{
    public function handle(RenderingFlightRowActions $hook): void
    {
        $hook->add(Fill::view('flightsprobe::probe', ['flight' => $hook->flight, 'policy' => $hook->policy]));
    }
}

final class FlightsProbeDetail
{
    public function handle(RenderingFlightActions $hook): void
    {
        $hook->add(Fill::view('flightsprobe::probe', ['flight' => $hook->flight, 'policy' => $hook->policy]));
    }
}

final class FlightsProbeBidRow
{
    public function handle(RenderingBidRowActions $hook): void
    {
        $hook->add(Fill::view('flightsprobe::probe', ['flight' => $hook->flight, 'bid' => $hook->bid]));
    }
}

final class FlightsProbeBidDrawer
{
    public function handle(RenderingBidDrawerActions $hook): void
    {
        $hook->add(Fill::view('flightsprobe::probe', ['bid' => $hook->bid, 'selection' => $hook->selection]));
    }
}

beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    View::addNamespace('flightsprobe', __DIR__.'/views');
    $this->fixture = airsideDispatchFixture();
    $this->actingAs($this->fixture['user']);

    Hooks::listen(RenderingFlightRowActions::class, FlightsProbeRow::class);
    Hooks::listen(RenderingFlightActions::class, FlightsProbeDetail::class);
    Hooks::listen(RenderingBidRowActions::class, FlightsProbeBidRow::class);
    Hooks::listen(RenderingBidDrawerActions::class, FlightsProbeBidDrawer::class);
});

it('fills flights.row.actions and flights.detail.actions on the manifest with flight and policy', function (): void {
    $callsign = $this->fixture['flight']->ident;

    $html = preg_replace('/\s+/', ' ', preg_replace('/<!--.*?-->/s', '', Livewire::test(FlightManifest::class)->html()));

    expect(preg_match('/data-probe>\s*F:'.preg_quote($callsign, '/').'\s*P\s*<\/span>/', $html))->toBe(1);
});

it('fills flights.detail.actions on the flight page with flight and policy', function (): void {
    $html = preg_replace('/\s+/', ' ', preg_replace('/<!--.*?-->/s', '', Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id])->html()));

    expect($html)->toContain('F:'.$this->fixture['flight']->ident.' P');
});

it('fills bids.row.actions with bid and flight', function (): void {
    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(BidList::class)->assertSeeHtmlInOrder(['F:'.$this->fixture['flight']->ident, 'B:'.$bid->id]);
});

it('fills bids.drawer.overview.actions with bid and selection', function (): void {
    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(AssignmentDrawer::class)
        ->dispatch('assignment-drawer.open', flightId: $this->fixture['flight']->id)
        ->assertSeeHtmlInOrder(['B:'.$bid->id, 'S:'.$bid->id]);
});
