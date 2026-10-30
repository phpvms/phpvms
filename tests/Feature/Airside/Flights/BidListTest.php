<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Bids\Actions\PlaceBid;
use App\Features\Bids\Livewire\BidList;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Bid;
use Livewire\Livewire;

/**
 * 7.3: My bids: tour sections, inline confirms, and removal with no reload.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
});

it('renders the page with the list component', function (): void {
    $fixture = airsideDispatchFixture();

    $this->actingAs($fixture['user'])->get('/flights/bids')
        ->assertOk()
        ->assertSee('wire:name="bids::bid-list"', false)
        ->assertSee('My bids');
});

it('shows the empty state', function (): void {
    $fixture = airsideDispatchFixture();

    Livewire::actingAs($fixture['user'])->test(BidList::class)
        ->assertSee('Flights')
        ->assertSee('Your reserved dispatches, ready to reopen and brief.')
        ->assertSee('0 bids')
        ->assertSee('No bids yet')
        ->assertSee('Reserve a flight from the manifest to keep it here for your next dispatch.')
        ->assertSee('Browse flights');
});

it('lists a bid with its facts, expiry and actions', function (): void {
    updateSetting('simbrief.api_key', 'key');
    updateSetting('bids.expire_time', 24);
    $fixture = airsideDispatchFixture();
    $bid = Bid::query()->create([
        'user_id'     => $fixture['user']->id,
        'flight_id'   => $fixture['flight']->id,
        'aircraft_id' => $fixture['aircraft']->id,
    ]);

    Livewire::actingAs($fixture['user'])->test(BidList::class)
        ->assertSee('1 bid')
        ->assertSee($fixture['flight']->ident)
        ->assertSee('ETE')
        ->assertSee('Dist')
        ->assertSee($fixture['aircraft']->registration.' · '.$fixture['aircraft']->icao, false)
        ->assertSee('Expires')
        ->assertSee('Expiring first')
        ->assertSee('Cancel this bid?')
        ->assertSee('Generate OFP')
        ->assertSee(route('frontend.ofp.planning', ['bid_id' => $bid->id]), false);
});

it('says No expiry when bids do not expire', function (): void {
    $fixture = airsideDispatchFixture();
    Bid::query()->create(['user_id' => $fixture['user']->id, 'flight_id' => $fixture['flight']->id]);

    Livewire::actingAs($fixture['user'])->test(BidList::class)->assertSee('No expiry');
});

it('removes a plain bid in place, without a reload', function (): void {
    $fixture = airsideDispatchFixture();
    $other = airsideDispatchFixture($fixture['user']);
    Bid::query()->create(['user_id' => $fixture['user']->id, 'flight_id' => $fixture['flight']->id]);
    Bid::query()->create(['user_id' => $fixture['user']->id, 'flight_id' => $other['flight']->id]);

    Livewire::actingAs($fixture['user'])->test(BidList::class)
        ->assertSee('2 bids')
        ->assertSee($fixture['flight']->ident)
        ->call('removeBid', $fixture['flight']->id)
        ->assertSee('1 bid')
        ->assertDontSee($fixture['flight']->ident)
        ->assertSee($other['flight']->ident)
        ->assertSet('removeError', null)
        ->assertNoRedirect();

    expect(Bid::query()->where('flight_id', $fixture['flight']->id)->exists())->toBeFalse();
});

it('shows the error when a bid cannot be removed', function (): void {
    $fixture = airsideDispatchFixture();

    Livewire::actingAs($fixture['user'])->test(BidList::class)
        ->call('removeBid', 'not-a-flight')
        ->assertSet('removeError', 'The bid could not be removed. Try again.')
        ->assertSee('The bid could not be removed. Try again.');
});

describe('tours', function (): void {
    beforeEach(function (): void {
        $this->tour = makeTour(3);
        app(PlaceBid::class)->handle($this->tour['flights'][0], $this->tour['user'], $this->tour['aircraft']);
        $this->tourName = UserTour::query()->where('user_id', $this->tour['user']->id)->firstOrFail()->name;
    });

    it('groups tour legs under their tour with the tour actions', function (): void {
        Livewire::actingAs($this->tour['user'])->test(BidList::class)
            ->assertSee($this->tourName)
            ->assertSee('Tour overview')
            ->assertSee('Cancel tour')
            ->assertSee('Cancel the whole tour?')
            ->assertSee('Yes, cancel it')
            ->assertSee('Keep flying')
            ->assertSee('legs')
            ->assertSee('Leg 1');
    });

    it('asks before a leg ends the tour, with the tour named', function (): void {
        Livewire::actingAs($this->tour['user'])->test(BidList::class)
            ->assertSee('Cancelling this leg ends “'.$this->tourName.'”.')
            ->assertSee('Cancel the tour');
    });

    it('cancels the whole run when a leg is removed, and every leg leaves the page', function (): void {
        $user = $this->tour['user'];
        $firstLeg = $this->tour['flights'][0];

        expect(Bid::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0);

        Livewire::actingAs($user)->test(BidList::class)
            ->call('removeBid', $firstLeg->id)
            ->assertDontSee($this->tourName)
            ->assertSee('No bids yet')
            ->assertSet('removeError', null);

        expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)->toBe(TourStatus::Cancelled)
            ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
    });
});
