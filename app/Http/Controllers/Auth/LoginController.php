<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Exceptions\PilotIdNotFound;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect after logging in
     */
    protected mixed $redirectTo = '/dashboard';

    private string $loginFieldValue;

    /**
     * LoginController constructor.
     */
    public function __construct(
        private readonly UserService $userSvc
    ) {
        $this->redirectTo = config('phpvms.login_redirect');
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Get the needed authorization credentials from the request.
     * Overriding the value from the trait
     *
     * @override
     */
    protected function credentials(Request $request): array
    {
        return [
            'email'    => $this->loginFieldValue,
            'password' => $request->input('password'),
        ];
    }

    /**
     * Validate the user login request.
     *
     * @override
     */
    protected function validateLogin(Request $request): void
    {
        $id_field = $request->input('email');
        $validations = ['required', 'string'];

        /*
         * Trying to login by email or not?
         *
         * If not, run a validation rule which attempts to split the user by their VA and ID
         * Then inject that user's email into the request
         */
        if (str_contains((string) $id_field, '@')) {
            $validations[] = 'email';
            $this->loginFieldValue = $request->input('email');
        } else {
            $validations[] = function ($attr, string $value, $fail) use ($request): void {
                try {
                    $user = $this->userSvc->findUserByPilotId($value);
                } catch (PilotIdNotFound) {
                    Log::warning('Error logging in, pilot_id not found, id='.$value);
                    $fail(__('auth.failed'));

                    return;
                }

                $request->email = $user->email;
                $this->loginFieldValue = $user->email;
            };
        }

        $request->validate([
            'email'    => $validations,
            'password' => 'required|string',
        ]);
    }

    protected function sendLoginResponse(Request $request): RedirectResponse|View
    {
        /** @var User $user */
        $user = Auth::user();

        if (setting('general.record_user_ip', true)) {
            $user->last_ip = $request->ip();
            $user->lastlogin_at = Carbon::now();
            $user->save();
        }

        if ($user->state !== UserState::ACTIVE && $user->state !== UserState::ON_LEAVE) {
            Log::info('Trying to login '.$user->ident.', state '.UserState::label($user->state));

            // Log them out
            $this->guard()->logout();
            $request->session()->invalidate();

            // Redirect to one of the error pages
            if ($user->state === UserState::PENDING) {
                return view('auth.pending');
            }

            if ($user->state === UserState::REJECTED) {
                return view('auth.rejected');
            }

            if ($user->state === UserState::SUSPENDED) {
                return view('auth.suspended');
            }
        }

        $request->session()->regenerate();
        $this->clearLoginAttempts($request);

        return redirect()->intended($this->redirectPath());
    }
}
