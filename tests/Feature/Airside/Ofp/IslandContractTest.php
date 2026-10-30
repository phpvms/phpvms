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

    $views = base_path('modules/phpvms-airside-theme/resources/views/ofp');
    expect(file_get_contents($views.'/planning.blade.php'))->toContain("@airsideVite('resources/js/islands/simbrief-attempt.js')")
        ->and(file_get_contents($views.'/briefing.blade.php'))->toContain("@airsideVite('resources/js/islands/briefing-globe.js')");
});

it('leaves no raw directive text in the rendered pages', function (): void {
    $flight = $this->fixture['flight'];
    $html = $this->actingAs($this->fixture['user'])
        ->get('/ofp/planning?flight_id='.$flight->id.'&aircraft_id='.$this->fixture['aircraft']->id)
        ->getContent();

    expect($html)->not->toContain('@js(')->not->toContain('@airsideVite');
});

it('keeps the popup, poll and retry behaviour of the skylight hook in the island', function (): void {
    $source = file_get_contents(base_path('modules/phpvms-airside-theme/resources/js/islands/simbrief-attempt.js'));

    expect($source)
        ->toContain('@phpvms/simbrief')
        ->toContain('reserveProviderPopup(POPUP_NAME)')
        ->toContain('const POPUP_NAME = "phpvms-simbrief"')
        ->toContain('POLL_INTERVAL_MS = 3_000')
        ->toContain('POLL_MAX_ATTEMPTS = 60')
        ->toContain('The SimBrief window was blocked. Allow popups, then try Generate OFP again.')
        ->toContain('Checking for the completed OFP')
        ->toContain('livewire:navigating');

    // The popup is reserved before the first await, or the browser blocks it.
    expect(strpos($source, 'reserveProviderPopup(POPUP_NAME)'))->toBeLessThan(strpos($source, 'await requestProviderSubmission'));
});
