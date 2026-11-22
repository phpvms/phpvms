<?php

declare(strict_types=1);

use App\Enums\PirepPhase;
use App\Enums\UserState;
use App\Models\User;
use App\Support\Days;

test('a 7.x theme comparing a user state to the old enum name still matches', function (): void {
    $user = User::factory()->make(['state' => UserState::ON_LEAVE]);

    expect($user->state === constant('App\Models\Enums\UserState::ON_LEAVE'))->toBeTrue();
});

test('the old enum names resolve to their 8.x classes', function (): void {
    expect(new ReflectionClass('App\Models\Enums\PirepStatus')->getName())->toBe(PirepPhase::class)
        ->and(constant('App\Models\Enums\Days::FRIDAY'))->toBe(Days::FRIDAY)
        ->and(class_exists('App\Models\Enums\NotAnEnum'))->toBeFalse();
});
