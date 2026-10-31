<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

/**
 * 7.6: the planning and briefing pages load their islands, and the island
 * sources keep the behaviour the skylight hook has. The blocked-popup path
 * needs a browser; this repo has no browser test runner, so the contract is
 * pinned at the source.
 */
beforeEach(function (): void {
    $this->fixture = airsideOfpFixture();
    $this->withoutVite();
});

it('loads the generate island on planning and the globe island on the briefing', function (): void {
    $flight = $this->fixture['flight'];

    $planning = $this->actingAs($this->fixture['user'])
        ->get('/ofp/planning?flight_id='.$flight->id.'&aircraft_id='.$this->fixture['aircraft']->id)
        ->assertOk()
        ->getContent();

    $briefing = $this->actingAs($this->fixture['user'])
        ->get(route('frontend.ofp.briefing', airsideBriefing($this->fixture)->id))
        ->assertOk()
        ->getContent();

    expect($planning)->toContain('data-airside-island="simbrief-attempt"')
        ->and($briefing)->toContain('data-airside-island="briefing-globe"');
});

it('leaves no raw directive text in the rendered pages', function (): void {
    $flight = $this->fixture['flight'];
    $html = $this->actingAs($this->fixture['user'])
        ->get('/ofp/planning?flight_id='.$flight->id.'&aircraft_id='.$this->fixture['aircraft']->id)
        ->getContent();

    expect($html)->not->toContain('@js(')->not->toContain('@airsideVite');
});
