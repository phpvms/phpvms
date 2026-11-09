<?php

declare(strict_types=1);

use App\Features\Profile\Actions\UpdateProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('clears verification and sends the verify mail when the email changes', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    app(UpdateProfile::class)->handle(
        $user,
        ['name' => $user->name, 'email' => 'new-address@example.com', 'airline_id' => $user->airline_id],
        null,
        [],
    );

    expect($user->refresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('keeps verification and sends no mail when the email is unchanged', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    app(UpdateProfile::class)->handle(
        $user,
        ['name' => 'Other Name', 'email' => $user->email, 'airline_id' => $user->airline_id],
        null,
        [],
    );

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});
