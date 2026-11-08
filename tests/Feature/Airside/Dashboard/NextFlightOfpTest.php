<?php

declare(strict_types=1);

require_once __DIR__.'/../Ofp/Support.php';

use App\Features\Dashboard\Livewire\NextFlight;
use App\Models\Bid;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->fixture = airsideOfpFixture();
    $this->withoutVite();
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id, 'aircraft_id' => $this->fixture['aircraft']->id]);
});

function nextFlightBoard()
{
    return Livewire::actingAs(test()->fixture['user'])->test(NextFlight::class);
}

it('offers Generate OFP while the bid has no OFP and SimBrief is enabled', function (): void {
    nextFlightBoard()->assertSet('canGenerateOfp', true);
});

it('offers no Generate OFP when SimBrief is not enabled', function (): void {
    updateSetting('simbrief.api_key', '');

    nextFlightBoard()->assertSet('canGenerateOfp', false);
});

it('offers no Generate OFP once the pilot has an OFP', function (): void {
    airsideBriefing($this->fixture);

    nextFlightBoard()->assertSet('canGenerateOfp', false);
});

it('gives the flight page outlet the same flight and policy as the flight page', function (): void {
    $detail = nextFlightBoard()->get('detail');

    expect($detail['flight']->summary->id)->toBe($this->fixture['flight']->id)
        ->and($detail['policy']->simbriefEnabled)->toBeTrue();
});

it('keeps the component root on the first element so a re-render does not nest a second row', function (): void {
    $html = nextFlightBoard()
        ->dispatch('assignment-drawer.closed', flightId: $this->fixture['flight']->id, changed: false)
        ->html();

    // Livewire writes its id on the first element it finds; a root-level @if moves it to a child.
    preg_match('/<(?!!)[a-z][^>]*>/i', $html, $first);

    expect($first[0])->toContain('wire:id');
});

it('links Generate OFP by the bid, so the planning page opens on the form without the aircraft picker', function (): void {
    $bid = Bid::query()->sole();

    nextFlightBoard()->assertSet('ofpPlanningUrl', route('frontend.ofp.planning', ['bid_id' => $bid->id]));
});
