<?php

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

Route::get('cron/{id}', [MaintenanceController::class, 'cron'])->name('maintenance.cron');

Route::get('news', [NewsController::class, 'index']);
Route::get('status', [StatusController::class, 'status']);
Route::get('version', [StatusController::class, 'status']);

/*
 * These need to be authenticated with a user's API key
 */
Route::group(['middleware' => ['api.auth']], function () {
    Route::get('airlines', [AirlineController::class, 'index']);
    Route::get('airlines/{id}', [AirlineController::class, 'get']);

    Route::get('airports', [AirportController::class, 'index']);
    Route::get('airports/{id}', [AirportController::class, 'get']);
    Route::get('airports/{id}/lookup', [AirportController::class, 'lookup']);
    Route::get('airports/{id}/distance/{to}', [AirportController::class, 'distance']);

    Route::get('fleet', [FleetController::class, 'index']);
    Route::get('fleet/aircraft/{id}', [FleetController::class, 'get_aircraft']);

    Route::get('subfleet', [FleetController::class, 'index']);
    Route::get('subfleet/aircraft/{id}', [FleetController::class, 'get_aircraft']);

    Route::get('flights', [FlightController::class, 'index']);
    Route::get('flights/search', [FlightController::class, 'search']);
    Route::get('flights/{id}', [FlightController::class, 'get']);
    Route::get('flights/{id}/briefing', [FlightController::class, 'briefing'])->name('flights.briefing');
    Route::get('flights/{id}/route', [FlightController::class, 'route']);
    Route::get('flights/{id}/aircraft', [FlightController::class, 'aircraft']);

    Route::get('pireps', [UserController::class, 'pireps']);
    Route::put('pireps/{pirep_id}', [PirepController::class, 'update']);

    /*
     * ACARS
     */
    Route::post('pireps/prefile', [PirepController::class, 'prefile']);
    Route::post('pireps/{pirep_id}', [PirepController::class, 'update']);
    Route::patch('pireps/{pirep_id}', [PirepController::class, 'update']);
    Route::put('pireps/{pirep_id}/update', [PirepController::class, 'update']);
    Route::post('pireps/{pirep_id}/update', [PirepController::class, 'update']);
    Route::post('pireps/{pirep_id}/file', [PirepController::class, 'file']);
    Route::post('pireps/{pirep_id}/comments', [PirepController::class, 'comments_post']);
    Route::put('pireps/{pirep_id}/cancel', [PirepController::class, 'cancel']);
    Route::delete('pireps/{pirep_id}/cancel', [PirepController::class, 'cancel']);

    Route::get('pireps/{pirep_id}/fields', [PirepController::class, 'fields_get']);
    Route::post('pireps/{pirep_id}/fields', [PirepController::class, 'fields_post']);

    Route::get('pireps/{pirep_id}/finances', [PirepController::class, 'finances_get']);
    Route::post('pireps/{pirep_id}/finances/recalculate', [PirepController::class, 'finances_recalculate']);

    Route::get('pireps/{pirep_id}/route', [PirepController::class, 'route_get']);
    Route::post('pireps/{pirep_id}/route', [PirepController::class, 'route_post']);
    Route::delete('pireps/{pirep_id}/route', [PirepController::class, 'route_delete']);

    Route::get('pireps/{pirep_id}/comments', [PirepController::class, 'comments_get']);

    Route::get('pireps/{pirep_id}/acars/position', [AcarsController::class, 'acars_get']);
    Route::post('pireps/{pirep_id}/acars/position', [AcarsController::class, 'acars_store']);
    Route::post('pireps/{pirep_id}/acars/positions', [AcarsController::class, 'acars_store']);

    Route::post('pireps/{pirep_id}/acars/events', [AcarsController::class, 'acars_events']);
    Route::post('pireps/{pirep_id}/acars/logs', [AcarsController::class, 'acars_logs']);

    // Route::get('settings', [SettingsController::class, 'index']);

    // This is the info of the user whose token is in use
    Route::get('user', [UserController::class, 'index']);
    Route::get('user/fleet', [UserController::class, 'fleet']);
    Route::get('user/pireps', [UserController::class, 'pireps']);

    Route::get('bids', [UserController::class, 'bids']);
    Route::get('bids/{id}', [UserController::class, 'get_bid']);
    Route::get('user/bids/{id}', [UserController::class, 'get_bid']);

    Route::get('user/bids', [UserController::class, 'bids']);
    Route::put('user/bids', [UserController::class, 'bids']);
    Route::post('user/bids', [UserController::class, 'bids']);
    Route::delete('user/bids', [UserController::class, 'bids']);

    Route::get('users/me', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'get']);
    Route::get('users/{id}/fleet', [UserController::class, 'fleet']);
    Route::get('users/{id}/pireps', [UserController::class, 'pireps']);

    Route::get('users/{id}/bids', [UserController::class, 'bids']);
    Route::put('users/{id}/bids', [UserController::class, 'bids']);
    Route::post('users/{id}/bids', [UserController::class, 'bids']);
});
