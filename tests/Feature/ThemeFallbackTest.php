<?php

declare(strict_types=1);

use App\Http\Middleware\SetActiveTheme;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Themes::set() accepts an unknown name and builds a parentless Theme, so the
 * `extends` chain vanishes and every themed view 404s at the finder. The
 * middleware must land on a theme that exists instead.
 */
it('falls back to an installed theme when the configured one is unknown', function (): void {
    updateSetting('general.theme', 'does-not-exist');

    new SetActiveTheme()->handle(Request::create('/'), fn (): Response => new Response());

    expect(Theme::exists(Theme::get()))->toBeTrue();
});

it('serves a themed view under a theme that is missing from the cache', function (): void {
    updateSetting('general.theme', 'does-not-exist');

    $this->followingRedirects()->get('/')->assertOk();
});
