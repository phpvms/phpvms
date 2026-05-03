<?php

declare(strict_types=1);

use App\Http\Requests\SearchUsersRequest;
use Illuminate\Support\Facades\Validator;

function validateSearchUsers(array $data): array
{
    $rules = (new SearchUsersRequest())->rules();
    $validator = Validator::make($data, $rules);

    return $validator->errors()->toArray();
}

test('SearchUsersRequest accepts empty payload', function () {
    expect(validateSearchUsers([]))->toBe([]);
});

test('SearchUsersRequest accepts plain search string', function () {
    expect(validateSearchUsers(['search' => 'John']))->toBe([]);
});

test('SearchUsersRequest accepts field-prefixed search', function () {
    expect(validateSearchUsers(['search' => 'name:John;email:foo']))->toBe([]);
});

test('SearchUsersRequest rejects search longer than 255 chars', function () {
    $errors = validateSearchUsers(['search' => str_repeat('a', 256)]);
    expect($errors)->toHaveKey('search');
});

test('SearchUsersRequest accepts allowlisted orderBy', function () {
    foreach (SearchUsersRequest::ORDERABLE_FIELDS as $col) {
        expect(validateSearchUsers(['orderBy' => $col]))->toBe([], "orderBy=$col rejected");
    }
});

test('SearchUsersRequest rejects non-allowlisted orderBy', function () {
    $errors = validateSearchUsers(['orderBy' => 'password']);
    expect($errors)->toHaveKey('orderBy');
});

test('SearchUsersRequest accepts asc/desc on sortedBy', function () {
    expect(validateSearchUsers(['sortedBy' => 'asc']))->toBe([]);
    expect(validateSearchUsers(['sortedBy' => 'desc']))->toBe([]);
});

test('SearchUsersRequest rejects unknown sortedBy', function () {
    $errors = validateSearchUsers(['sortedBy' => 'foo']);
    expect($errors)->toHaveKey('sortedBy');
});

test('SearchUsersRequest accepts integer state', function () {
    expect(validateSearchUsers(['state' => 1]))->toBe([]);
});

test('SearchUsersRequest rejects non-integer state', function () {
    $errors = validateSearchUsers(['state' => 'notanumber']);
    expect($errors)->toHaveKey('state');
});

test('SearchUsersRequest accepts integer airline_id', function () {
    expect(validateSearchUsers(['airline_id' => 7]))->toBe([]);
});

test('SearchUsersRequest accepts integer page and limit', function () {
    expect(validateSearchUsers(['page' => 2, 'limit' => 25]))->toBe([]);
});

test('SearchUsersRequest rejects limit > 100', function () {
    $errors = validateSearchUsers(['limit' => 500]);
    expect($errors)->toHaveKey('limit');
});
