<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Enums\PirepState;
use App\Models\Pirep;

/**
 * 7.7: which PIREPs the logbook lists and how it pages.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it("does not list another pilot's PIREPs or cancelled ones", function (): void {
    $mine = Pirep::factory()->create(['user_id' => $this->pilot->id, 'state' => PirepState::ACCEPTED]);
    Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    Pirep::factory()->create(['user_id' => $this->pilot->id, 'state' => PirepState::CANCELLED]);

    $response = $this->actingAs($this->pilot)->get(route('frontend.pireps.index'))->assertOk();

    expect(collect($response->viewData('pireps'))->pluck('id')->all())->toBe([$mine->id]);
});

it('pages the logbook', function (): void {
    $perPage = paginate_limit();
    Pirep::factory()->count($perPage + 1)->create(['user_id' => $this->pilot->id, 'state' => PirepState::ACCEPTED]);

    $first = $this->actingAs($this->pilot)->get(route('frontend.pireps.index'));
    $second = $this->actingAs($this->pilot)->get(route('frontend.pireps.index', ['page' => 2]));

    expect($first->viewData('pagination')['total'])->toBe($perPage + 1)
        ->and($first->viewData('pagination')['lastPage'])->toBe(2)
        ->and($first->viewData('pireps'))->toHaveCount($perPage)
        ->and($second->viewData('pireps'))->toHaveCount(1);
});
