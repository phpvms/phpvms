<?php

declare(strict_types=1);

use App\Auth\InstallSafeUserProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

it('registers the install-safe provider for the users provider', function (): void {
    expect(Auth::createUserProvider('users'))->toBeInstanceOf(InstallSafeUserProvider::class);
});

it('resolves a user normally when the users table exists', function (): void {
    $user = User::factory()->create();

    $resolved = Auth::createUserProvider('users')->retrieveById($user->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->getAuthIdentifier())->toBe($user->id);
});

it('returns null instead of throwing when the users table is missing (fresh/wiped DB)', function (): void {
    // Simulate a wiped database while a stale session cookie still references a
    // user id: dropping the table must not crash auth resolution.
    Schema::disableForeignKeyConstraints();
    Schema::drop('users');
    Schema::enableForeignKeyConstraints();

    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Auth::createUserProvider('users')->retrieveById(1))->toBeNull();
});
