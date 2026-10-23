<?php

declare(strict_types=1);

use App\Http\Requests\RouteForge\LintRequest;
use App\Models\Airport;
use Illuminate\Support\Facades\Validator;

/**
 * The origin and destination rules used to carry `alpha`, which restricted an
 * airport id to letters. Airport ids are not letters-only -- the factory draws
 * from A-Z0-9, real ICAO identifiers carry digits, and the generated ids these
 * columns were widened for will carry digits, `-` and `_`. The `exists` rule
 * alongside is what actually decides whether an airport is usable, so `alpha`
 * could only reject one that `exists` had already accepted.
 */
function airportListPasses(array $ids): bool
{
    $rules = LintRequest::create('/admin/route-forge/api/lint', 'POST')->rules();

    return Validator::make(
        ['origins' => $ids, 'destinations' => $ids],
        array_intersect_key($rules, array_flip(['origins', 'origins.*', 'destinations', 'destinations.*'])),
    )->passes();
}

it('accepts an existing airport id that carries digits', function (): void {
    Airport::factory()->create(['id' => 'K6L3', 'icao' => 'K6L3']);

    expect(airportListPasses(['K6L3']))->toBeTrue();
});

it('accepts an existing airport id at the full column width', function (): void {
    Airport::factory()->create(['id' => 'V1a2B3c4D5e6F7g8', 'icao' => 'KJFK']);

    expect(airportListPasses(['V1a2B3c4D5e6F7g8']))->toBeTrue();
});

it('still rejects an airport that does not exist', function (): void {
    expect(airportListPasses(['ZZZZ']))->toBeFalse();
});

/**
 * rows.*.dpt_airport_id and rows.*.arr_airport_id carried the same `alpha`,
 * and there Rule::in() over the submitted origins and destinations is the real
 * check -- so `alpha` could only reject an id the scope had already accepted.
 */
it('accepts a row airport id that carries digits, and still scopes it', function (): void {
    Airport::factory()->create(['id' => 'K6L3', 'icao' => 'K6L3']);
    Airport::factory()->create(['id' => 'K7M4', 'icao' => 'K7M4']);

    $rowPasses = function (string $dpt, string $arr): bool {
        $request = LintRequest::create('/admin/route-forge/api/lint', 'POST', [
            'origins'      => ['K6L3'],
            'destinations' => ['K7M4'],
        ]);
        $rules = $request->rules();

        return Validator::make(
            ['rows' => [['dpt_airport_id' => $dpt, 'arr_airport_id' => $arr]]],
            array_intersect_key($rules, array_flip(['rows.*.dpt_airport_id', 'rows.*.arr_airport_id'])),
        )->passes();
    };

    expect($rowPasses('K6L3', 'K7M4'))->toBeTrue()
        ->and($rowPasses('K6L3', 'KLAX'))->toBeFalse();
});
