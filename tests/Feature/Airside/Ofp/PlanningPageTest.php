<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Models\SimBriefAttempt;

/**
 * 7.6: the OFP planning page under airside renders Blade with the SPA props.
 */
beforeEach(function (): void {
    $this->fixture = airsideOfpFixture();
    $this->withoutVite();
});

it('creates no attempt while no aircraft is chosen', function (): void {
    $flight = $this->fixture['flight'];

    $this->actingAs($this->fixture['user'])
        ->get('/ofp/planning?flight_id='.$flight->id)
        ->assertOk();

    expect(SimBriefAttempt::query()->count())->toBe(0);
});

it('creates an attempt and hands the island its data once an aircraft is chosen', function (): void {
    $flight = $this->fixture['flight'];
    $aircraft = $this->fixture['aircraft'];

    $response = $this->actingAs($this->fixture['user'])
        ->get('/ofp/planning?flight_id='.$flight->id.'&aircraft_id='.$aircraft->id)
        ->assertOk();

    $attempt = SimBriefAttempt::query()->where('user_id', $this->fixture['user']->id)->firstOrFail();

    // The island reads its data from a JSON script: the attempt, both endpoints and the provider fields.
    preg_match('/<script type="application\/json">(.*?)<\/script>/s', $response->getContent(), $match);
    $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

    expect($data['staticId'])->toBe($attempt->static_id)
        ->and($data['apiCodeUrl'])->toBe(route('frontend.ofp.attempt.api-code', $attempt->static_id))
        ->and($data['pollUrl'])->toBe(route('frontend.ofp.attempt.poll', $attempt->static_id))
        ->and($data['providerFields']['orig'])->toBe($this->fixture['departure']->id)
        ->and($data['providerFields']['reg'])->toBe($aircraft->registration);
});

it('sends a pilot with an existing briefing to it', function (): void {
    $briefing = airsideBriefing($this->fixture);

    $this->actingAs($this->fixture['user'])
        ->get('/ofp/planning?flight_id='.$this->fixture['flight']->id)
        ->assertRedirect(route('frontend.ofp.briefing', $briefing->id));
});
