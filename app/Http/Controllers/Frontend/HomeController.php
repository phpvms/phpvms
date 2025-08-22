<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Enums\UserState;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PDOException;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(): View|RedirectResponse
    {
        try {
            $users = User::with('home_airport')->where('state', '!=', UserState::DELETED)->orderBy('created_at', 'desc')->take(4)->get();
        } catch (PDOException $e) {
            Log::emergency($e);

            return view('system/errors/database_error', [
                'error' => $e->getMessage(),
            ]);
        } catch (QueryException $e) {
            return redirect('system/install');
        }

        // No users
        if (!$users) {
            return redirect('system/install');
        }

        return view('home', [
            'users' => $users,
        ]);
    }
}
