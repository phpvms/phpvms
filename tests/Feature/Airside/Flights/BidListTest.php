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

it('removes a plain bid in place, without a reload', function (): void {
    $fixture = airsideDispatchFixture();
    $other = airsideDispatchFixture($fixture['user']);
    Bid::query()->create(['user_id' => $fixture['user']->id, 'flight_id' => $fixture['flight']->id]);
    Bid::query()->create(['user_id' => $fixture['user']->id, 'flight_id' => $other['flight']->id]);

    Livewire::actingAs($fixture['user'])->test(BidList::class)
        ->call('removeBid', $fixture['flight']->id)
        ->assertSet('removeError', null)
        ->assertNoRedirect();

    expect(Bid::query()->where('flight_id', $fixture['flight']->id)->exists())->toBeFalse()
        ->and(Bid::query()->where('flight_id', $other['flight']->id)->exists())->toBeTrue();
});

it('shows the error when a bid cannot be removed', function (): void {
    $fixture = airsideDispatchFixture();

    Livewire::actingAs($fixture['user'])->test(BidList::class)
        ->call('removeBid', 'not-a-flight')
        ->assertSet('removeError', 'The bid could not be removed. Try again.');
});

describe('tours', function (): void {
    beforeEach(function (): void {
        $this->tour = makeTour(3);
        app(PlaceBid::class)->handle($this->tour['flights'][0], $this->tour['user'], $this->tour['aircraft']);
    });

    it('cancels the whole run when a leg is removed, and every leg leaves the page', function (): void {
        $user = $this->tour['user'];
        $firstLeg = $this->tour['flights'][0];

        expect(Bid::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0);

        Livewire::actingAs($user)->test(BidList::class)
            ->call('removeBid', $firstLeg->id)
            ->assertSet('removeError', null);

        expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)->toBe(TourStatus::Cancelled)
            ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
    });
});
