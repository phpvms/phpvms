<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Enums\UserState;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        try {
            $users = User::with('home_airport')->where('state', '!=', UserState::DELETED)->orderBy('created_at', 'desc')->take(4)->get();
        } catch (\PDOException $e) {
            Log::emergency($e);

            return view('system/errors/database_error', [
                'error' => $e->getMessage(),
            ]);
        } catch (QueryException $e) {
            return view('system/errors/not_installed');
        }

        // No users
        if (!$users) {
            return view('system/errors/not_installed');
        }

        return view('home', [
            'users' => $users,
        ]);
    }
}
