<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Models\Airport;
use App\Models\Rank;

/**
 * 7.5: the dashboard page renders the pilot header from DashboardData and
 * mounts the board.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('renders the pilot header, the seven metrics and the board', function (): void {
    $airport = Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);
    $rank = Rank::factory()->create(['name' => 'First Officer', 'hours' => 999999]);
    $this->pilot->update(['name' => 'Ada Pilot', 'curr_airport_id' => $airport->id, 'rank_id' => $rank->id, 'flights' => 12]);

    $response = $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))->assertOk();

    $response->assertSeeInOrder(['Ada Pilot', 'At KJFK'])
        ->assertSee('Rank progress')
        ->assertSee('Highest rank reached')
        ->assertSee('Flights')
        ->assertSee('Hours')
        ->assertSee('Transfer')
        ->assertSee('Balance')
        ->assertSee('Pilot score')
        ->assertSee('On-time')
        ->assertSee('Avg landing')
        ->assertSee('wire:name="dashboard::dashboard-board"', false);
});

it('shows a dash for a missing score, on-time percentage and landing rate', function (): void {
    $html = $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))->assertOk()->getContent();

    // Each metric is a TallStackUI stats tile: the title heading, then the value in the number area.
    $tile = fn (string $label): string => '/'.preg_quote($label, '/').'<\/h2>\s*<div x-ref="number">\s*<span[^>]*>—<\/span>/';

    expect($html)->toMatch($tile('Pilot score'))
        ->and($html)->toMatch($tile('On-time'))
        ->and($html)->toMatch($tile('Avg landing'));
});

it('says the rank target is unavailable when the pilot has no rank', function (): void {
    $this->pilot->update(['rank_id' => null]);

    $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))
        ->assertOk()
        ->assertSee('Rank target unavailable');
});

it('passes the nav display route to the board', function (): void {
    $this->pilot->update(['curr_airport_id' => null, 'home_airport_id' => null]);

    $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))->assertOk()->assertSee('wire:name="dashboard::dashboard-board"', false);
});
