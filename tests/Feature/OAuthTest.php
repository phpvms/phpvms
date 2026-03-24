<?php

use App\Models\Enums\UserState;
use App\Models\User;
use App\Models\UserOAuthToken;
use App\Services\OAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;

beforeEach(function () {
    // Create a default user to prevent redirection to the installer
    User::factory()->create();

    $this->drivers = [
        'ivao',
        'vatsim',
        'discord',
    ];

    foreach ($this->drivers as $driver) {
        Config::set('services.'.$driver.'.enabled', true);
    }
});

/**
 * Simulate what would be returned by the OAuth provider
 */
function getMockedProvider(): LegacyMockInterface|MockInterface
{
    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User')
        ->allows([
            'getId'     => 123456789,
            'getName'   => 'OAuth user',
            'getEmail'  => 'oauth.user@phpvms.net',
            'getAvatar' => 'https://en.gravatar.com/userimage/12856995/aa6c0527a723abfd5fb9e246f0ff8af4.png',
        ]);

    $abstractUser->token = 'token';
    $abstractUser->refreshToken = 'refresh_token';
    $abstractUser->expiresIn = 3600 * 24 * 7;

    return Mockery::mock('Laravel\Socialite\Contracts\Provider')
        ->allows([
            'refreshToken' => $abstractUser,
            'user'         => $abstractUser,
        ]);
}

test('link account from profile', function () {
    $user = User::factory()->create([
        'name'  => 'OAuth user',
        'email' => 'oauth.user@phpvms.net',
    ]);
    Auth::login($user);

    foreach ($this->drivers as $driver) {
        Socialite::shouldReceive('driver')->with($driver)->andReturn(getMockedProvider());

        $this->get(route('oauth.callback', ['provider' => $driver]))
            ->assertRedirect(route('frontend.profile.index'));

        $user->refresh();
        expect($user->{$driver.'_id'})->toEqual(123456789);

        $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

        expect($tokens)->not->toBeNull()
            ->and($tokens->token)->toEqual('token')
            ->and($tokens->refresh_token)->toEqual('refresh_token')
            ->and($tokens->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();
    }
});

test('link account from login', function () {
    $now = now()->setMicro(0);
    Carbon::setTestNow($now);

    $user = User::factory()->create([
        'name'  => 'OAuth user',
        'email' => 'oauth.user@phpvms.net',
    ]);

    foreach ($this->drivers as $driver) {
        Socialite::shouldReceive('driver')->with($driver)->andReturn(getMockedProvider());

        $this->get(route('oauth.callback', ['provider' => $driver]))
            ->assertRedirect(route('frontend.dashboard.index'));

        $user->refresh();
        expect($user->{$driver.'_id'})->toEqual(123456789)
            ->and($user->lastlogin_at)->toEqual($now);

        $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

        expect($tokens)->not->toBeNull()
            ->and($tokens->token)->toEqual('token')
            ->and($tokens->refresh_token)->toEqual('refresh_token')
            ->and($tokens->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();

        Auth::logout();
    }
});

test('login with linked account', function () {
    $now = now()->setMicro(0);
    Carbon::setTestNow($now);

    $user = User::factory()->create([
        'name'  => 'OAuth user',
        'email' => 'oauth.user@phpvms.net',
    ]);

    foreach ($this->drivers as $driver) {
        $user->update([
            $driver.'_id' => 123456789,
        ]);

        UserOAuthToken::create([
            'user_id'       => $user->id,
            'provider'      => $driver,
            'token'         => 'token',
            'refresh_token' => 'refresh_token',
        ]);

        Socialite::shouldReceive('driver')->with($driver)->andReturn(getMockedProvider());

        $this->get(route('oauth.callback', ['provider' => $driver]))
            ->assertRedirect(route('frontend.dashboard.index'));

        $user->refresh();
        expect($user->{$driver.'_id'})->toEqual(123456789)
            ->and($user->lastlogin_at)->toEqual($now);

        $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

        expect($tokens)->not->toBeNull()
            ->and($tokens->token)->toEqual('token')
            ->and($tokens->refresh_token)->toEqual('refresh_token');

        Auth::logout();
    }
});

test('login with pending account', function () {
    $user = User::factory()->create([
        'name'  => 'OAuth user',
        'email' => 'oauth.user@phpvms.net',
        'state' => UserState::PENDING,
    ]);

    foreach ($this->drivers as $driver) {
        Socialite::shouldReceive('driver')->with($driver)->andReturn(getMockedProvider());

        $this->get(route('oauth.callback', ['provider' => $driver]))
            ->assertViewIs('auth.pending');
    }
});

test('no account found', function () {
    foreach ($this->drivers as $driver) {
        Socialite::shouldReceive('driver')->with($driver)->andReturn(getMockedProvider());

        $this->get(route('oauth.callback', ['provider' => $driver]))
            ->assertRedirect(url('/login'));
    }
});

test('unlink account', function () {
    $user = User::factory()->create([
        'name'  => 'OAuth user',
        'email' => 'oauth.user@phpvms.net',
    ]);

    foreach ($this->drivers as $driver) {
        $user->update([
            $driver.'_id' => 123456789,
        ]);

        Auth::login($user);

        $this->get(route('oauth.logout', ['provider' => $driver]))
            ->assertRedirect(route('frontend.profile.index'));

        $user->refresh();
        expect($user->{$driver.'_id'})->toBeEmpty();
    }
});

test('non existing provider', function () {
    $this->get(route('oauth.redirect', ['provider' => 'aze']))
        ->assertStatus(404);

    $this->get(route('oauth.callback', ['provider' => 'aze']))
        ->assertStatus(404);
});

test('disabled provider', function () {
    $originalConfigValue = config('services.discord.enabled');
    Config::set('services.discord.enabled', false);

    $this->get(route('oauth.redirect', ['provider' => 'discord']))
        ->assertStatus(404);
    $this->get(route('oauth.callback', ['provider' => 'discord']))
        ->assertStatus(404);

    Config::set('services.discord.enabled', $originalConfigValue);
});

test('refresh expired oauth token', function () {
    $user = User::factory()->create([
        'name'  => 'OAuth user',
        'email' => 'oauth.user@phpvms.net',
    ]);

    foreach ($this->drivers as $driver) {
        $user->update([
            $driver.'_id' => 123456789,
        ]);

        UserOAuthToken::updateOrCreate(
            [
                'user_id'  => $user->id,
                'provider' => $driver,
            ],
            [
                'token'         => 'expired_token',
                'refresh_token' => 'old_refresh_token',
                'expires_at'    => ($driver === 'ivao') ? now()->subWeek() : now()->addHour(),
            ]);

        Socialite::shouldReceive('driver')->with($driver)->andReturn(getMockedProvider());

        app(OAuthService::class)->refreshTokensBeforeTheyExpire();

        $user->refresh();
        $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

        expect($tokens)->not->toBeNull()
            ->and($tokens->token)->toEqual('token')
            ->and($tokens->refresh_token)->toEqual('refresh_token')
            ->and($tokens->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();
    }
});
