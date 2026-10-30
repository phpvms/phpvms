<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Enums\PirepState;
use App\Models\Pirep;

/**
 * 7.7: the logbook list, its pagination and the empty state.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('shows the header, the total and the empty state when there are no PIREPs', function (): void {
    $this->actingAs($this->pilot)->get(route('frontend.pireps.index'))
        ->assertOk()
        ->assertSee('Logbook')
        ->assertSee('My PIREPs')
        ->assertSee('Every flight you have filed, most recent first.')
        ->assertSee('0 total')
        ->assertSee('No PIREPs yet')
        ->assertSee('Fly a flight and it will show up in your logbook.')
        ->assertDontSee('aria-label="Pagination"', escape: false);
});

it("lists the pilot's PIREPs with route, state and stats", function (): void {
    $pirep = Pirep::factory()->create([
        'user_id'      => $this->pilot->id,
        'state'        => PirepState::ACCEPTED,
        'flight_time'  => 125,
        'score'        => 87,
        'submitted_at' => '2026-03-14 10:00:00',
    ]);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.index'))
        ->assertOk()
        ->assertSee('1 total')
        ->assertSee($pirep->ident)
        ->assertSee($pirep->dpt_airport_id)
        ->assertSee($pirep->arr_airport_id)
        ->assertSee('Accepted')
        ->assertSee('2h 5m')
        ->assertSee('87')
        ->assertSee('14 Mar 2026')
        ->assertSee('Aircraft')
        ->assertSee('Distance')
        ->assertSee('Filed')
        ->assertSee(route('frontend.pireps.show', ['id' => $pirep->id]), false)
        ->assertDontSee('No PIREPs yet');
});

it("does not list another pilot's PIREPs or cancelled ones", function (): void {
    $other = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    $cancelled = Pirep::factory()->create(['user_id' => $this->pilot->id, 'state' => PirepState::CANCELLED]);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.index'))
        ->assertOk()
        ->assertDontSee($other->ident)
        ->assertDontSee($cancelled->id)
        ->assertSee('0 total');
});

it('pages the logbook and shows the pager only past one page', function (): void {
    $perPage = paginate_limit();
    Pirep::factory()->count($perPage + 1)->create(['user_id' => $this->pilot->id, 'state' => PirepState::ACCEPTED]);

    $first = $this->actingAs($this->pilot)->get(route('frontend.pireps.index'))
        ->assertOk()
        ->assertSee(($perPage + 1).' total')
        ->assertSee('aria-label="Pagination"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('aria-label="Next page"', false);

    expect($first->getContent())->not->toContain('aria-label="Previous page"');

    $this->actingAs($this->pilot)->get(route('frontend.pireps.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('aria-label="Previous page"', false)
        ->assertDontSee('aria-label="Next page"', false);
});
