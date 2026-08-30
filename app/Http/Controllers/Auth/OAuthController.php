<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Enums\UserState;
use App\Features\OAuth\Helpers\ExternalAuthSessionService;
use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\OAuthIdentityService;
use App\Features\OAuth\Helpers\OAuthSessionStore;
use App\Models\Invite;
use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class OAuthController extends Controller
{
    private const array INTENTS = ['login', 'register', 'link'];

    public function __construct(
        private readonly OAuthConnectionService $connections,
        private readonly OAuthIdentityService $identities,
        private readonly OAuthSessionStore $sessions,
        private readonly ExternalAuthSessionService $externalSessions,
    ) {}

    public function redirectToProvider(string $connection, Request $request): RedirectResponse
    {
        $oauthConnection = $this->resolveConnection($connection);
        $this->connections->assertServerSideSessionRevocationSupported($oauthConnection);
        $intent = (string) $request->query('intent', Auth::check() ? 'link' : 'login');

        if (!in_array($intent, self::INTENTS, true) || !$this->surfaceEnabled($oauthConnection, $intent)) {
            abort(404);
        }

        if ($intent === 'link' && !Auth::check()) {
            abort(403);
        }

        $registrationContext = $this->registrationContext($request);
        if ($intent === 'register'
            && setting('general.invite_only_registrations', false)
            && !$this->validInvite($registrationContext) instanceof Invite) {
            abort(403);
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($this->connections->driverFor($oauthConnection));
        if ($oauthConnection->scopes !== null) {
            $driver->scopes($oauthConnection->scopes);
        }

        $response = $driver->redirect();
        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        if (!is_string($state) || $state === '') {
            abort(500, 'The social login provider did not create an OAuth state value.');
        }

        $this->sessions->rememberFlow($request, $state, $oauthConnection, $intent, $registrationContext);

        return $response;
    }

    public function handleProviderCallback(string $connection, Request $request): View|RedirectResponse
    {
        $oauthConnection = $this->resolveConnection($connection);
        $this->connections->assertServerSideSessionRevocationSupported($oauthConnection);
        $state = (string) $request->query('state', '');
        $flow = $this->sessions->flow($request, $state);

        if ($flow === null || $flow['connection_id'] !== $connection) {
            abort(419, 'The social login request has expired or is invalid.');
        }

        if (!in_array($flow['intent'], self::INTENTS, true)
            || !$this->surfaceEnabled($oauthConnection, $flow['intent'])) {
            abort(404);
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($this->connections->driverFor($oauthConnection));
        /** @var SocialiteUser $providerUser */
        $providerUser = $driver->user();
        $this->sessions->forgetFlow($request, $state);

        $providerUserId = trim((string) $providerUser->getId());
        if ($providerUserId === '') {
            flash()->error(__('auth.oauth_missing_subject'));

            return redirect('/login');
        }

        $credentials = $this->providerCredentials($oauthConnection, $providerUser);
        $linkedUser = $this->identities->findUser($connection, $providerUserId);
        $intent = $flow['intent'];

        if ($intent === 'link') {
            return $this->linkAuthenticatedUser($request, $oauthConnection, $providerUser, $linkedUser);
        }

        if ($linkedUser instanceof User) {
            $this->identities->syncToken($linkedUser, $credentials);

            return $this->loginLinkedUser($request, $oauthConnection, $providerUser, $linkedUser);
        }

        $pending = $this->sessions->rememberPending($request, $oauthConnection, $providerUser, $flow);
        if ($intent === 'register') {
            return $this->beginRegistration($connection, $request);
        }

        $verifiedEmailMatch = false;
        if ($pending['email_verified'] === true && filled($pending['email'])) {
            $verifiedEmailMatch = User::query()
                ->where('email', mb_strtolower(trim((string) $pending['email'])))
                ->exists();
        }

        return view('auth.oauth-unknown', [
            'connection'            => $oauthConnection,
            'pending'               => $pending,
            'verifiedEmailMatch'    => $verifiedEmailMatch,
            'registrationAvailable' => $this->registrationAvailable($oauthConnection, $pending),
        ]);
    }

    public function beginRegistration(string $connection, Request $request): RedirectResponse
    {
        $pending = $this->pendingFor($connection, $request);
        $oauthConnection = $this->resolveConnection($connection);
        $registrationAttempt = $pending['registration_attempt'] ?? null;
        if (!is_string($registrationAttempt) || $registrationAttempt === '') {
            abort(419, 'The social login request has expired or is invalid.');
        }

        if (!$oauthConnection->registration_enabled || setting('general.disable_registrations', false)) {
            abort(403);
        }

        $registrationUrl = url('/register');
        if (setting('general.invite_only_registrations', false)) {
            $invite = $this->validInvite($pending);
            if (!$invite instanceof Invite) {
                abort(403);
            }

            $registrationUrl .= '?'.http_build_query([
                'invite' => $invite->id,
                'token'  => $invite->token,
            ]);
        }

        return redirect($registrationUrl)->withInput([
            'name'               => $pending['name'],
            'email'              => $pending['email'],
            'oauth_registration' => $registrationAttempt,
        ]);
    }

    public function beginExistingLink(string $connection, Request $request): RedirectResponse
    {
        $this->pendingFor($connection, $request);
        $oauthConnection = $this->resolveConnection($connection);

        if (!$oauthConnection->linking_enabled) {
            abort(403);
        }

        $request->session()->put('url.intended', route('oauth.link.complete', ['provider' => $connection]));

        return redirect('/login');
    }

    public function completeExistingLink(string $connection, Request $request): RedirectResponse
    {
        $pending = $this->pendingFor($connection, $request);
        $oauthConnection = $this->resolveConnection($connection);

        if (!$oauthConnection->linking_enabled) {
            abort(403);
        }

        try {
            /** @var User $user */
            $user = Auth::user();
            $this->identities->link($user, $pending);
            $this->sessions->forgetPending($request);
        } catch (ValidationException $validationException) {
            flash()->error($validationException->validator->errors()->first());

            return redirect()->route('frontend.profile.index');
        }

        flash()->success(__('auth.oauth_linked', ['provider' => $oauthConnection->display_name]));

        return redirect()->route('frontend.profile.index');
    }

    public function unlink(string $connection): RedirectResponse
    {
        $oauthConnection = $this->connections->find($connection);
        if (!$oauthConnection instanceof OAuthConnection) {
            abort(404);
        }

        try {
            /** @var User $user */
            $user = Auth::user();
            $this->identities->unlink($user, $oauthConnection);
        } catch (ValidationException $validationException) {
            flash()->error($validationException->validator->errors()->first());

            return redirect()->route('frontend.profile.index');
        }

        flash()->success(__('auth.oauth_unlinked', ['provider' => $oauthConnection->display_name]));

        return redirect()->route('frontend.profile.index');
    }

    private function linkAuthenticatedUser(
        Request $request,
        OAuthConnection $connection,
        SocialiteUser $providerUser,
        ?User $linkedUser,
    ): RedirectResponse {
        if (!Auth::check()) {
            abort(403);
        }

        /** @var User $user */
        $user = Auth::user();
        if ($linkedUser instanceof User && $linkedUser->id !== $user->id) {
            flash()->error(__('auth.oauth_identity_already_linked'));

            return redirect()->route('frontend.profile.index');
        }

        try {
            $pending = $this->sessions->rememberPending($request, $connection, $providerUser);
            $this->identities->link($user, $pending);
            $this->sessions->forgetPending($request);
        } catch (ValidationException $validationException) {
            flash()->error($validationException->validator->errors()->first());

            return redirect()->route('frontend.profile.index');
        }

        flash()->success(__('auth.oauth_linked', ['provider' => $connection->display_name]));

        return redirect()->route('frontend.profile.index');
    }

    private function loginLinkedUser(
        Request $request,
        OAuthConnection $connection,
        SocialiteUser $providerUser,
        User $user,
    ): View|RedirectResponse {
        $this->connections->assertServerSideSessionRevocationSupported($connection);

        if ($user->state !== UserState::ACTIVE && $user->state !== UserState::ON_LEAVE) {
            Log::info('Trying to login '.$user->ident.', state '.$user->state->getLabel());
            Auth::logout();
            $request->session()->invalidate();

            return match ($user->state) {
                UserState::PENDING   => view('auth.pending'),
                UserState::REJECTED  => view('auth.rejected'),
                UserState::SUSPENDED => view('auth.suspended'),
                default              => redirect('/login'),
            };
        }

        $user->lastlogin_at = now();
        if (setting('general.record_user_ip', true)) {
            $user->last_ip = $request->ip();
        }

        $user->save();

        $remember = !in_array($connection->provider, ['openidconnect', 'vacentral'], true);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->identities->syncDiscordPrivateChannel($user, $connection->connection_id);

        if (in_array($connection->provider, ['openidconnect', 'vacentral'], true)) {
            $raw = $providerUser->getRaw();
            $sid = isset($raw['sid']) && is_string($raw['sid']) ? $raw['sid'] : null;
            $this->externalSessions->record(
                $user,
                $connection,
                $request->session()->getId(),
                (string) $providerUser->getId(),
                $sid,
            );
        }

        return redirect()->route('frontend.dashboard.index');
    }

    private function resolveConnection(string $connection): OAuthConnection
    {
        try {
            return $this->connections->resolve($connection);
        } catch (ValidationException) {
            abort(404);
        }
    }

    /** @return array<string, bool|int|string|null> */
    private function pendingFor(string $connection, Request $request): array
    {
        $pending = $this->sessions->pending($request);
        if ($pending === null || $pending['connection_id'] !== $connection) {
            abort(419, 'The social login request has expired or is invalid.');
        }

        return $pending;
    }

    /** @return array<string, bool|int|string|null> */
    private function providerCredentials(OAuthConnection $connection, SocialiteUser $providerUser): array
    {
        return [
            'connection_id'    => $connection->connection_id,
            'token'            => (string) $providerUser->token,
            'refresh_token'    => (string) $providerUser->refreshToken,
            'token_expires_at' => $providerUser->expiresIn > 0
                ? now()->addSeconds($providerUser->expiresIn)->toIso8601String()
                : null,
        ];
    }

    private function surfaceEnabled(OAuthConnection $connection, string $intent): bool
    {
        return match ($intent) {
            'login'    => $connection->login_enabled,
            'register' => $connection->registration_enabled,
            'link'     => $connection->linking_enabled,
            default    => false,
        };
    }

    /** @return array{invite: string, invite_token: string}|null */
    private function registrationContext(Request $request): ?array
    {
        $invite = $request->query('invite');
        $token = $request->query('token');
        if (!is_string($invite) || !is_string($token) || $token === '') {
            return null;
        }

        return [
            'invite'       => $invite,
            'invite_token' => $token,
        ];
    }

    /** @param array<string, bool|int|string|null>|null $context */
    private function validInvite(?array $context): ?Invite
    {
        $inviteId = $context['invite'] ?? null;
        $token = $context['invite_token'] ?? null;
        if ((!is_string($inviteId) && !is_int($inviteId)) || !is_string($token) || $token === '') {
            return null;
        }

        $invite = Invite::find($inviteId);
        if (!$invite instanceof Invite
            || !hash_equals((string) $invite->token, $token)
            || ($invite->usage_limit && $invite->usage_count >= $invite->usage_limit)
            || ($invite->expires_at && $invite->expires_at->isPast())) {
            return null;
        }

        return $invite;
    }

    /** @param array<string, bool|int|string|null> $pending */
    private function registrationAvailable(OAuthConnection $connection, array $pending): bool
    {
        if (!$connection->registration_enabled || setting('general.disable_registrations', false)) {
            return false;
        }

        return !setting('general.invite_only_registrations', false)
            || $this->validInvite($pending) instanceof Invite;
    }
}
