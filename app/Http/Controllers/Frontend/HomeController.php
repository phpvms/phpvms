<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Enums\UserState;
use App\Models\User;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PDOException;

class HomeController extends Controller
{
    public function __construct(private readonly PageService $pageService) {}

    /**
     * Show the application dashboard.
     */
    public function index(): View|RedirectResponse
    {
        try {
            $users = User::with('home_airport')
                ->where('state', '!=', UserState::DELETED
                )->orderBy('created_at', 'desc')
                ->take(4)
                ->get();
        } catch (PDOException $pdoException) {
            Log::emergency($pdoException);

            return redirect('system/install');
        }

        // No users
        if ($users->isEmpty()) {
            return redirect('system/install');
        }

        // Deliberately after both install redirects: an unreachable database or
        // an empty user table means the site is not set up, and that has to win
        // over whatever content happens to claim the root.
        $page = $this->pageService->resolve(PageService::ROOT_PATH);

        // A non-public page claiming the root falls through for guests rather
        // than throwing. Locking anonymous visitors out of the site root would
        // be a worse failure than showing them the stock home page.
        if ($page && ($page->public || Auth::check())) {
            return view('pages.index', ['page' => $page]);
        }

        return view('home', [
            'users' => $users,
        ]);
    }
}
