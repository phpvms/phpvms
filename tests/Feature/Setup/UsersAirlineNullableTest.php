<?php

declare(strict_types=1);

use App\Models\User;

it('lets a user exist without an airline', function (): void {
    $user = User::factory()->create(['airline_id' => null]);

    expect(User::query()->whereKey($user->id)->value('airline_id'))->toBeNull();
});
