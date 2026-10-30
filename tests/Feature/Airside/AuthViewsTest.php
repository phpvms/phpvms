<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

require_once __DIR__.'/Support.php';

use App\Models\Invite;
use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * 6.6: every auth view renders with airside active, from the data the
 * controllers pass.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', false);
});

function enableDiscordLogin(): OAuthConnection
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
    ]);

    return $connection->refresh();
}

it('renders the login page from its controller', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('action="'.url('/login').'"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee(url('/register'), false)
        ->assertSee(url('/password/reset'), false)
        ->assertSee('x-data="tallstackui_toastBase(', false);
});

it('lists the enabled social logins on the login page', function (): void {
    enableDiscordLogin();

    $this->get('/login')->assertOk()->assertSee('Discord Crew');
});

it('shows a failed login error under the field', function (): void {
    $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertRedirect('/login');

    $this->get('/login')->assertSee('role="alert"', false)->assertSee('value="nobody@example.com"', false);
});

it('renders the register page with the terms and the disabled submit', function (): void {
    $this->get('/register')
        ->assertOk()
        ->assertSee('action="'.url('/register').'"', false)
        ->assertSee('name="airline_id"', false)
        ->assertSee('name="home_airport_id"', false)
        ->assertSee('name="country"', false)
        ->assertSee('name="timezone"', false)
        ->assertSee('name="toc_accepted"', false)
        ->assertSee('name="opt_in"', false)
        ->assertSee('id="register_button"', false)
        ->assertSee('airsideAirportSearch(', false);
});

it('renders the register page for an invite', function (): void {
    updateSetting('general.invite_only_registrations', true);
    $invite = Invite::create(['token' => 'secret-token', 'usage_limit' => 5, 'usage_count' => 0]);

    $this->get('/register?invite='.$invite->id.'&token=secret-token')
        ->assertOk()
        ->assertSee('name="invite" value="'.$invite->id.'"', false)
        ->assertSee('name="invite_token"', false);
});

it('renders the password reset request page', function (): void {
    $this->get('/password/reset')
        ->assertOk()
        ->assertSee('action="'.url('/password/email').'"', false)
        ->assertSee('name="email"', false);
});

it('renders the password reset form with the token and email', function (): void {
    $this->get('/password/reset/abc123?email=pilot@example.com')
        ->assertOk()
        ->assertSee('action="'.url('/password/reset').'"', false)
        ->assertSee('name="token" value="abc123"', false)
        ->assertSee('value="pilot@example.com"', false)
        ->assertSee('name="password_confirmation"', false);
});

/**
 * Views that need state a controller builds first, rendered on a test route
 * with the data shape their controller passes.
 */
it('renders every account state and confirmation view', function (string $view, string $messageKey): void {
    Route::middleware('web')->get('/airside-views/'.$view, fn (): Factory|View => view($view));

    $this->get('/airside-views/'.$view)->assertOk()->assertSee(__($messageKey), false);
})->with([
    'pending'    => ['auth.pending', 'auth.pending_message'],
    'rejected'   => ['auth.rejected', 'auth.denied_message'],
    'suspended'  => ['auth.suspended', 'auth.suspended_message'],
    'registered' => ['auth.registered', 'auth.confirmation_message'],
    'toc'        => ['auth.toc', 'toc.title'],
]);

it('renders the email verification page and the resent notice', function (): void {
    Route::middleware('web')->get('/airside-views/verify', fn (): Factory|View => view('auth.verify'));

    $this->get('/airside-views/verify')
        ->assertOk()
        ->assertSee('action="'.route('verification.resend').'"', false)
        ->assertDontSee('A fresh verification link');

    $this->withSession(['resent' => true])->get('/airside-views/verify')->assertSee('A fresh verification link');
});

it('renders the unknown social account page with its actions', function (bool $registration, bool $linking, bool $verifiedEmail): void {
    $connection = enableDiscordLogin();
    $connection->update(['linking_enabled' => $linking]);

    Route::middleware('web')->get('/airside-views/oauth-unknown', fn (): Factory|View => view('auth.oauth-unknown', [
        'connection'            => $connection->refresh(),
        'pending'               => ['email' => 'oauth@example.com'],
        'verifiedEmailMatch'    => $verifiedEmail,
        'registrationAvailable' => $registration,
    ]));

    $response = $this->get('/airside-views/oauth-unknown')->assertOk()->assertSee(route('oauth.cancel'), false);

    $registration
        ? $response->assertSee(route('oauth.register', ['provider' => 'discord']), false)
        : $response->assertDontSee(route('oauth.register', ['provider' => 'discord']), false);

    $linking
        ? $response->assertSee(route('oauth.link', ['provider' => 'discord']), false)
        : $response->assertDontSee(route('oauth.link', ['provider' => 'discord']), false);

    $verifiedEmail
        ? $response->assertSee('oauth@example.com')
        : $response->assertDontSee('oauth@example.com');
})->with([
    'register and link'    => [true, true, false],
    'neither'              => [false, false, false],
    'verified email match' => [true, true, true],
]);

it('renders the social connection buttons for the registration surface', function (): void {
    enableDiscordLogin();
    Route::middleware('web')->get('/airside-views/oauth-connections', fn (): Factory|View => view('auth.oauth-connections', ['surface' => 'registration']));

    $this->get('/airside-views/oauth-connections')->assertOk()->assertSee('Discord Crew')->assertSee('intent=register', false);
});

it('renders the OAuth consent page with the scopes', function (): void {
    $pilot = User::factory()->create();

    Route::middleware(['web', 'auth'])->get('/airside-views/oauth-authorize', fn (): Factory|View => view('oauth.authorize', [
        'client' => new class()
        {
            public string $name = 'Flight Planner';

            public function getKey(): string
            {
                return 'client-key';
            }
        },
        'scopes'    => [(object) ['description' => 'Read your profile'], (object) ['description' => 'File PIREPs']],
        'request'   => (object) ['state' => 'xyz'],
        'authToken' => 'auth-token-1',
    ]));

    $this->actingAs($pilot)->get('/airside-views/oauth-authorize')
        ->assertOk()
        ->assertSee('Flight Planner')
        ->assertSee('Read your profile')
        ->assertSee('File PIREPs')
        ->assertSee('name="auth_token" value="auth-token-1"', false)
        ->assertSee(route('passport.authorizations.approve'), false)
        ->assertSee(route('passport.authorizations.deny'), false);
});
