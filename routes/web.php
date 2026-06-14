<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\RouteForgeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Frontend\AirportController;
use App\Http\Controllers\Frontend\CreditsController;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\DownloadController;
use App\Http\Controllers\Frontend\FlightController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\LanguageController;
use App\Http\Controllers\Frontend\LiveMapController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\PirepController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\SimBriefController;
use App\Http\Controllers\Frontend\UserController;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'     => '',
    'as'         => 'frontend.',
    'middleware' => (config('phpvms.registration.email_verification', false) ? ['auth', 'verified'] : ['auth']),
], function (): void {
    Route::resource('dashboard', DashboardController::class);

    Route::get('airports/{id}', [AirportController::class, 'show'])->name('airports.show');

    Route::get('downloads', [DownloadController::class, 'index'])->name('downloads.index');
    Route::get('downloads/{id}', [DownloadController::class, 'show'])->name('downloads.download');

    Route::get('flights/bids', [FlightController::class, 'bids'])->name('flights.bids');
    Route::get('flights/search', [FlightController::class, 'search'])->name('flights.search');
    Route::resource('flights', FlightController::class);

    Route::get('pireps/fares', [PirepController::class, 'fares']);
    Route::post('pireps/{id}/submit', [PirepController::class, 'submit'])->name('pireps.submit');

    Route::resource('pireps', PirepController::class, [
        'except' => ['show'],
    ]);

    Route::get('profile/acars', [ProfileController::class, 'acars'])->name('profile.acars');
    Route::get('profile/regen_apikey', [ProfileController::class, 'regen_apikey'])->name('profile.regen_apikey');

    Route::resource('profile', ProfileController::class);

    Route::get('simbrief/generate', [SimBriefController::class, 'generate'])->name('simbrief.generate');
    Route::post('simbrief/apicode', [SimBriefController::class, 'api_code'])->name('simbrief.api_code');
    Route::get('simbrief/check_ofp', [SimBriefController::class, 'check_ofp'])->name('simbrief.check_ofp')->middleware('throttle:10,1');
    Route::get('simbrief/update_ofp', [SimBriefController::class, 'update_ofp'])->name('simbrief.update_ofp');
    Route::get('simbrief/{id}', [SimBriefController::class, 'briefing'])->name('simbrief.briefing');
    Route::get('simbrief/{id}/prefile', [SimBriefController::class, 'prefile'])->name('simbrief.prefile');
    Route::get('simbrief/{id}/cancel', [SimBriefController::class, 'cancel'])->name('simbrief.cancel');
    Route::get('simbrief/{id}/generate_new', [SimBriefController::class, 'generate_new'])->name('simbrief.generate_new');
});

Route::group([
    'prefix' => '',
    'as'     => 'frontend.',
], function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('r/{id}', [PirepController::class, 'show'])->name('pirep.show.public');
    Route::get('pireps/{id}', [PirepController::class, 'show'])->name('pireps.show');

    Route::get('users/{id}', [ProfileController::class, 'show'])->name('users.show.public');
    Route::get('pilots/{id}', [ProfileController::class, 'show'])->name('pilots.show.public');

    Route::get('page/{slug}', [PageController::class, 'show'])->name('pages.show');

    Route::get('profile/{id}', [ProfileController::class, 'show'])->name('profile.show.public');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('pilots', [UserController::class, 'index'])->name('pilots.index');

    Route::get('livemap', [LiveMapController::class, 'index'])->name('livemap.index');

    Route::get('lang/{lang}', [LanguageController::class, 'switchLang'])->name('lang.switch');

    Route::get('credits', [CreditsController::class, 'index'])->name('credits');
});

Route::group([
    'prefix' => 'oauth',
    'as'     => 'oauth.',
], function (): void {
    Route::get('{provider}/redirect', [OAuthController::class, 'redirectToProvider'])->name('redirect');
    Route::get('{provider}/callback', [OAuthController::class, 'handleProviderCallback'])->name('callback');
    Route::get('{provider}/logout', [OAuthController::class, 'logoutProvider'])->name('logout')->middleware('auth');
});

Route::get('/logout', [LoginController::class, 'logout'])->name(Logout::class);
Auth::routes(['verify' => true]);

/**
 * RouteForge admin API endpoints.
 *
 * Session-authenticated **RPC** endpoints — NOT a public REST API. They live
 * in routes/web.php (not routes/api.php) so the cookie session and CSRF
 * protection apply, are gated by `permission:edit:flight`, and have no
 * public consumers or versioned contract guarantee outside this codebase.
 *
 * The `/boot` endpoint is the SPA's bootstrap entry point — replaces the
 * legacy `window.routeforgeConfig` global and ships every piece of mount-time
 * state the React/Preact app needs in one round-trip. Bundles are NOT in the
 * boot envelope (paginated + searchable via `/bundles` instead).
 *
 * `/lint` and `/commit` carry an additional `throttle:60,1` (60 requests /
 * minute / user). The SPA's auto-lint effect debounces at 400ms, so normal
 * typing peaks well below the cap; the throttle exists to bound the
 * database load when a fast typist, stuck key, or buggy client fires lint
 * continuously. AbortController cancels in-flight requests client-side but
 * already-started server queries finish, so the throttle is the only
 * server-side backstop.
 */
Route::middleware(['web', 'auth', 'permission:edit:flight'])
    ->prefix('admin/route-forge/api')
    ->name('admin.routeforge.api.')
    ->group(function (): void {
        Route::get('boot', [RouteForgeController::class, 'boot'])->name('boot');
        Route::get('bundles', [RouteForgeController::class, 'bundles'])->name('bundles');
        Route::get('preview-airports', [RouteForgeController::class, 'previewAirports'])->name('preview-airports');
        Route::get('subfleets', [RouteForgeController::class, 'subfleets'])->name('subfleets');
        Route::get('airline-stats', [RouteForgeController::class, 'airlineStats'])->name('airline-stats');
        Route::post('lint', [RouteForgeController::class, 'lint'])
            ->middleware('throttle:60,1')
            ->name('lint');
        Route::post('commit', [RouteForgeController::class, 'commit'])
            ->middleware('throttle:60,1')
            ->name('commit');
    });

Route::get('/update', fn (): Redirector|RedirectResponse => redirect('/system/update'));
