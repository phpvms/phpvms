<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be resent if the user did not receive the original email message.
    |
    */
    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
     */
    protected string $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function verify(Request $request)
    {
        $user = User::find($request->route('id'));

        if (!hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            throw new AuthorizationException();
        }

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            throw new AuthorizationException();
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($this->redirectPath());
        }

        if ($user->markEmailAsVerified()) {
            Log::info('Marking user '.$user->id.' as verified');
            event(new Verified($user));
        }

        return redirect($this->redirectPath())->with('verified', true);
    }
}
