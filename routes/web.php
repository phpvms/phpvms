<?php

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'     => '',
    'as'         => 'frontend.',
    'middleware' => (config('phpvms.registration.email_verification', false) ? ['auth', 'verified'] : ['auth']),
], function () {
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
], function () {
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
], function () {
    Route::get('{provider}/redirect', [OAuthController::class, 'redirectToProvider'])->name('redirect');
    Route::get('{provider}/callback', [OAuthController::class, 'handleProviderCallback'])->name('callback');
    Route::get('{provider}/logout', [OAuthController::class, 'logoutProvider'])->name('logout')->middleware('auth');
});

Route::get('/logout', [LoginController::class, 'logout'])->name(Logout::class);
Auth::routes(['verify' => true]);

Route::get('/update', function () {
    return redirect('/system/update');
});
