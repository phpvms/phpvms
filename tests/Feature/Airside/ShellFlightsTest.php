<?php

declare(strict_types=1);

require_once __DIR__.'/Flights/Support.php';

use App\Features\Shell\ShellData;
use App\Models\Bid;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The header's previous and next flight (ShellData::previousFlight() and nextFlight()).
 */
function shellFor(User $user): ShellData
{
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): User => $user);

    return app(ShellData::class, ['request' => $request]);
}

beforeEach(function (): void {
    airsideFlightsBaseline();
    $this->fixture = airsideDispatchFixture();
});

it('has no previous or next flight for a pilot with no PIREP and no bid', function (): void {
    $shell = shellFor($this->fixture['user']);

    expect($shell->previousFlight())->toBeNull()
        ->and($shell->nextFlight())->toBeNull();
});

it('links the last PIREP and the first bid', function (): void {
    $user = $this->fixture['user'];
    $pirep = Pirep::factory()->create(['user_id' => $user->id, 'dpt_airport_id' => 'KAUS', 'arr_airport_id' => 'KJFK']);
    $user->update(['last_pirep_id' => $pirep->id]);
    Bid::query()->create(['user_id' => $user->id, 'flight_id' => $this->fixture['flight']->id]);

    $shell = shellFor($user->fresh());

    expect($shell->previousFlight())->toMatchArray([
        'route' => 'KAUS → KJFK',
        'url'   => route('frontend.pireps.show', ['id' => $pirep->id]),
    ])->and($shell->nextFlight())->toMatchArray([
        'route' => $this->fixture['departure']->id.' → '.$this->fixture['arrival']->id,
        'url'   => route('frontend.flights.bids'),
        'tour'  => null,
    ]);
});
