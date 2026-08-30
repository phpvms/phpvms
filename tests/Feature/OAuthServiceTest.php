<?php

declare(strict_types=1);

use App\Features\OAuth\OAuthService;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserOAuthToken;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('oauth token refresh resolves the socialite driver from its connection', function (): void {
    OAuthConnection::query()->updateOrCreate([
        'connection_id' => 'discord',
    ], [
        'display_name'  => 'Discord',
        'provider'      => 'discord',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'enabled'       => true,
    ]);

    $user = User::factory()->create();
    $token = UserOAuthToken::query()->create([
        'user_id'       => $user->id,
        'connection_id' => 'discord',
        'token'         => 'expired-token',
        'refresh_token' => 'refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    $updatedToken = new SocialiteUser();
    $updatedToken->token = 'new-token';
    $updatedToken->refreshToken = 'new-refresh-token';
    $updatedToken->expiresIn = 3600;

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('refreshToken')
        ->once()
        ->with('refresh-token')
        ->andReturn($updatedToken);
    Socialite::shouldReceive('extend')->once()->with('discord', Mockery::type(Closure::class));
    Socialite::shouldReceive('driver')->once()->with('discord')->andReturn($provider);

    $refreshed = app(OAuthService::class)->refreshToken($token);

    expect($refreshed->connection_id)->toBe('discord')
        ->and($refreshed->token)->toBe('new-token')
        ->and($refreshed->refresh_token)->toBe('new-refresh-token');
});
