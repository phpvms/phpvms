<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\View\View;

/**
 * Class ForgotPasswordController.
 */
class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    /**
     * ForgotPasswordController constructor.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showLinkRequestForm(): View
    {
        return view('auth.passwords.email');
    }
}
