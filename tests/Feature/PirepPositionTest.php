<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Support\Units\Distance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('distance keeps its fractional part', function (): void {
    $pirep = Pirep::factory()->create();

    PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
        'distance' => 1234.56,
    ]);

    // Past the cast: `acars`.`distance` was an int and would have stored 1234.
    $raw = DB::table('pirep_positions')->where('pirep_id', $pirep->id)->value('distance');

    expect(round((float) $raw, 2))->toBe(1234.56);
});

test('vertical speed stores negatives', function (): void {
    $pirep = Pirep::factory()->create();

    $position = PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
        'vs'       => -1800,
    ]);

    expect($position->fresh()->vs)->toBe(-1800.0);
});

test('display units apply as they do for acars', function (): void {
    updateSetting('units.distance', 'km');

    $pirep = Pirep::factory()->create();

    $position = PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
        'distance' => 100,
    ]);

    $acars = Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::FLIGHT_PATH,
        'distance' => 100,
    ]);

    // Units come from the cast, not the column name.
    expect($position->fresh()->distance)->toBeInstanceOf(Distance::class)
        ->and($position->fresh()->distance->local(2))
        ->toBe($acars->fresh()->distance->local(2));
});

test('every telemetry column is not null', function (): void {
    // Seeded-to-zero only holds if the schema enforces it.
    $nullable = collect(Schema::getColumns('pirep_positions'))
        ->reject(fn (array $column): bool => in_array($column['name'], ['created_at', 'updated_at'], true))
        ->filter(fn (array $column): bool => $column['nullable'])
        ->pluck('name')
        ->all();

    expect($nullable)->toBe([]);
});
