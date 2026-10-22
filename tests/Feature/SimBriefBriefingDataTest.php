<?php

declare(strict_types=1);

use App\Http\Data\SimBriefBriefingData;
use App\Models\Pirep;
use App\Models\SimBrief;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A SimBrief row with a real OFP attached but no `pirep_id` — the pre-flight
 * state the briefing page reads. `Pirep::factory()` is used only as a cheap
 * way to get an associated flight/aircraft/user (mirrors
 * `PirepArchiveTest::attachSimBriefOfp()`); the pirep itself is discarded.
 *
 * `id` is passed explicitly as a string (36-char, matching the `simbrief`
 * table's `string('id', 36)->primary()` column and how production sets it
 * from SimBrief's own OFP id — see `SimBriefService.php:91,249`).
 * `SimBriefFactory`'s default is an int, which `SimBriefBriefingData`'s
 * `string $id` (under `declare(strict_types=1)`) rejects; that's a
 * pre-existing factory/DTO mismatch, worked around test-locally rather than
 * by changing the shared factory.
 */
function briefingWithOfp(): SimBrief
{
    $pirep = Pirep::factory()->create();

    $path = 'simbrief/briefing-data-test-'.fake()->uuid().'.json';
    Storage::put($path, readDataFile('simbrief/briefing.json'));

    return SimBrief::factory()->create([
        'id'            => Str::uuid()->toString(),
        'user_id'       => $pirep->user_id,
        'flight_id'     => $pirep->flight_id,
        'aircraft_id'   => $pirep->aircraft_id,
        'pirep_id'      => null,
        'ofp_json_path' => $path,
    ]);
}

test('a briefing with a navlog returns planned fixes carrying ident, coordinates, and altitude', function (): void {
    $briefing = briefingWithOfp();

    $data = SimBriefBriefingData::fromModel($briefing, null);

    expect($data->plannedFixes)->not->toBeEmpty();

    foreach ($data->plannedFixes as $fix) {
        expect($fix->ident)->not->toBeEmpty()
            ->and($fix->lat)->toBeFloat()
            ->and($fix->lon)->toBeFloat()
            ->and($fix->altitudeFt)->not->toBeNull();
    }
});

test('a briefing with no OFP returns empty planned fixes and does not error', function (): void {
    $pirep = Pirep::factory()->create();

    $briefing = SimBrief::factory()->create([
        'id'            => Str::uuid()->toString(),
        'user_id'       => $pirep->user_id,
        'flight_id'     => $pirep->flight_id,
        'aircraft_id'   => $pirep->aircraft_id,
        'pirep_id'      => null,
        'ofp_json_path' => 'simbrief/does-not-exist.json',
    ]);

    $data = SimBriefBriefingData::fromModel($briefing, null);

    expect($data->plannedFixes)->toEqual([])
        ->and($data->route)->toEqual('');
});
