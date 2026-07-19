<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AcarsController;
use App\Http\Controllers\Api\AirlineController;
use App\Http\Controllers\Api\AirportController;
use App\Http\Controllers\Api\FleetController;
use App\Http\Controllers\Api\FlightController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PirepController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StatusController::class, 'status']);

Route::get('acars', [AcarsController::class, 'live_flights']);
Route::get('acars/geojson', [AcarsController::class, 'pireps_geojson']);

Route::get('airports/hubs', [AirportController::class, 'index_hubs']);
Route::get('airports/search', [AirportController::class, 'search']);

Route::get('pireps/{pirep_id}', [PirepController::class, 'get']);
Route::get('pireps/{pirep_id}/acars/geojson', [AcarsController::class, 'acars_geojson']);

Route::get('cron/{id}', [MaintenanceController::class, 'cron'])->name('api.maintenance.cron');

Route::get('news', [NewsController::class, 'index']);
Route::get('status', [StatusController::class, 'status']);
Route::get('version', [StatusController::class, 'status']);

/*
 * These need to be authenticated with a user's API key
 */
Route::group(['middleware' => ['api.auth']], function (): void {
    // Each route enforces a scope via the `scope` middleware. Legacy api_key
    // clients hold the wildcard scope and bypass these checks (see
    // App\Http\Middleware\CheckApiScope); Passport tokens must hold the listed
    // scope. Read scopes end in `:read`, write/ACARS scopes end in `:write`.
    Route::get('airlines', [AirlineController::class, 'index'])->middleware('scope:airlines:read');
    Route::get('airlines/{id}', [AirlineController::class, 'get'])->middleware('scope:airlines:read');

    Route::get('airports', [AirportController::class, 'index'])->middleware('scope:airports:read');
    Route::get('airports/{airport}', [AirportController::class, 'get'])->middleware('scope:airports:read');
    Route::get('airports/{id}/lookup', [AirportController::class, 'lookup'])->middleware('scope:airports:read');
    Route::get('airports/{id}/distance/{to}', [AirportController::class, 'distance'])->middleware('scope:airports:read');

    Route::get('fleet', [FleetController::class, 'index'])->middleware('scope:fleet:read');
    Route::get('fleet/aircraft/{id}', [FleetController::class, 'get_aircraft'])->middleware('scope:fleet:read');

    Route::get('subfleet', [FleetController::class, 'index'])->middleware('scope:fleet:read');
    Route::get('subfleet/aircraft/{id}', [FleetController::class, 'get_aircraft'])->middleware('scope:fleet:read');

    Route::get('flights', [FlightController::class, 'index'])->middleware('scope:flights:read');
    Route::get('flights/search', [FlightController::class, 'search'])->middleware('scope:flights:read');
    Route::get('flights/{id}', [FlightController::class, 'get'])->middleware('scope:flights:read');
    Route::get('flights/{id}/briefing', [FlightController::class, 'briefing'])->middleware('scope:flights:read')->name('api.flights.briefing');
    Route::get('flights/{id}/route', [FlightController::class, 'route'])->middleware('scope:flights:read');
    Route::get('flights/{id}/aircraft', [FlightController::class, 'aircraft'])->middleware('scope:flights:read');

    Route::get('pireps', [UserController::class, 'pireps'])->middleware('scope:pireps:read');
    Route::put('pireps/{pirep_id}', [PirepController::class, 'update'])->middleware('scope:pireps:write');

    /*
     * ACARS
     */
    Route::post('pireps/prefile', [PirepController::class, 'prefile'])->middleware('scope:pireps:write');
    Route::post('pireps/{pirep_id}', [PirepController::class, 'update'])->middleware('scope:pireps:write');
    Route::patch('pireps/{pirep_id}', [PirepController::class, 'update'])->middleware('scope:pireps:write');
    Route::put('pireps/{pirep_id}/update', [PirepController::class, 'update'])->middleware('scope:pireps:write');
    Route::post('pireps/{pirep_id}/update', [PirepController::class, 'update'])->middleware('scope:pireps:write');
    Route::post('pireps/{pirep_id}/file', [PirepController::class, 'file'])->middleware('scope:pireps:write');
    Route::post('pireps/{pirep_id}/comments', [PirepController::class, 'comments_post'])->middleware('scope:pireps:write');
    Route::put('pireps/{pirep_id}/cancel', [PirepController::class, 'cancel'])->middleware('scope:pireps:write');
    Route::delete('pireps/{pirep_id}/cancel', [PirepController::class, 'cancel'])->middleware('scope:pireps:write');

    Route::get('pireps/{pirep_id}/fields', [PirepController::class, 'fields_get'])->middleware('scope:pireps:read');
    Route::post('pireps/{pirep_id}/fields', [PirepController::class, 'fields_post'])->middleware('scope:pireps:write');

    Route::get('pireps/{pirep_id}/finances', [PirepController::class, 'finances_get'])->middleware('scope:pireps:read');
    Route::post('pireps/{pirep_id}/finances/recalculate', [PirepController::class, 'finances_recalculate'])->middleware('scope:pireps:write');

    Route::get('pireps/{pirep_id}/route', [PirepController::class, 'route_get'])->middleware('scope:pireps:read');
    Route::post('pireps/{pirep_id}/route', [PirepController::class, 'route_post'])->middleware('scope:pireps:write');
    Route::delete('pireps/{pirep_id}/route', [PirepController::class, 'route_delete'])->middleware('scope:pireps:write');

    Route::get('pireps/{pirep_id}/comments', [PirepController::class, 'comments_get'])->middleware('scope:pireps:read');

    Route::get('pireps/{pirep_id}/acars/position', [AcarsController::class, 'acars_get'])->middleware('scope:pireps:read');
    Route::post('pireps/{pirep_id}/acars/position', [AcarsController::class, 'acars_store'])->middleware('scope:pireps:write');
    Route::post('pireps/{pirep_id}/acars/positions', [AcarsController::class, 'acars_store'])->middleware('scope:pireps:write');

    Route::post('pireps/{pirep_id}/acars/events', [AcarsController::class, 'acars_events'])->middleware('scope:pireps:write');
    Route::post('pireps/{pirep_id}/acars/logs', [AcarsController::class, 'acars_logs'])->middleware('scope:pireps:write');

    // Route::get('settings', [SettingsController::class, 'index']);

    // This is the info of the user whose token is in use
    Route::get('user', [UserController::class, 'index'])->middleware('scope:user:read');
    Route::get('user/fleet', [UserController::class, 'fleet'])->middleware('scope:user:read');
    Route::get('user/pireps', [UserController::class, 'pireps'])->middleware('scope:user:read');

    Route::get('bids', [UserController::class, 'bids'])->middleware('scope:user:read');
    Route::get('bids/{id}', [UserController::class, 'get_bid'])->middleware('scope:user:read');
    Route::get('user/bids/{id}', [UserController::class, 'get_bid'])->middleware('scope:user:read');

    Route::get('user/bids', [UserController::class, 'bids'])->middleware('scope:user:read');
    Route::put('user/bids', [UserController::class, 'bids'])->middleware('scope:bids:write');
    Route::post('user/bids', [UserController::class, 'bids'])->middleware('scope:bids:write');
    Route::delete('user/bids', [UserController::class, 'bids'])->middleware('scope:bids:write');

    Route::get('users/me', [UserController::class, 'index'])->middleware('scope:user:read');
    Route::get('users/{id}', [UserController::class, 'get'])->middleware('scope:user:read');
    Route::get('users/{id}/fleet', [UserController::class, 'fleet'])->middleware('scope:user:read');
    Route::get('users/{id}/pireps', [UserController::class, 'pireps'])->middleware('scope:user:read');

    Route::get('users/{id}/bids', [UserController::class, 'bids'])->middleware('scope:user:read');
    Route::put('users/{id}/bids', [UserController::class, 'bids'])->middleware('scope:bids:write');
    Route::post('users/{id}/bids', [UserController::class, 'bids'])->middleware('scope:bids:write');

    Route::post('users/simbrief_username', [UserController::class, 'simbrief_username'])->middleware('scope:settings:write')->name('api.users.simbrief_username');
});
