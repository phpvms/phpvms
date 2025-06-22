<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Models\Enums\UserState;
use App\Models\Invite;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Repositories\AirlineRepository;
use App\Repositories\AirportRepository;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\HttpClient;
use App\Support\Timezonelist;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\View\View;

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
        private readonly AirlineRepository $airlineRepo,
        private readonly AirportRepository $airportRepo,
        private readonly HttpClient $httpClient,
        private readonly UserService $userService,
    ) {
        $this->middleware('guest');

        $this->redirectTo = config('phpvms.registration_redirect');
    }

    public function showRegistrationForm(?Request $request = null): View
    {
        abort_if(setting('general.disable_registrations', false), 403, 'Registrations are disabled');

        if (setting('general.invite_only_registrations', false)) {
            abort_if(!$request->has('invite') && !$request->has('token'), 403, 'Registrations are invite only');
            $invite = Invite::find($request->get('invite'));
            abort_if(!$invite || $invite->token !== $request->get('token'), 403, 'Invalid invite');
            abort_if($invite->usage_limit && $invite->usage_count >= $invite->usage_limit, 403, 'Invite has been used too many times');
            abort_if($invite->expires_at && $invite->expires_at->isPast(), 403, 'Invite has expired');
        }

        $airlines = $this->airlineRepo->selectBoxList();
        $userFields = UserField::where(['show_on_registration' => true, 'active' => true, 'internal' => false])->get();

        return view('auth.register', [
            'airports'   => [],
            'airlines'   => $airlines,
            'countries'  => Countries::getSelectList(),
            'timezones'  => Timezonelist::toArray(),
            'userFields' => $userFields,
            'hubs_only'  => setting('pilots.home_hubs_only'),
            'invite'     => $invite ?? null,
            'captcha'    => [
                'enabled'    => setting('captcha.enabled', env('CAPTCHA_ENABLED', false)),
                'site_key'   => setting('captcha.site_key', env('CAPTCHA_SITE_KEY')),
                'secret_key' => setting('captcha.secret_key', env('CAPTCHA_SECRET_KEY')),
            ],
        ]);
    }

    /**
     * Get a validator for an incoming registration request.
     */
    protected function validator(array $data): Validator
    {
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
        $captcha_enabled = setting('captcha.enabled', env('CAPTCHA_ENABLED', false));
        if ($captcha_enabled === true) {
            $rules['h-captcha-response'] = [
                'required',
                function ($attribute, $value, $fail): void {
                    $response = $this->httpClient->form_post('https://hcaptcha.com/siteverify', [
                        'secret'   => setting('captcha.secret_key', env('CAPTCHA_SECRET_KEY')),
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
     * @param array $opts
     *
     * @throws \Exception
     * @throws \RuntimeException
     */
    protected function create(Request $request): User
    {
        abort_if(setting('general.disable_registrations', false), 403, 'Registrations are disabled');

        if (setting('general.invite_only_registrations', false)) {
            abort_if(!$request->has('invite') && !$request->has('invite_token'), 403, 'Registrations are invite only');

            $invite = Invite::find($request->get('invite'));
            abort_if(!$invite || $invite->token !== base64_decode((string) $request->get('invite_token')), 403, 'Invalid invite');

            abort_if($invite->usage_limit && $invite->usage_count >= $invite->usage_limit, 403, 'Invite has been used too many times');

            abort_if($invite->expires_at && $invite->expires_at->isPast(), 403, 'Invite has expired');

            abort_if($invite->email && $invite->email !== $request->get('email'), 403, 'Invite is for a different email address');

            $invite->update([
                'usage_count' => $invite->usage_count + 1,
            ]);
        }

        // Default options
        $opts = $request->all();
        $opts['password'] = Hash::make($opts['password']);

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
     * @throws \Exception
     */
    public function register(Request $request): RedirectResponse|View
    {
        $this->validator($request->all())->validate();

        $user = $this->create($request);
        if ($user->state === UserState::PENDING) {
            return view('auth.pending');
        }

        $this->guard()->login($user);

        return redirect('/dashboard');
    }
}
