<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\assertDatabaseHas;

test('the password broker stores reset tokens in the migrated table', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $status = Password::broker()->sendResetLink(['email' => $user->email]);

    expect($status)->toBe(Password::RESET_LINK_SENT);
    assertDatabaseHas(config('auth.passwords.users.table'), ['email' => $user->email]);
    Notification::assertSentTo($user, ResetPassword::class);
});
