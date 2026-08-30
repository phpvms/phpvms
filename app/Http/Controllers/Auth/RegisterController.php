<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Enums\UserState;
use App\Features\OAuth\Helpers\ExternalAuthSessionService;
use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\OAuthIdentityService;
use App\Features\OAuth\Helpers\OAuthSessionStore;
use App\Models\Airline;
use App\Models\Invite;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\HttpClient;
use App\Support\Timezonelist;
use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\View\View;
use RuntimeException;

class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * RegisterController constructor.
     */
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly UserService $userService,
        private readonly OAuthConnectionService $oauthConnections,
        private readonly OAuthIdentityService $oauthIdentities,
        private readonly OAuthSessionStore $oauthSessions,
        private readonly ExternalAuthSessionService $externalSessions,
    ) {
        $this->middleware('guest');

        $this->redirectTo = config('phpvms.registration_redirect');
    }

    public function showRegistrationForm(Request $request): RedirectResponse|View
    {
        if (setting('general.disable_registrations', false)) {
            abort(403, 'Registrations are disabled');
        }

        $oauthRegistration = $request->old('oauth_registration');
        if ($oauthRegistration !== null
            && (!is_string($oauthRegistration)
                || $oauthRegistration === ''
                || $this->oauthSessions->pendingRegistration($request, $oauthRegistration) === null)) {
            return $this->expiredOAuthRegistration();
        }

        if (setting('general.invite_only_registrations', false)) {
            if (!$request->has('invite') && !$request->has('token')) {
                abort(403, 'Registrations are invite only');
            }

            $invite = Invite::find($request->input('invite'));
            if (!$invite || $invite->token !== $request->input('token')) {
                abort(403, 'Invalid invite');
            }

            if ($invite->usage_limit && $invite->usage_count >= $invite->usage_limit) {
                abort(403, 'Invite has been used too many times');
            }

            if ($invite->expires_at && $invite->expires_at->isPast()) {
                abort(403, 'Invite has expired');
            }
        }

        $airlines = Airline::selectList();
        $userFields = UserField::where(['show_on_registration' => true, 'active' => true, 'internal' => false])->get();

        return view('auth.register', [
            'airports'          => [],
            'airlines'          => $airlines,
            'countries'         => Countries::getSelectList(),
            'timezones'         => Timezonelist::toArray(),
            'userFields'        => $userFields,
            'hubs_only'         => setting('pilots.home_hubs_only'),
            'invite'            => $invite ?? null,
            'oauthRegistration' => is_string($oauthRegistration) ? $oauthRegistration : null,
            'captcha'           => [
                'enabled'    => setting('captcha.enabled', false),
                'site_key'   => setting('captcha.site_key', ''),
                'secret_key' => setting('captcha.secret_key', ''),
            ],
        ]);
    }

    /**
     * Get a validator for an incoming registration request.
     */
    protected function validator(array $data): Validator
    {
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim((string) $data['email']));
        }

        $rules = [
            'name'            => 'required|max:255',
            'email'           => 'required|email|max:255|unique:users,email',
            'airline_id'      => 'required',
            'home_airport_id' => 'required',
            'password'        => 'required|min:5|confirmed',
            'toc_accepted'    => 'accepted',
        ];

        // Dynamically add the required fields
        $userFields = UserField::where([
            'show_on_registration' => true,
            'required'             => true,
            'internal'             => false,
            'active'               => true,
        ])->get();

        foreach ($userFields as $field) {
            $rules['field_'.$field->slug] = 'required';
        }

        /*
         * Validation for hcaptcha
         */
        $captcha_enabled = setting('captcha.enabled', false);
        if ($captcha_enabled === true) {
            $rules['h-captcha-response'] = [
                'required',
                function ($attribute, $value, $fail): void {
                    $response = $this->httpClient->form_post('https://hcaptcha.com/siteverify', [
                        'secret'   => setting('captcha.secret_key', ''),
                        'response' => $value,
                    ]);

                    if ($response['success'] !== true) {
                        Log::error('Captcha failed '.json_encode($response));
                        $fail('Captcha verification failed, please try again.');
                    }
                },
            ];
        }

        return ValidatorFacade::make($data, $rules);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @throws Exception
     * @throws RuntimeException
     */
    protected function create(Request $request): User
    {
        if (setting('general.disable_registrations', false)) {
            abort(403, 'Registrations are disabled');
        }

        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        if (setting('general.invite_only_registrations', false)) {
            if (!$request->has('invite') && !$request->has('invite_token')) {
                abort(403, 'Registrations are invite only');
            }

            $invite = Invite::find($request->input('invite'));
            if (!$invite || $invite->token !== base64_decode((string) $request->input('invite_token'))) {
                abort(403, 'Invalid invite');
            }

            if ($invite->usage_limit && $invite->usage_count >= $invite->usage_limit) {
                abort(403, 'Invite has been used too many times');
            }

            if ($invite->expires_at && $invite->expires_at->isPast()) {
                abort(403, 'Invite has expired');
            }

            if ($invite->email && $invite->email !== $request->input('email')) {
                abort(403, 'Invite is for a different email address');
            }

            $invite->update([
                'usage_count' => $invite->usage_count + 1,
            ]);
        }

        // Default options
        $opts = $request->all();
        $opts['password'] = Hash::make($opts['password']);

        // A privilege flag, not a registration field: it is fillable so the
        // admin pages can write it, which means a tampered registration
        // request could grant itself auto-accepted PIREPs.
        unset($opts['auto_accept_pireps']);

        if (setting('general.record_user_ip', true)) {
            $opts['last_ip'] = $request->ip();
        }

        // Convert transfer hours into minutes
        if (isset($opts['transfer_time'])) {
            $opts['transfer_time'] *= 60;
        }

        $user = $this->userService->createUser($opts);

        Log::info('User registered: ', $user->toArray());

        $userFields = UserField::where(['show_on_registration' => true, 'active' => true, 'internal' => false])->get();
        foreach ($userFields as $field) {
            $field_name = 'field_'.$field->slug;
            UserFieldValue::updateOrCreate([
                'user_field_id' => $field->id,
                'user_id'       => $user->id,
            ], ['value' => $opts[$field_name]]);
        }

        return $user;
    }

    /**
     * Handle a registration request for the application.
     *
     *
     * @throws Exception
     */
    public function register(Request $request): RedirectResponse|View
    {
        $oauthRegistration = $request->input('oauth_registration');
        $pendingIdentity = null;
        if ($oauthRegistration !== null) {
            if (!is_string($oauthRegistration)
                || $oauthRegistration === ''
                || ($pendingIdentity = $this->oauthSessions->pendingRegistration(
                    $request,
                    $oauthRegistration,
                )) === null) {
                return $this->expiredOAuthRegistration();
            }
        }

        $this->validator($request->all())->validate();

        $connection = null;
        if ($pendingIdentity !== null) {
            $connection = $this->oauthConnections->resolve((string) $pendingIdentity['connection_id']);
            if (!$this->oauthConnections->pendingIdentityMatches($connection, $pendingIdentity)) {
                $this->oauthSessions->forgetPending($request);

                return $this->expiredOAuthRegistration();
            }

            $this->oauthConnections->assertServerSideSessionRevocationSupported($connection);
            if (!$connection->registration_enabled) {
                abort(403);
            }
        }

        $user = DB::transaction(function () use ($request, $pendingIdentity): User {
            $user = $this->create($request);
            if ($pendingIdentity !== null) {
                $this->oauthIdentities->link($user, $pendingIdentity);
            }

            return $user;
        });

        if ($pendingIdentity !== null) {
            $this->oauthSessions->forgetPending($request);
        }

        if ($user->state === UserState::PENDING) {
            return view('auth.pending');
        }

        $this->guard()->login($user);

        if ($pendingIdentity !== null
            && in_array($connection->provider, ['openidconnect', 'vacentral'], true)) {
            $this->externalSessions->record(
                $user,
                $connection,
                $request->session()->getId(),
                (string) $pendingIdentity['provider_user_id'],
                is_string($pendingIdentity['oidc_sid'] ?? null) ? $pendingIdentity['oidc_sid'] : null,
            );
        }

        return redirect('/dashboard');
    }

    public function cancelOAuthRegistration(Request $request): RedirectResponse
    {
        $this->oauthSessions->forgetPending($request);

        return redirect('/login');
    }

    private function expiredOAuthRegistration(): RedirectResponse
    {
        flash()->error(__('auth.oauth_registration_expired'));

        return redirect('/login');
    }
}
