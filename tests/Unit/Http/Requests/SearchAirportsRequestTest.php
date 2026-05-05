<?php

declare(strict_types=1);

use App\Http\Requests\SearchAirportsRequest;
use Illuminate\Support\Facades\Validator;

function validateSearchAirports(array $data): Illuminate\Contracts\Validation\Validator
{
    $request = new SearchAirportsRequest();

    return Validator::make($data, $request->rules());
}

test('SearchAirportsRequest passes with empty input', function (): void {
    expect(validateSearchAirports([])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with search term', function (): void {
    expect(validateSearchAirports(['search' => 'KJ'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with field-specific search syntax', function (): void {
    expect(validateSearchAirports(['search' => 'icao:e'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with legacy searchFields and searchJoin params', function (): void {
    expect(validateSearchAirports([
        'search'       => 'JFK',
        'searchFields' => 'icao:like;name',
        'searchJoin'   => 'and',
    ])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with hub flag', function (): void {
    expect(validateSearchAirports(['hub' => '1'])->passes())->toBeTrue();
    expect(validateSearchAirports(['hubs' => 'true'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with legacy single-column orderBy', function (): void {
    expect(validateSearchAirports(['orderBy' => 'icao', 'sortedBy' => 'asc'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with legacy multi-column orderBy', function (): void {
    expect(validateSearchAirports([
        'orderBy'  => 'country;icao',
        'sortedBy' => 'asc;desc',
    ])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with limit inside configured page size', function (): void {
    expect(validateSearchAirports(['limit' => (string) config('phpvms.pagination.limit')])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with additional legacy sortable columns', function (): void {
    expect(validateSearchAirports(['orderBy' => 'notes'])->passes())->toBeTrue();
});

test('SearchAirportsRequest rejects searchFields on disallowed column', function (): void {
    expect(validateSearchAirports(['searchFields' => 'notes'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects searchFields on disallowed operator', function (): void {
    expect(validateSearchAirports(['searchFields' => 'icao:between'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects orderBy on disallowed column', function (): void {
    expect(validateSearchAirports(['orderBy' => 'continent'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects sortedBy outside asc/desc', function (): void {
    expect(validateSearchAirports(['sortedBy' => 'sideways'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects search longer than 255 chars', function (): void {
    expect(validateSearchAirports(['search' => str_repeat('x', 256)])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects non-positive limit values', function (): void {
    expect(validateSearchAirports(['limit' => '0'])->fails())->toBeTrue()
        ->and(validateSearchAirports(['limit' => '-1'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects limit above configured max page size', function (): void {
    $tooLarge = (string) (config('phpvms.pagination.max') + 1);

    expect(validateSearchAirports(['limit' => $tooLarge])->fails())->toBeTrue();
});

test('SearchAirportsRequest passes with limit at configured max page size', function (): void {
    expect(validateSearchAirports(['limit' => (string) config('phpvms.pagination.max')])->passes())->toBeTrue();
});
