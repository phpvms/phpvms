<?php

declare(strict_types=1);

use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * Shared set-up for the airside feature tests: airside is the active theme,
 * Vite is faked, and `/airside-probe` renders a fixture view that extends `app`.
 */
function useAirside(): User
{
    updateSetting('general.theme', 'airside');
    Theme::set('airside');

    View::addNamespace('airsidetest', base_path('tests/fixtures/views/airside'));

    Route::middleware('web')->get('/airside-probe', fn (): Factory|\Illuminate\Contracts\View\View => view('airsidetest::addon-page'));
    Route::middleware(['web', 'auth'])->get('/airside-probe/pilot', fn (): Factory|\Illuminate\Contracts\View\View => view('airsidetest::addon-page'));

    Route::middleware(['web', 'auth'])->get('/airside-probe/save', fn () => redirect('/airside-probe/pilot')->with('success', 'Saved the profile'));
    Route::middleware(['web', 'auth'])->get('/flights/bids/probe', fn (): Factory|\Illuminate\Contracts\View\View => view('airsidetest::addon-page'));

    return User::factory()->create();
}
