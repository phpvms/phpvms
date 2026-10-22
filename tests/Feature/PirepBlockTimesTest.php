<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepState;
use App\Models\Acars;
use App\Models\Pirep;
use App\Services\PirepService;

/**
 * Filing used to leave both block times unset. The guard reads
 * `if (!$pirep->block_on_time)`, and CarbonCast turned the NULL column into
 * *now* — a truthy Carbon — so the whole branch was unreachable and the API
 * only appeared to report block times because the cast fabricated them.
 */
beforeEach(function (): void {
    $this->svc = app(PirepService::class);
});

it('takes block on from the last ACARS report, not the filing time', function (): void {
    $pirep = Pirep::factory()->create([
        'state'          => PirepState::IN_PROGRESS,
        'submitted_at'   => null,
        'block_on_time'  => null,
        'block_off_time' => null,
        'flight_time'    => 120,
    ]);

    $lastReport = now()->subHours(3);

    foreach ([now()->subHours(5), now()->subHours(4), $lastReport] as $at) {
        Acars::factory()->create([
            'pirep_id'   => $pirep->id,
            'type'       => AcarsType::FLIGHT_PATH,
            'created_at' => $at,
        ]);
    }

    $filed = $this->svc->file($pirep);

    // Filing happens now; the flight ended three hours ago and the block times
    // have to say so.
    expect($filed->block_on_time->toDateTimeString())->toBe($lastReport->toDateTimeString())
        ->and($filed->block_off_time->toDateTimeString())
        ->toBe($lastReport->copy()->subMinutes(120)->toDateTimeString())
        ->and($filed->block_on_time->diffInMinutes($filed->block_off_time, absolute: true))
        ->toEqual(120.0);
});

it('falls back to the submit time when the flight carries no telemetry', function (): void {
    $pirep = Pirep::factory()->create([
        'state'          => PirepState::IN_PROGRESS,
        'submitted_at'   => null,
        'block_on_time'  => null,
        'block_off_time' => null,
        'flight_time'    => 60,
    ]);

    $filed = $this->svc->file($pirep);

    expect($filed->block_on_time)->not->toBeNull()
        ->and($filed->block_on_time->toDateTimeString())->toBe($filed->submitted_at->toDateTimeString())
        ->and($filed->block_off_time->toDateTimeString())
        ->toBe($filed->submitted_at->copy()->subMinutes(60)->toDateTimeString());
});

it('leaves block times a client already reported alone', function (): void {
    $blockOff = now()->subHours(6);
    $blockOn = now()->subHours(4);

    $pirep = Pirep::factory()->create([
        'state'          => PirepState::IN_PROGRESS,
        'submitted_at'   => null,
        'block_off_time' => $blockOff,
        'block_on_time'  => $blockOn,
        'flight_time'    => 120,
    ]);

    Acars::factory()->create([
        'pirep_id'   => $pirep->id,
        'type'       => AcarsType::FLIGHT_PATH,
        'created_at' => now()->subHour(),
    ]);

    $filed = $this->svc->file($pirep);

    expect($filed->block_off_time->toDateTimeString())->toBe($blockOff->toDateTimeString())
        ->and($filed->block_on_time->toDateTimeString())->toBe($blockOn->toDateTimeString());
});

it('leaves block off unset when there is no flight time to work back from', function (): void {
    $pirep = Pirep::factory()->create([
        'state'          => PirepState::IN_PROGRESS,
        'submitted_at'   => null,
        'block_on_time'  => null,
        'block_off_time' => null,
        'flight_time'    => 0,
    ]);

    $filed = $this->svc->file($pirep);

    expect($filed->block_on_time)->not->toBeNull()
        ->and($filed->block_off_time)->toBeNull();
});
