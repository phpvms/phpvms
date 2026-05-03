<?php

declare(strict_types=1);

use App\Http\Requests\SearchUsersRequest;
use App\Models\Airline;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Queries\UserSearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

function userSearchRequest(array $params): SearchUsersRequest
{
    $req = SearchUsersRequest::createFromBase(Request::create('/users', 'GET', $params));
    $req->setContainer(app())->setRedirector(app('redirect'));
    $req->validateResolved();

    return $req;
}

test('UserSearchQuery returns all users when no params given', function () {
    updateSetting('pilots.hide_inactive', false);
    User::factory()->count(3)->create(['state' => UserState::ACTIVE]);

    $request = userSearchRequest([]);
    $query = (new UserSearchQuery())->build($request);

    expect($query->count())->toBe(3);
});

test('UserSearchQuery filters by free-text search across name and email', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'John Doe',  'email' => 'a@b.com', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@x.com', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'Bob',       'email' => 'john@xyz', 'state' => UserState::ACTIVE]);

    $request = userSearchRequest(['search' => 'John']);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Bob', 'John Doe']); // John Doe by name, Bob by email
});

test('UserSearchQuery filters by field-specific search', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'John Doe',  'email' => 'a@b.com',     'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'Bob Smith', 'email' => 'john@x.com',  'state' => UserState::ACTIVE]);

    $request = userSearchRequest(['search' => 'name:John']);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->all();

    expect($names)->toBe(['John Doe']);
});

test('UserSearchQuery joins multi-pair field-specific search with OR', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'John Doe',   'email' => 'unrelated@x.com', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'Other',      'email' => 'foo@bar.com',     'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'NoMatch',    'email' => 'baz@qux.com',     'state' => UserState::ACTIVE]);

    // Either name LIKE %John% OR email LIKE %foo% — first two should match.
    $request = userSearchRequest(['search' => 'name:John;email:foo']);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['John Doe', 'Other']);
});

test('UserSearchQuery falls back to free-text when colon search has no allowlisted fields', function () {
    updateSetting('pilots.hide_inactive', false);

    // Search payload `8:30` contains a colon but no field key matches FIELD_SEARCH allowlist.
    // Old code silently returned all rows; new code treats the whole string as free-text.
    User::factory()->create(['name' => 'foo 8:30 bar', 'email' => 'a@b.com',  'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'unrelated',    'email' => 'c@d.com',  'state' => UserState::ACTIVE]);

    $request = userSearchRequest(['search' => '8:30']);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->all();

    expect($names)->toBe(['foo 8:30 bar']);
});

test('UserSearchQuery applies pilots.hide_inactive setting', function () {
    updateSetting('pilots.hide_inactive', true);

    User::factory()->create(['name' => 'a', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'p', 'state' => UserState::PENDING]);
    User::factory()->create(['name' => 'r', 'state' => UserState::REJECTED]);

    $request = userSearchRequest([]);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['a']);
});

test('UserSearchQuery filters by explicit state param', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'a', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'p', 'state' => UserState::PENDING]);

    $request = userSearchRequest(['state' => UserState::PENDING]);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->all();

    expect($names)->toBe(['p']);
});

test('UserSearchQuery filters by airline_id', function () {
    updateSetting('pilots.hide_inactive', false);
    $airline = Airline::factory()->create();
    $other = Airline::factory()->create();

    User::factory()->create(['name' => 'a', 'airline_id' => $airline->id, 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'b', 'airline_id' => $other->id,   'state' => UserState::ACTIVE]);

    $request = userSearchRequest(['airline_id' => $airline->id]);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->all();

    expect($names)->toBe(['a']);
});

test('UserSearchQuery applies orderBy + sortedBy', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'Charlie', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'Alice',   'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'Bob',     'state' => UserState::ACTIVE]);

    $request = userSearchRequest(['orderBy' => 'name', 'sortedBy' => 'asc']);
    $names = (new UserSearchQuery())->build($request)->pluck('name')->all();

    expect($names)->toBe(['Alice', 'Bob', 'Charlie']);
});

test('UserSearchQuery returns a Builder, not a collection', function () {
    updateSetting('pilots.hide_inactive', false);
    User::factory()->create(['state' => UserState::ACTIVE]);

    $request = userSearchRequest([]);
    $result = (new UserSearchQuery())->build($request);

    expect($result)->toBeInstanceOf(Builder::class);
});

test('UserSearchQuery includes default eager loads (airline, current_airport, fields, home_airport, rank, awards count)', function () {
    updateSetting('pilots.hide_inactive', false);
    User::factory()->create(['state' => UserState::ACTIVE]);

    $request = userSearchRequest([]);
    $user = (new UserSearchQuery())->build($request)->first();

    // Awards count should be loaded
    expect($user->getAttribute('awards_count'))->not->toBeNull();
    // Eager-loaded relations should be in the loaded array
    expect($user->relationLoaded('airline'))->toBeTrue();
    expect($user->relationLoaded('home_airport'))->toBeTrue();
    expect($user->relationLoaded('current_airport'))->toBeTrue();
    expect($user->relationLoaded('rank'))->toBeTrue();
    expect($user->relationLoaded('fields'))->toBeTrue();
});
