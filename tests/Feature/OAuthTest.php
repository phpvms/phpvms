<?php

declare(strict_types=1);

use App\Features\OAuth\Helpers\ExternalAuthSessionService;
use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\ExternalAuthSession;
use App\Models\Invite;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserOAuthToken;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\Contracts\Helpers\ConfigRetrieverInterface;
use SocialiteProviders\Manager\SocialiteWasCalled;

beforeEach(function (): void {
    User::factory()->create();
    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', false);
});

function oauthConnection(array $attributes = []): OAuthConnection
{
    $connection = OAuthConnection::query()->where('connection_id', 'discord')->firstOrFail();
    $connection->update([
        'display_name'         => 'Discord Crew',
        'client_id'            => 'client-id',
        'client_secret'        => 'client-secret',
        'enabled'              => true,
        'login_enabled'        => true,
        'registration_enabled' => true,
        'linking_enabled'      => true,
        ...$attributes,
    ]);

    return $connection->refresh();
}

function oauthUser(
    string $subject = 'subject-123',
    string $email = 'oauth.user@phpvms.net',
    bool $emailVerified = true,
): SocialiteUser {
    return new SocialiteUser()
        ->setRaw([
            'sub'            => $subject,
            'email'          => $email,
            'email_verified' => $emailVerified,
        ])
        ->map([
            'id'    => $subject,
            'name'  => 'OAuth User',
            'email' => $email,
        ])
        ->setToken('access-token')
        ->setRefreshToken('refresh-token')
        ->setExpiresIn(3600);
}

function mockOAuthCallback(SocialiteUser $user, string $driver = 'discord'): void
{
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('user')->once()->andReturn($user);
    Socialite::shouldReceive('extend')->zeroOrMoreTimes();
    Socialite::shouldReceive('driver')->with($driver)->once()->andReturn($provider);
}

function mockOAuthRedirect(string $driver = 'discord'): void
{
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('scopes')->zeroOrMoreTimes()->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturn(
        new RedirectResponse('https://provider.example/authorize?state=social-state'),
    );
    Socialite::shouldReceive('extend')->zeroOrMoreTimes();
    Socialite::shouldReceive('driver')->with($driver)->once()->andReturn($provider);
}

/** @return array<string, mixed> */
function oauthFlow(string $intent = 'login', string $connection = 'discord', ?Invite $invite = null): array
{
    return [
        'state' => 'social-state',
        'oauth' => [
            'flows' => [
                'social-state' => [
                    'connection_id' => $connection,
                    'intent'        => $intent,
                    'invite'        => $invite?->id,
                    'invite_token'  => $invite?->token,
                    'expires_at'    => now()->addMinutes(5)->getTimestamp(),
                ],
            ],
        ],
    ];
}

/** @return array<string, mixed> */
function pendingOAuthIdentity(
    string $connection = 'discord',
    string $subject = 'subject-123',
    ?string $oidcSid = null,
    string $email = 'oauth.user@phpvms.net',
): array {
    $oauthConnection = OAuthConnection::query()->where('connection_id', $connection)->firstOrFail();
    $issuer = $oauthConnection->configuration['base_url'] ?? null;

    return [
        'oauth' => [
            'pending_identity' => [
                'connection_id'        => $connection,
                'connection_record_id' => $oauthConnection->getKey(),
                'provider'             => $oauthConnection->provider,
                'issuer'               => is_string($issuer) ? rtrim($issuer, '/') : null,
                'provider_user_id'     => $subject,
                'oidc_sid'             => $oidcSid,
                'name'                 => 'OAuth User',
                'email'                => $email,
                'email_verified'       => true,
                'registration_attempt' => 'registration-attempt',
                'token'                => Crypt::encryptString('access-token'),
                'refresh_token'        => Crypt::encryptString('refresh-token'),
                'token_expires_at'     => now()->addHour()->toIso8601String(),
                'expires_at'           => now()->addMinutes(5)->getTimestamp(),
            ],
        ],
    ];
}

/** @return array<string, mixed> */
function oauthRegistrationPayload(string $email, ?string $attempt = 'registration-attempt'): array
{
    $payload = [
        'name'            => 'New OAuth Pilot',
        'email'           => $email,
        'airline_id'      => Airline::factory()->create()->id,
        'home_airport_id' => Airport::factory()->create()->id,
        'toc_accepted'    => true,
        ...($attempt === null ? [] : ['oauth_registration' => $attempt]),
    ];

    if ($attempt === null) {
        $payload['password'] = 'secret';
        $payload['password_confirmation'] = 'secret';
    }

    return $payload;
}

test('a known provider subject signs in its linked pilot', function (): void {
    $connection = oauthConnection();
    $user = User::factory()->create(['email' => 'pilot@phpvms.net']);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);
    mockOAuthCallback(oauthUser(email: 'different@phpvms.net'));

    $response = $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertRedirect(route('frontend.dashboard.index'));

    $response->assertCookie(Auth::guard()->getRecallerName());
    $this->assertAuthenticatedAs($user);
    expect(UserOAuthToken::query()
        ->where('user_id', $user->id)
        ->where('connection_id', 'discord')
        ->exists())->toBeTrue();
});

test('OIDC login cannot be restored after its recorded session is revoked', function (): void {
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();
    $this->app->instance(
        OAuthConnectionService::class,
        new OAuthConnectionService(new SocialiteProviderRegistry(static fn (): bool => true)),
    );
    $socialiteManager = Mockery::mock(SocialiteWasCalled::class);
    $socialiteManager->shouldReceive('extendSocialite')->once();
    $this->app->instance(SocialiteWasCalled::class, $socialiteManager);

    $connection = OAuthConnection::query()->create([
        'connection_id' => 'crew-sso',
        'display_name'  => 'Crew SSO',
        'provider'      => 'openidconnect',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'scopes'        => ['openid', 'profile', 'email'],
        'configuration' => ['base_url' => 'https://issuer.example.com'],
        'enabled'       => true,
        'login_enabled' => true,
    ]);
    $user = User::factory()->create();
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);
    $providerUser = oauthUser();
    $providerUser->setRaw([...$providerUser->getRaw(), 'sid' => 'oidc-session']);
    mockOAuthCallback($providerUser, 'oidc_crew-sso');
    $rememberToken = $user->getRememberToken();

    $response = $this->withSession(oauthFlow(connection: 'crew-sso'))
        ->get(route('oauth.callback', ['provider' => 'crew-sso', 'state' => 'social-state']))
        ->assertRedirect(route('frontend.dashboard.index'));

    $response->assertCookieMissing(Auth::guard()->getRecallerName());

    expect($user->fresh()->getRememberToken())->toBe($rememberToken);
    $externalSession = ExternalAuthSession::query()->firstOrFail();
    expect(app(ExternalAuthSessionService::class)->revoke(
        $connection,
        'oidc-session',
        'subject-123',
    ))->toBe(1);

    Auth::forgetGuards();
    app(SessionManager::class)->forgetDrivers();
    $this->app->forgetInstance('session.store');

    $request = HttpRequest::create(route('frontend.dashboard.index'), 'GET');
    $followUpResponse = app(HttpKernel::class)->handle($request);
    expect($followUpResponse->getStatusCode())->toBe(302)
        ->and($followUpResponse->headers->get('Location'))->toBe(url('/login'));
    app(HttpKernel::class)->terminate($request, $followUpResponse);
    expect(ExternalAuthSession::query()->whereKey($externalSession->id)->exists())->toBeFalse();
});

test('runtime provider registration works without the manager config retriever binding', function (): void {
    $this->app->offsetUnset(ConfigRetrieverInterface::class);
    $this->app->instance(
        OAuthConnectionService::class,
        new OAuthConnectionService(new SocialiteProviderRegistry(static fn (): bool => true)),
    );
    OAuthConnection::query()->create([
        'connection_id' => 'crew-sso',
        'display_name'  => 'Crew SSO',
        'provider'      => 'openidconnect',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'configuration' => ['base_url' => 'https://issuer.example.com'],
        'enabled'       => true,
        'login_enabled' => true,
    ]);

    expect(fn () => app(OAuthConnectionService::class)->resolve('crew-sso'))
        ->not->toThrow(BindingResolutionException::class);
});

test('an unknown subject is never attached by matching email', function (): void {
    oauthConnection();
    User::factory()->create(['email' => 'oauth.user@phpvms.net']);
    mockOAuthCallback(oauthUser());

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk()
        ->assertViewIs('auth.oauth-unknown')
        ->assertSee('We found an existing pilot account using oauth.user@phpvms.net');

    $this->assertGuest();
    expect(UserIdentity::query()->where('provider_user_id', 'subject-123')->exists())->toBeFalse();
});

test('pending provider tokens are encrypted in the session', function (): void {
    $connection = oauthConnection();
    mockOAuthCallback(oauthUser());

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk();

    $pending = session('oauth.pending_identity');
    expect($pending)->toBeArray()
        ->and($pending['token'])->not->toBe('access-token')
        ->and($pending['refresh_token'])->not->toBe('refresh-token')
        ->and(Crypt::decryptString($pending['token']))->toBe('access-token')
        ->and(Crypt::decryptString($pending['refresh_token']))->toBe('refresh-token')
        ->and($pending['connection_record_id'])->toBe($connection->getKey())
        ->and($pending['provider'])->toBe('discord')
        ->and($pending['issuer'])->toBeNull();
});

test('a pending identity cannot be linked after its connection is replaced', function (): void {
    $connection = oauthConnection();
    $pending = pendingOAuthIdentity();
    $user = User::factory()->create();

    $connection->delete();
    OAuthConnection::query()->create([
        'connection_id'   => 'discord',
        'display_name'    => 'Replacement Discord',
        'provider'        => 'discord',
        'client_id'       => 'replacement-client',
        'client_secret'   => 'replacement-secret',
        'enabled'         => true,
        'linking_enabled' => true,
    ]);

    $this->actingAs($user)
        ->withSession($pending)
        ->get(route('oauth.link.complete', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'discord')->exists())->toBeFalse();
});

test('a pending identity cannot cross an OpenID Connect issuer change', function (): void {
    $this->app->instance(
        OAuthConnectionService::class,
        new OAuthConnectionService(new SocialiteProviderRegistry(static fn (): bool => true)),
    );
    $socialiteManager = Mockery::mock(SocialiteWasCalled::class);
    $socialiteManager->shouldReceive('extendSocialite')->once();
    $this->app->instance(SocialiteWasCalled::class, $socialiteManager);
    $connection = OAuthConnection::query()->create([
        'connection_id'   => 'crew-sso',
        'display_name'    => 'Crew SSO',
        'provider'        => 'openidconnect',
        'client_id'       => 'client-id',
        'client_secret'   => 'client-secret',
        'configuration'   => ['base_url' => 'https://issuer.example.com'],
        'enabled'         => true,
        'linking_enabled' => true,
    ]);
    $pending = pendingOAuthIdentity('crew-sso');
    $connection->update(['configuration' => ['base_url' => 'https://replacement.example.com']]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession($pending)
        ->get(route('oauth.link.complete', ['provider' => 'crew-sso']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'crew-sso')->exists())->toBeFalse();
});

test('invite-only login does not offer registration without a valid invite', function (): void {
    updateSetting('general.invite_only_registrations', true);
    oauthConnection();
    mockOAuthCallback(oauthUser());

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk()
        ->assertViewHas('registrationAvailable', false);

    $this->post(route('oauth.register', ['provider' => 'discord']))
        ->assertForbidden();
});

test('an invite survives the unknown-account registration decision', function (): void {
    updateSetting('general.invite_only_registrations', true);
    oauthConnection();
    $invite = Invite::create([
        'token'       => 'invite-token',
        'usage_count' => 0,
    ]);
    mockOAuthCallback(oauthUser());

    $this->withSession(oauthFlow(invite: $invite))
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk()
        ->assertViewHas('registrationAvailable', true);

    $this->post(route('oauth.register', ['provider' => 'discord']))
        ->assertRedirect($invite->link);
});

test('canceling an unknown account clears the pending identity', function (): void {
    Notification::fake();
    oauthConnection();
    mockOAuthCallback(oauthUser());

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk();

    $this->get(route('oauth.cancel'))->assertMethodNotAllowed();

    $this->post(route('oauth.cancel'))
        ->assertRedirect('/login')
        ->assertSessionMissing('oauth.pending_identity');

    $this->post('/register', oauthRegistrationPayload('normal.registration@phpvms.net', null))
        ->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'normal.registration@phpvms.net')->firstOrFail();
    expect($user->identities()->exists())->toBeFalse();
});

test('an expired provider registration cannot create an unlinked account', function (): void {
    Notification::fake();
    oauthConnection();
    $session = pendingOAuthIdentity();
    $session['oauth']['pending_identity']['expires_at'] = now()->subMinute()->getTimestamp();

    $this->withSession($session)
        ->post('/register', oauthRegistrationPayload('expired.oauth@phpvms.net'))
        ->assertRedirect('/login')
        ->assertSessionMissing('oauth.pending_identity');

    expect(User::query()->where('email', 'expired.oauth@phpvms.net')->exists())->toBeFalse();
});

test('a provider registration marker must match its pending attempt', function (): void {
    Notification::fake();
    oauthConnection();

    $this->withSession(pendingOAuthIdentity())
        ->post('/register', oauthRegistrationPayload('mismatched.oauth@phpvms.net', 'wrong-attempt'))
        ->assertRedirect('/login');

    expect(User::query()->where('email', 'mismatched.oauth@phpvms.net')->exists())->toBeFalse();
});

test('an unverified matching email is not shown as an existing-account match', function (): void {
    oauthConnection();
    User::factory()->create(['email' => 'oauth.user@phpvms.net']);
    mockOAuthCallback(oauthUser(emailVerified: false));

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk()
        ->assertViewHas('verifiedEmailMatch', false)
        ->assertDontSee('We found an existing pilot account');
});

test('a logged-in pilot can explicitly link an unowned provider subject', function (): void {
    oauthConnection();
    $user = User::factory()->create();
    mockOAuthCallback(oauthUser());

    $this->actingAs($user)
        ->withSession(oauthFlow('link'))
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertRedirect(route('frontend.profile.index'));

    expect(UserIdentity::query()
        ->where('user_id', $user->id)
        ->where('connection_id', 'discord')
        ->where('provider_user_id', 'subject-123')
        ->exists())->toBeTrue();
});

test('a pilot cannot take a provider subject linked to another pilot', function (): void {
    $connection = oauthConnection();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    UserIdentity::query()->create([
        'user_id'          => $owner->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);
    mockOAuthCallback(oauthUser());

    $this->actingAs($other)
        ->withSession(oauthFlow('link'))
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertRedirect(route('frontend.profile.index'));

    expect(UserIdentity::query()->where('provider_user_id', 'subject-123')->count())->toBe(1)
        ->and($other->identities()->exists())->toBeFalse();
});

test('an unknown identity can prove an existing account with a local sign in', function (): void {
    oauthConnection();
    $user = User::factory()->create([
        'email'    => 'pilot@phpvms.net',
        'password' => Hash::make('secret'),
    ]);
    mockOAuthCallback(oauthUser(email: 'provider@phpvms.net'));

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertOk();

    $this->post(route('oauth.link', ['provider' => 'discord']))
        ->assertRedirect('/login');

    $this->post('/login', ['email' => $user->email, 'password' => 'secret'])
        ->assertRedirect(route('oauth.link.complete', ['provider' => 'discord']));

    $this->get(route('oauth.link.complete', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()
        ->where('connection_id', 'discord')
        ->where('provider_user_id', 'subject-123')
        ->exists())->toBeTrue();
});

test('provider registration uses the pending identity without asking for a password', function (): void {
    Notification::fake();
    oauthConnection();
    $airline = Airline::factory()->create();
    $airport = Airport::factory()->create();

    $this->withSession(pendingOAuthIdentity())
        ->post('/register', [
            'name'               => 'Tampered Name',
            'email'              => 'tampered@phpvms.net',
            'airline_id'         => $airline->id,
            'home_airport_id'    => $airport->id,
            'toc_accepted'       => true,
            'oauth_registration' => 'registration-attempt',
        ])
        ->assertRedirect(route('frontend.profile.index'));

    $user = User::query()->where('email', 'oauth.user@phpvms.net')->firstOrFail();
    expect($user->identities()
        ->where('connection_id', 'discord')
        ->where('provider_user_id', 'subject-123')
        ->exists())->toBeTrue()
        ->and($user->name)->toBe('OAuth User')
        ->and(Hash::check('', $user->password))->toBeFalse();
});

test('provider registration form shows provider identity as text and hides password fields', function (): void {
    oauthConnection();

    $this->withSession(pendingOAuthIdentity())
        ->post(route('oauth.register', ['provider' => 'discord']))
        ->assertRedirect('/register');

    $this->get('/register')
        ->assertOk()
        ->assertSee('Complete the registration to continue your Discord Crew login')
        ->assertSee('OAuth User')
        ->assertSee('oauth.user@phpvms.net')
        ->assertDontSee('name="name"', false)
        ->assertDontSee('name="email"', false)
        ->assertDontSee('name="password"', false)
        ->assertDontSee('name="password_confirmation"', false);
});

test('OpenID Connect registration records its revocable local session', function (): void {
    Notification::fake();
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();
    $this->app->instance(
        OAuthConnectionService::class,
        new OAuthConnectionService(new SocialiteProviderRegistry(static fn (): bool => true)),
    );
    $socialiteManager = Mockery::mock(SocialiteWasCalled::class);
    $socialiteManager->shouldReceive('extendSocialite')->once();
    $this->app->instance(SocialiteWasCalled::class, $socialiteManager);
    OAuthConnection::query()->create([
        'connection_id'        => 'crew-sso',
        'display_name'         => 'Crew SSO',
        'provider'             => 'openidconnect',
        'client_id'            => 'client-id',
        'client_secret'        => 'client-secret',
        'configuration'        => ['base_url' => 'https://issuer.example.com'],
        'enabled'              => true,
        'registration_enabled' => true,
    ]);

    $this->withSession(pendingOAuthIdentity(
        'crew-sso',
        oidcSid: 'oidc-session',
        email: 'registered.oidc@phpvms.net',
    ))
        ->post('/register', oauthRegistrationPayload('registered.oidc@phpvms.net'))
        ->assertRedirect(route('frontend.profile.index'));

    $user = User::query()->where('email', 'registered.oidc@phpvms.net')->firstOrFail();
    $externalSession = ExternalAuthSession::query()->where('user_id', $user->id)->firstOrFail();
    expect($externalSession->connection_id)->toBe('crew-sso')
        ->and($externalSession->provider_user_id)->toBe('subject-123')
        ->and($externalSession->oidc_sid)->toBe('oidc-session')
        ->and($externalSession->session_id)->toBe(session()->getId());
});

test('cookie sessions are rejected before OpenID Connect registration creates a user', function (): void {
    Notification::fake();
    config(['session.driver' => 'cookie']);
    $this->app->instance(
        OAuthConnectionService::class,
        new OAuthConnectionService(new SocialiteProviderRegistry(static fn (): bool => true)),
    );
    $socialiteManager = Mockery::mock(SocialiteWasCalled::class);
    $socialiteManager->shouldReceive('extendSocialite')->once();
    $this->app->instance(SocialiteWasCalled::class, $socialiteManager);
    OAuthConnection::query()->create([
        'connection_id'        => 'crew-sso',
        'display_name'         => 'Crew SSO',
        'provider'             => 'openidconnect',
        'client_id'            => 'client-id',
        'client_secret'        => 'client-secret',
        'configuration'        => ['base_url' => 'https://issuer.example.com'],
        'enabled'              => true,
        'registration_enabled' => true,
    ]);

    $request = HttpRequest::create(
        '/register',
        'POST',
        oauthRegistrationPayload('cookie.oidc@phpvms.net'),
    );
    $session = new Store('test', new ArraySessionHandler(120));
    $session->put('oauth', pendingOAuthIdentity('crew-sso')['oauth']);

    $request->setLaravelSession($session);

    expect(fn () => app(RegisterController::class)->register($request))
        ->toThrow(ValidationException::class, 'SESSION_DRIVER=cookie');

    expect(User::query()->where('email', 'cookie.oidc@phpvms.net')->exists())->toBeFalse();
});

test('cookie sessions are rejected before starting OpenID Connect authentication', function (): void {
    config(['session.driver' => 'cookie']);
    $this->app->instance(
        OAuthConnectionService::class,
        new OAuthConnectionService(new SocialiteProviderRegistry(static fn (): bool => true)),
    );
    $socialiteManager = Mockery::mock(SocialiteWasCalled::class);
    $socialiteManager->shouldReceive('extendSocialite')->once();
    $this->app->instance(SocialiteWasCalled::class, $socialiteManager);
    OAuthConnection::query()->create([
        'connection_id' => 'crew-sso',
        'display_name'  => 'Crew SSO',
        'provider'      => 'openidconnect',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'configuration' => ['base_url' => 'https://issuer.example.com'],
        'enabled'       => true,
        'login_enabled' => true,
    ]);

    $request = HttpRequest::create(route('oauth.redirect', ['provider' => 'crew-sso']), 'GET');

    expect(fn () => app(OAuthController::class)
        ->redirectToProvider('crew-sso', $request))
        ->toThrow(ValidationException::class, 'SESSION_DRIVER=cookie');
});

test('invite-only provider registration preserves the invite through the full flow', function (): void {
    Notification::fake();
    updateSetting('general.invite_only_registrations', true);
    oauthConnection();
    $invite = Invite::create([
        'token'       => 'invite-token',
        'usage_count' => 0,
    ]);
    $redirectRoute = route('oauth.redirect', [
        'provider' => 'discord',
        'intent'   => 'register',
        'invite'   => $invite->id,
        'token'    => $invite->token,
    ]);

    $this->get($invite->link)
        ->assertOk()
        ->assertSee($redirectRoute);

    mockOAuthRedirect();
    $this->get($redirectRoute)
        ->assertRedirect('https://provider.example/authorize?state=social-state');

    mockOAuthCallback(oauthUser(email: 'invited.oauth@phpvms.net'));
    $this->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertRedirect($invite->link);

    $pending = session('oauth.pending_identity');
    $registrationAttempt = $pending['registration_attempt'];
    $this->get($invite->link)
        ->assertOk()
        ->assertSee('name="oauth_registration"', false)
        ->assertSee((string) $registrationAttempt);
    $this->post('/register', [
        ...oauthRegistrationPayload('invited.oauth@phpvms.net', (string) $registrationAttempt),
        'invite'       => $invite->id,
        'invite_token' => base64_encode((string) $invite->token),
    ])->assertRedirect(route('frontend.profile.index'));

    $user = User::query()->where('email', 'invited.oauth@phpvms.net')->firstOrFail();
    expect($user->identities()
        ->where('connection_id', 'discord')
        ->where('provider_user_id', 'subject-123')
        ->exists())->toBeTrue()
        ->and($invite->fresh()->usage_count)->toBe(1);
});

test('provider registration validates its pending connection before creating a user', function (): void {
    Notification::fake();
    oauthConnection(['registration_enabled' => false]);

    $this->withSession(pendingOAuthIdentity())
        ->post('/register', oauthRegistrationPayload('disabled.oauth@phpvms.net'))
        ->assertForbidden();

    expect(User::query()->where('email', 'disabled.oauth@phpvms.net')->exists())->toBeFalse();
});

test('provider registration rolls back user creation when identity linking fails', function (): void {
    Notification::fake();
    $connection = oauthConnection();
    $owner = User::factory()->create();
    UserIdentity::query()->create([
        'user_id'          => $owner->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);

    $this->withSession(pendingOAuthIdentity())
        ->post('/register', oauthRegistrationPayload('conflict.oauth@phpvms.net'))
        ->assertSessionHasErrors('identity');

    expect(User::query()->where('email', 'conflict.oauth@phpvms.net')->exists())->toBeFalse()
        ->and(UserIdentity::query()->where('provider_user_id', 'subject-123')->count())->toBe(1);
});

test('OAuth route helpers keep the legacy provider parameter name', function (): void {
    expect(parse_url(route('oauth.redirect', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/redirect')
        ->and(parse_url(route('oauth.callback', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/callback')
        ->and(parse_url(route('oauth.register', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/register')
        ->and(parse_url(route('oauth.link', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/link')
        ->and(parse_url(route('oauth.link.complete', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/link/complete')
        ->and(parse_url(route('oauth.unlink', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/unlink')
        ->and(parse_url(route('oauth.backchannel-logout', ['provider' => 'discord']), PHP_URL_PATH))
        ->toBe('/oauth/discord/backchannel-logout')
        ->and(parse_url(route('oauth.cancel'), PHP_URL_PATH))
        ->toBe('/oauth/cancel');
});

test('disabled surfaces do not render or accept callbacks', function (): void {
    oauthConnection(['login_enabled' => false]);

    $this->get('/login')->assertDontSee('Sign in with Discord Crew');

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertNotFound();
});

test('callback state binds the intent to the connection', function (): void {
    oauthConnection();

    $this->withSession(oauthFlow(connection: 'vatsim'))
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertStatus(419);
});

test('unlink removes identity token and external session mappings', function (): void {
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();
    $connection = oauthConnection();
    $user = User::factory()->create([
        'password'                   => Hash::make('secret'),
        'discord_private_channel_id' => 'discord-dm-channel',
    ]);
    $identity = UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);
    UserOAuthToken::query()->create([
        'user_id'       => $user->id,
        'connection_id' => $connection->connection_id,
        'token'         => 'token',
        'refresh_token' => 'refresh-token',
    ]);
    ExternalAuthSession::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => $identity->provider_user_id,
        'session_id'       => 'external-session',
    ]);
    DB::table('sessions')->insert([
        'id'            => 'external-session',
        'user_id'       => $user->id,
        'ip_address'    => '127.0.0.1',
        'user_agent'    => 'Pest',
        'payload'       => base64_encode('session'),
        'last_activity' => time(),
    ]);

    $this->actingAs($user)
        ->delete(route('oauth.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'discord')->exists())->toBeFalse()
        ->and($user->oauth_tokens()->where('connection_id', 'discord')->exists())->toBeFalse()
        ->and($user->external_auth_sessions()->where('connection_id', 'discord')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'external-session')->exists())->toBeFalse()
        ->and($user->fresh()->discord_private_channel_id)->toBe('');
});

test('unlink does not restore a revoked current session at response end', function (): void {
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();
    $connection = oauthConnection();
    $user = User::factory()->create(['password' => Hash::make('secret')]);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);

    $this->withSession(['session-marker' => 'active']);
    $session = app(SessionManager::class)->driver();
    $sessionId = $session->getId();
    $session->save();

    ExternalAuthSession::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
        'session_id'       => $sessionId,
    ]);

    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeTrue();

    $this->withCookie((string) config('session.cookie'), $sessionId)
        ->actingAs($user)
        ->delete(route('oauth.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($session->getId())->not->toBe($sessionId)
        ->and(DB::table('sessions')->where('id', $sessionId)->exists())->toBeFalse()
        ->and(ExternalAuthSession::query()->where('session_id', $sessionId)->exists())->toBeFalse();
});

test('unlink refuses to remove the final permitted sign-in method', function (): void {
    $connection = oauthConnection();
    $user = User::factory()->create(['password' => '']);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);

    $this->actingAs($user)
        ->delete(route('oauth.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'discord')->exists())->toBeTrue();
});

test('an unavailable alternate identity does not permit unlinking the final usable sign-in method', function (): void {
    $connection = oauthConnection();
    $unavailableConnection = OAuthConnection::query()->create([
        'connection_id' => 'crew-sso',
        'display_name'  => 'Crew SSO',
        'provider'      => 'openidconnect',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'enabled'       => true,
        'login_enabled' => true,
    ]);
    app()->instance(
        SocialiteProviderRegistry::class,
        new SocialiteProviderRegistry(
            static fn (string $providerClass): bool => $providerClass === DiscordProvider::class,
        ),
    );
    $user = User::factory()->create(['password' => '']);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'discord-subject',
    ]);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $unavailableConnection->connection_id,
        'provider_user_id' => 'oidc-subject',
    ]);

    $this->actingAs($user)
        ->delete(route('oauth.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'discord')->exists())->toBeTrue()
        ->and($user->identities()->where('connection_id', 'crew-sso')->exists())->toBeTrue();
});

test('login registration and profile actions use their own surface flags', function (): void {
    oauthConnection([
        'login_enabled'        => true,
        'registration_enabled' => false,
        'linking_enabled'      => false,
    ]);
    $user = User::factory()->create();

    $this->get('/login')->assertSee('Sign in with Discord Crew');
    $this->get('/register')->assertDontSee('Register with Discord Crew');
    $this->actingAs($user)
        ->get(route('frontend.profile.edit', ['profile' => $user->id]))
        ->assertDontSee('Connected Accounts');
});

test('a social login connection can render its configured logo', function (): void {
    oauthConnection([
        'display_name'  => 'vacentral',
        'configuration' => ['logo_url' => 'https://vacentral.net/images/logo.png'],
    ]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Sign in with vacentral')
        ->assertSeeHtml('src="https://vacentral.net/images/logo.png"');
});

test('a disabled linked connection remains visible and unlinkable', function (): void {
    $connection = oauthConnection([
        'enabled'         => false,
        'linking_enabled' => false,
    ]);
    $user = User::factory()->create(['password' => Hash::make('secret')]);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);

    $this->actingAs($user)
        ->get(route('frontend.profile.edit', ['profile' => $user->id]))
        ->assertOk()
        ->assertSee('Connected Accounts')
        ->assertSee('Disconnect Discord Crew')
        ->assertDontSee('Connect Discord Crew');

    $this->delete(route('oauth.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'discord')->exists())->toBeFalse();
});

test('an unavailable linked connection remains visible and unlinkable', function (): void {
    $connection = oauthConnection();
    app()->instance(
        SocialiteProviderRegistry::class,
        new SocialiteProviderRegistry(static fn (string $providerClass): bool => false),
    );
    $user = User::factory()->create(['password' => Hash::make('secret')]);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);

    $this->actingAs($user)
        ->get(route('frontend.profile.edit', ['profile' => $user->id]))
        ->assertOk()
        ->assertSee('Disconnect Discord Crew')
        ->assertDontSee('Connect Discord Crew');

    $this->delete(route('oauth.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->identities()->where('connection_id', 'discord')->exists())->toBeFalse();
});

test('an unavailable unlinked connection cannot be connected', function (): void {
    oauthConnection();
    app()->instance(
        SocialiteProviderRegistry::class,
        new SocialiteProviderRegistry(static fn (string $providerClass): bool => false),
    );
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('frontend.profile.edit', ['profile' => $user->id]))
        ->assertOk()
        ->assertDontSee('Connect Discord Crew');
});

test('a successful discord link stores the private channel id', function (): void {
    config(['services.discord.bot_token' => 'discord-bot-token']);
    Http::fake([
        'discord.com/api/users/@me/channels' => Http::response(['id' => 'discord-dm-channel']),
    ]);
    oauthConnection();
    $user = User::factory()->create();
    mockOAuthCallback(oauthUser());

    $this->actingAs($user)
        ->withSession(oauthFlow('link'))
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertRedirect(route('frontend.profile.index'));

    expect($user->fresh()->discord_private_channel_id)->toBe('discord-dm-channel');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/users/@me/channels'
        && $request->hasHeader('Authorization', 'Bot discord-bot-token')
        && $request['recipient_id'] === 'subject-123');
});

test('a successful discord login refreshes the private channel id', function (): void {
    config(['services.discord.bot_token' => 'discord-bot-token']);
    Http::fake([
        'discord.com/api/users/@me/channels' => Http::response(['id' => 'new-discord-dm-channel']),
    ]);
    $connection = oauthConnection();
    $user = User::factory()->create(['discord_private_channel_id' => 'old-discord-dm-channel']);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);
    mockOAuthCallback(oauthUser());

    $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state']))
        ->assertRedirect(route('frontend.dashboard.index'));

    expect($user->fresh()->discord_private_channel_id)->toBe('new-discord-dm-channel');
});

test('a discord login failure before completion does not change the private channel id', function (): void {
    config(['services.discord.bot_token' => 'discord-bot-token']);
    Http::fake([
        'discord.com/api/users/@me/channels' => Http::response(['id' => 'new-discord-dm-channel']),
    ]);
    $connection = oauthConnection();
    $user = User::factory()->create(['discord_private_channel_id' => 'old-discord-dm-channel']);
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'subject-123',
    ]);
    mockOAuthCallback(oauthUser());
    Event::listen(AuthLogin::class, static function (): never {
        throw new RuntimeException('Authentication did not complete.');
    });
    $this->withoutExceptionHandling();

    expect(fn () => $this->withSession(oauthFlow())
        ->get(route('oauth.callback', ['provider' => 'discord', 'state' => 'social-state'])))
        ->toThrow(RuntimeException::class, 'Authentication did not complete.');

    expect($user->fresh()->discord_private_channel_id)->toBe('old-discord-dm-channel');
    Http::assertNothingSent();
});
