<?php

declare(strict_types=1);

/*
 * Web routes for the sample-blade-widget addon.
 *
 * These are loaded by SampleBladeWidgetServiceProvider::registerRoutes() inside
 * a group with the `web` + `auth` middleware, so everything here is behind a
 * logged-in session (which is also what makes the SPA's credentialed fetch and
 * the island CSRF token work).
 *
 * The route is named in FULL here (`sample-blade-widget.notams`) rather than
 * relying on a group name prefix. Two things must stay in sync with this file:
 *   - the widget `endpoint` the provider registers is the LITERAL path
 *     '/widgets/sample-notams' (NOT route() by name — see the provider's note
 *     on boot-time route ordering), so it must match this route's path.
 *   - the Blade fragment's <form action> uses route('sample-blade-widget.notams'),
 *     which renders at request time and resolves to that same path.
 */

use Illuminate\Support\Facades\Route;
use Modules\SampleBladeWidget\Http\Controllers\NotamsController;

Route::get('/widgets/sample-notams', [NotamsController::class, 'show'])
    ->name('sample-blade-widget.notams');
