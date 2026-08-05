<?php

use App\Exceptions\PilotIdRangeExhausted;
use App\Exceptions\UserPilotIdExists;
use App\Models\User;
use App\Services\UserService;

test('range enabled assigns starting from range start', function (): void {
    User::factory()->create(['pilot_id' => 4]);

    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', 100);
    setting_save('pilots.id_range_end', 999);

    $user = User::factory()->create();

    expect($user->pilot_id)->toEqual(100);
});

test('gap fill assigns lowest free id, off assigns max plus one', function (): void {
    User::factory()->create(['pilot_id' => 1]);
    User::factory()->create(['pilot_id' => 2]);
    User::factory()->create(['pilot_id' => 4]);

    setting_save('pilots.id_fill_gaps', true);
    $user = User::factory()->create();
    expect($user->pilot_id)->toEqual(3);

    setting_save('pilots.id_fill_gaps', false);
    $user = User::factory()->create();
    expect($user->pilot_id)->toEqual(5);
});

test('reuse deleted on fills a trashed users gap, off skips it', function (): void {
    User::factory()->create(['pilot_id' => 1]);
    User::factory()->create(['pilot_id' => 2]);
    User::factory()->create(['pilot_id' => 4]);
    $trashed = User::factory()->create(['pilot_id' => 3]);
    $trashed->delete();

    setting_save('pilots.id_fill_gaps', true);
    setting_save('pilots.id_reuse_deleted', true);
    $user = app(UserService::class)->getNextAvailablePilotId();
    expect($user)->toEqual(3);

    setting_save('pilots.id_reuse_deleted', false);
    $user = app(UserService::class)->getNextAvailablePilotId();
    expect($user)->toEqual(5);
});

test('change pilot id to a trashed users id honors reuse deleted setting', function (): void {
    $trashed = User::factory()->create(['pilot_id' => 5]);
    $trashed->delete();

    $user = User::factory()->create();

    setting_save('pilots.id_reuse_deleted', false);
    expect(fn () => app(UserService::class)->changePilotId($user, 5))->toThrow(UserPilotIdExists::class);

    setting_save('pilots.id_reuse_deleted', true);
    $user = app(UserService::class)->changePilotId($user, 5);
    expect($user->pilot_id)->toEqual(5);
});

test('full range throws and does not save a partial pilot id', function (): void {
    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', 1);
    setting_save('pilots.id_range_end', 2);

    User::factory()->create(['pilot_id' => 1]);
    User::factory()->create(['pilot_id' => 2]);

    expect(fn () => User::factory()->create())->toThrow(PilotIdRangeExhausted::class);
    expect(User::whereNull('pilot_id')->exists())->toBeTrue();
});

test('enabling a range leaves existing out of range pilots untouched', function (): void {
    $user1 = User::factory()->create(['pilot_id' => 1]);
    $user2 = User::factory()->create(['pilot_id' => 2]);

    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', 100);
    setting_save('pilots.id_range_end', 999);

    expect($user1->refresh()->pilot_id)->toEqual(1)
        ->and($user2->refresh()->pilot_id)->toEqual(2);
});

test('a grandfathered pilot above the range does not exhaust it', function (): void {
    User::factory()->create(['pilot_id' => 1200]);

    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', 100);
    setting_save('pilots.id_range_end', 999);
    setting_save('pilots.id_fill_gaps', false);

    $user = User::factory()->create();

    expect($user->pilot_id)->toEqual(100);
});

test('createUser rolls back the user row when the range is exhausted', function (): void {
    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', 1);
    setting_save('pilots.id_range_end', 1);

    User::factory()->create(['pilot_id' => 1]);

    $attrs = User::factory()->make(['email' => 'exhausted@example.com'])->getAttributes();

    expect(fn () => app(UserService::class)->createUser($attrs))->toThrow(PilotIdRangeExhausted::class);
    expect(User::where('email', 'exhausted@example.com')->exists())->toBeFalse();
});

test('an empty range start setting never assigns pilot id 0', function (): void {
    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', '');
    setting_save('pilots.id_range_end', 999);

    $user = User::factory()->create();

    expect($user->pilot_id)->toBeGreaterThanOrEqual(1);
});
