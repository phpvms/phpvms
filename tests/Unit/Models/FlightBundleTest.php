<?php

declare(strict_types=1);

use App\Models\Flight;
use App\Models\FlightBundle;
use Illuminate\Support\Carbon;

it('seeds a bundle named Default post-migration', function (): void {
    $default = FlightBundle::where('name', 'Default')->first();

    expect($default)->not->toBeNull()
        ->and($default->enabled)->toBeTrue()
        ->and($default->visible)->toBeTrue();
});

it('returns expected flights via relationship', function (): void {
    $bundle = FlightBundle::factory()->create();

    $flights = Flight::factory()->count(2)->create([
        'bundle_id' => $bundle->id,
    ]);

    $bundle->refresh();

    expect($bundle->flights)->toHaveCount(2)
        ->and($bundle->flights->contains('id', $flights->first()->id))->toBeTrue();
});

it('hasDates reflects date presence', function (): void {
    $noDates = FlightBundle::factory()->create([
        'start_date' => null,
        'end_date'   => null,
    ]);

    $withDates = FlightBundle::factory()->create([
        'start_date' => Carbon::today(),
        'end_date'   => null,
    ]);

    expect($noDates->has_dates)->toBeFalse()
        ->and($withDates->has_dates)->toBeTrue();
});
