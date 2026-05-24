<?php

declare(strict_types=1);

use App\Http\Middleware\SetActiveTheme;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * SetActiveTheme used to early-return on admin/api/install paths, leaving
 * whatever theme the previous frontend request had set on the long-lived
 * Theme singleton. Under Octane that's a per-worker leak. The middleware
 * now always sets a baseline theme.
 *
 * @group octane
 */
test('admin path does not inherit theme from a prior frontend request', function (): void {
    updateSetting('general.theme', 'seven');

    $middleware = new SetActiveTheme();

    // Request 1: frontend route — middleware sets the configured theme.
    $middleware->handle(Request::create('/'), fn (): Response => new Response());

    $themeAfterFrontend = Theme::get();

    // Request 2: admin route — same booted Theme singleton.
    $middleware->handle(Request::create('/admin'), fn (): Response => new Response());
    $themeAfterAdmin = Theme::get();

    // Both should equal the configured default. The bug we're guarding
    // against was: request 2 inherits request 1's theme implicitly because
    // the middleware short-circuits on /admin and never resets the singleton.
    expect($themeAfterAdmin)->toBe($themeAfterFrontend);
});
