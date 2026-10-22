<?php

declare(strict_types=1);

use App\Casts\CarbonCast;
use App\Models\Pirep;
use Carbon\Carbon;

/**
 * `new Carbon(null)` is *now*, so a cast that does not guard the null turns an
 * unset column into the current time. `pireps.submitted_at` is the nullable one:
 * a flight still in the air has never been filed, and used to report itself as
 * filed this second wherever it was shown.
 */
it('leaves a null value null rather than reading it as now', function (): void {
    $cast = new CarbonCast();

    expect($cast->get(new Pirep(), 'submitted_at', null, []))->toBeNull();
});

it('still casts a real value to Carbon', function (): void {
    $cast = new CarbonCast();

    $value = $cast->get(new Pirep(), 'submitted_at', '2026-08-10 02:37:45', []);

    expect($value)->toBeInstanceOf(Carbon::class)
        ->and($value->toDateTimeString())->toBe('2026-08-10 02:37:45');
});

it('passes an existing Carbon straight through', function (): void {
    $cast = new CarbonCast();
    $carbon = Carbon::parse('2026-01-02 03:04:05');

    expect($cast->get(new Pirep(), 'submitted_at', $carbon, []))->toBe($carbon);
});
