<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\FlightBundle;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L8EventDatesOutsideWindow;
use Illuminate\Support\Carbon;
use Tests\Support\RouteForgeTestHelpers as RF;

function l8Event(string $start, string $end): Event
{
    return Event::factory()->make([
        'name'       => 'Test Event',
        'start_date' => $start,
        'end_date'   => $end,
    ]);
}

function l8Bundle(?string $start, ?string $end): FlightBundle
{
    return new FlightBundle([
        'name'       => 'Test Bundle',
        'enabled'    => true,
        'start_date' => $start !== null ? Carbon::parse($start) : null,
        'end_date'   => $end !== null ? Carbon::parse($end) : null,
    ]);
}

it('fires when bundle window is fully before the event window', function (): void {
    $issues = (new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle('2026-06-01', '2026-06-30'),
        event: l8Event('2026-07-01', '2026-07-31'),
    ));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L8')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->rowIndex)->toBeNull();
});

it('fires when bundle window is fully after the event window', function (): void {
    $issues = (new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle('2026-08-01', '2026-08-31'),
        event: l8Event('2026-07-01', '2026-07-31'),
    ));

    expect($issues)->toHaveCount(1);
});

it('does not fire when bundle and event windows overlap', function (): void {
    $issues = (new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle('2026-07-15', '2026-08-15'),
        event: l8Event('2026-07-01', '2026-07-31'),
    ));

    expect($issues)->toBe([]);
});

it('treats missing bundle start_date as -infinity', function (): void {
    $issues = (new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle(null, '2026-07-15'), // ends mid-event, no start
        event: l8Event('2026-07-01', '2026-07-31'),
    ));

    expect($issues)->toBe([]);
});

it('treats missing bundle end_date as +infinity', function (): void {
    $issues = (new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle('2026-07-15', null), // starts mid-event, no end
        event: l8Event('2026-07-01', '2026-07-31'),
    ));

    expect($issues)->toBe([]);
});

it('does not fire when no event is selected', function (): void {
    expect((new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle('2026-08-01', '2026-08-31'),
        event: null,
    )))->toBe([]);
});

it('does not fire when bundle has no dates at all', function (): void {
    expect((new L8EventDatesOutsideWindow())->check(RF::ctx(
        rows: [RF::row()],
        bundle: l8Bundle(null, null),
        event: l8Event('2026-07-01', '2026-07-31'),
    )))->toBe([]);
});
