<?php

declare(strict_types=1);

use App\Http\Requests\SearchFlightsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

function validateSearchFlights(array $data): array
{
    $rules = (new SearchFlightsRequest())->rules();
    $validator = Validator::make($data, $rules);

    return $validator->errors()->toArray();
}

function resolveSearchFlights(array $data): SearchFlightsRequest
{
    $request = SearchFlightsRequest::createFrom(Request::create('/api/flights', 'GET', $data));
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    return $request;
}

test('SearchFlightsRequest accepts plain search string', function () {
    expect(validateSearchFlights(['search' => 'JFK']))->toBe([]);
});

test('SearchFlightsRequest rejects search longer than 255 chars', function () {
    $errors = validateSearchFlights(['search' => str_repeat('a', 256)]);

    expect($errors)->toHaveKey('search');
});

test('SearchFlightsRequest accepts sortable time columns', function () {
    expect(validateSearchFlights(['orderBy' => 'dpt_time']))->toBe([])
        ->and(validateSearchFlights(['orderBy' => 'arr_time']))->toBe([]);
});

test('SearchFlightsRequest accepts legacy multi-column orderBy syntax', function () {
    expect(validateSearchFlights([
        'orderBy'  => 'flight_number;route_code',
        'sortedBy' => 'asc;desc',
    ]))->toBe([]);
});

test('SearchFlightsRequest preserves sortable aliases after validation', function () {
    $request = resolveSearchFlights([
        'sort'      => 'dpt_time',
        'direction' => 'desc',
    ]);

    expect($request->input('sort'))->toBe('dpt_time')
        ->and($request->input('direction'))->toBe('desc')
        ->and($request->input('orderBy'))->toBe('dpt_time')
        ->and($request->input('sortedBy'))->toBe('desc');
});

test('SearchFlightsRequest keeps explicit orderBy over sortable alias', function () {
    $request = resolveSearchFlights([
        'sort'      => 'dpt_time',
        'direction' => 'desc',
        'orderBy'   => 'flight_number',
        'sortedBy'  => 'asc',
    ]);

    expect($request->input('sort'))->toBe('dpt_time')
        ->and($request->input('direction'))->toBe('desc')
        ->and($request->input('orderBy'))->toBe('flight_number')
        ->and($request->input('sortedBy'))->toBe('asc');
});
