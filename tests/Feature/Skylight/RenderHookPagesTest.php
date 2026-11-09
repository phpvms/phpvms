<?php

declare(strict_types=1);

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingBidDrawerActions;
use App\Features\Hooks\Points\RenderingBidRowActions;
use App\Features\Hooks\Points\RenderingBriefingActions;
use App\Features\Hooks\Points\RenderingFlightActions;
use App\Features\Hooks\Points\RenderingFlightRowActions;
use App\Features\Hooks\Points\RenderingPirepDetail;
use App\Features\Hooks\Points\RenderingProfileCards;
use App\Http\Data\BidData;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\Subfleet;
use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

final class PagesFlightActionsListener
{
    public function handle(RenderingFlightActions $hook): void
    {
        $hook->add(Fill::vue('Foo', null, ['flight' => '@flight']));
    }
}

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_show_flights_from_current', false);
    updateSetting('bids.allow_multiple_bids', true);
});

function pagesFixture(): array
{
    $departure = Airport::factory()->create();
    $user = User::factory()->create(['curr_airport_id' => $departure->id]);
    $subfleet = Subfleet::factory()->create(['airline_id' => $user->airline_id]);
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id, 'airport_id' => $departure->id]);
    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => Airport::factory()->create()->id,
    ]);

    return ['user' => $user, 'aircraft' => $aircraft, 'flight' => $flight];
}

it('shares a listener-added vue fill in the skylight slots prop of the flights page', function (): void {
    Hooks::listen(RenderingFlightActions::class, PagesFlightActionsListener::class);
    $fixture = pagesFixture();

    $this->actingAs($fixture['user'])
        ->get('/flights')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('skylight.slots', [[
                'slot'      => 'flights.detail.actions',
                'component' => 'Foo',
                'module'    => null,
                'view'      => null,
                'props'     => ['flight' => '@flight'],
                'order'     => 0,
            ]]));
});

it('dispatches the list hooks for the flights page, the flight page and the bids page', function (): void {
    $fixture = pagesFixture();
    Bid::factory()->create(['user_id' => $fixture['user']->id, 'flight_id' => $fixture['flight']->id]);
    $fake = Hooks::fake();

    $this->actingAs($fixture['user'])->get('/flights')->assertOk();
    $fake->assertDispatched(RenderingFlightActions::class, fn (RenderingFlightActions $hook): bool => $hook->isList() && $hook->user->is($fixture['user']));
    $fake->assertDispatched(RenderingFlightRowActions::class, fn (RenderingFlightRowActions $hook): bool => $hook->isList());

    $fake->assertNotDispatched(RenderingBidRowActions::class);
    $this->actingAs($fixture['user'])->get('/flights/bids')->assertOk();
    $fake->assertDispatched(RenderingBidRowActions::class, fn (RenderingBidRowActions $hook): bool => $hook->isList());
    $fake->assertDispatched(RenderingBidDrawerActions::class, fn (RenderingBidDrawerActions $hook): bool => $hook->isList());

    $this->actingAs($fixture['user'])->get('/flights/'.$fixture['flight']->id)->assertOk();
    $fake->assertDispatched(RenderingFlightActions::class, fn (RenderingFlightActions $hook): bool => $hook->isList() && $hook->policy->requireCurrentAirport === false);
});

it('dispatches the PIREP detail hook with the viewed PIREP', function (): void {
    $user = User::factory()->create();
    $pirep = Pirep::factory()->create(['user_id' => $user->id]);
    $fake = Hooks::fake();

    $this->actingAs($user)->get('/pireps/'.$pirep->id)->assertOk();

    $fake->assertDispatched(RenderingPirepDetail::class, fn (RenderingPirepDetail $hook): bool => $hook->pirep->is($pirep));
});

it('dispatches the profile hook with the profile DTO', function (): void {
    $user = User::factory()->create();
    $fake = Hooks::fake();

    $this->actingAs($user)->get('/profile/'.$user->id)->assertOk();

    $fake->assertDispatched(RenderingProfileCards::class, fn (RenderingProfileCards $hook): bool => $hook->profile->isOwnProfile);
});

it('dispatches the briefing hook with the briefing the page holds', function (): void {
    $fixture = pagesFixture();
    $briefing = SimBrief::factory()->create([
        'user_id'     => $fixture['user']->id,
        'flight_id'   => $fixture['flight']->id,
        'aircraft_id' => $fixture['aircraft']->id,
    ]);
    $fake = Hooks::fake();

    $this->actingAs($fixture['user'])->get('/ofp/briefings/'.$briefing->id)->assertOk();

    $fake->assertDispatched(RenderingBriefingActions::class, fn (RenderingBriefingActions $hook): bool => $hook->briefing->id === (string) $briefing->id
        && $hook->flight->summary->id === $fixture['flight']->id
        && $hook->aircraft->id === $fixture['aircraft']->id
        && !$hook->bid instanceof BidData);
});
