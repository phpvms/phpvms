<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\UserService;

/*
 * Exercises UserService::getUserFields semantics.
 *
 * Originated as a Phase 0 safety net before the method was absorbed
 * out of UserRepository into UserService. Assertions are unchanged
 * from the repository-era contract.
 */

function userFieldsService(): UserService
{
    return app(UserService::class);
}

/**
 * Build a fresh user with its `fields` relation eager-loaded so the
 * method's internal `$user->fields` / `$userFieldValue->field` walk
 * does not trip preventLazyLoading in tests.
 */
function userFieldsMakeUserWithFields(): User
{
    /** @var User $user */
    $user = User::factory()->create();

    return $user->fresh(['fields.field']);
}

test('returns only public fields when only public true', function () {
    /** @var UserField $public */
    $public = UserField::create([
        'name'     => 'Discord Username',
        'private'  => false,
        'internal' => false,
    ]);
    UserField::create([
        'name'     => 'Home Address',
        'private'  => true,
        'internal' => false,
    ]);

    $user = userFieldsMakeUserWithFields();

    $results = userFieldsService()->getUserFields($user, true);

    expect($results)->toHaveCount(1);
    /** @var UserField $first */
    $first = $results->first();
    expect($first->slug)->toEqual($public->slug);
});

test('returns only private fields when only public false', function () {
    UserField::create([
        'name'     => 'Discord Username',
        'private'  => false,
        'internal' => false,
    ]);
    /** @var UserField $private */
    $private = UserField::create([
        'name'     => 'Home Address',
        'private'  => true,
        'internal' => false,
    ]);

    $user = userFieldsMakeUserWithFields();

    $results = userFieldsService()->getUserFields($user, false);

    expect($results)->toHaveCount(1);
    /** @var UserField $first */
    $first = $results->first();
    expect($first->slug)->toEqual($private->slug);
});

test('returns all non internal fields when only public null', function () {
    UserField::create([
        'name'     => 'Discord Username',
        'private'  => false,
        'internal' => false,
    ]);
    UserField::create([
        'name'     => 'Home Address',
        'private'  => true,
        'internal' => false,
    ]);
    UserField::create([
        'name'     => 'Internal Notes',
        'private'  => false,
        'internal' => true,
    ]);

    $user = userFieldsMakeUserWithFields();

    $results = userFieldsService()->getUserFields($user);

    // Both non-internal fields (one public, one private) regardless of `private`.
    expect($results)->toHaveCount(2);
    foreach ($results as $field) {
        expect((bool) $field->internal)->toBeFalse();
    }
});

test('with internal fields true includes internal', function () {
    UserField::create([
        'name'     => 'Discord Username',
        'private'  => false,
        'internal' => false,
    ]);
    UserField::create([
        'name'     => 'Internal Notes',
        'private'  => false,
        'internal' => true,
    ]);

    $user = userFieldsMakeUserWithFields();

    $results = userFieldsService()->getUserFields($user, null, true);

    expect($results)->toHaveCount(2);
});

test('populates field value from user field values', function () {
    /** @var UserField $field */
    $field = UserField::create([
        'name'     => 'Discord Username',
        'private'  => false,
        'internal' => false,
    ]);

    /** @var User $user */
    $user = User::factory()->create();

    UserFieldValue::create([
        'user_field_id' => $field->id,
        'user_id'       => $user->id,
        'value'         => 'pilot#1234',
    ]);

    // Reload with the relations the method walks so preventLazyLoading
    // doesn't trip on $user->fields or $userFieldValue->field.
    $user = $user->fresh(['fields.field']);

    $results = userFieldsService()->getUserFields($user, true);

    expect($results)->toHaveCount(1);
    /** @var UserField $first */
    $first = $results->first();
    expect($first->value)->toEqual('pilot#1234');
});
