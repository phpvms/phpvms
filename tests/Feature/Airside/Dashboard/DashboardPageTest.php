<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Features\Dashboard\Livewire\DashboardBoard;
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

it('renders the pilot header and the board', function (): void {
    $airport = Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);
    $rank = Rank::factory()->create(['name' => 'First Officer', 'hours' => 999999]);
    $this->pilot->update(['name' => 'Ada Pilot', 'curr_airport_id' => $airport->id, 'rank_id' => $rank->id, 'flights' => 12]);

    $response = $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))->assertOk();

    $response->assertSeeInOrder(['Ada Pilot', 'At KJFK'])
        ->assertSeeLivewire(DashboardBoard::class);
});

it('shows a dash for a missing score, on-time percentage and landing rate', function (): void {
    $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))
        ->assertOk()
        ->assertSee('—');
});

it('says the rank target is unavailable when the pilot has no rank', function (): void {
    $this->pilot->update(['rank_id' => null]);

    $this->actingAs($this->pilot)->get(route('frontend.dashboard.index'))
        ->assertOk()
        ->assertSee('Rank target unavailable');
});
