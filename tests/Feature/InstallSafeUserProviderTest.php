<?php

declare(strict_types=1);

use App\Auth\InstallSafeUserProvider;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticatable model pointed at a table that does not exist, to exercise the
 * "users table missing" path without dropping the real table (Postgres refuses
 * to drop a table with dependent foreign keys, and DDL breaks the test
 * transaction on MySQL).
 */
class MissingTableUser extends Authenticatable
{
    protected $table = 'a_users_table_that_does_not_exist';
}

it('registers the install-safe provider for the users provider', function (): void {
    expect(Auth::createUserProvider('users'))->toBeInstanceOf(InstallSafeUserProvider::class);
});

it('resolves a user normally when the users table exists', function (): void {
    $user = User::factory()->create();

    $resolved = Auth::createUserProvider('users')->retrieveById($user->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->getAuthIdentifier())->toBe($user->id);
});

it('returns null instead of throwing when the backing table is missing (fresh/wiped DB)', function (): void {
    // A stale session cookie references a user id, but the table isn't there yet.
    $provider = new InstallSafeUserProvider(app('hash'), MissingTableUser::class);

    expect($provider->retrieveById(1))->toBeNull();
});
