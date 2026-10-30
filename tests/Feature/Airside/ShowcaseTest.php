<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/Support.php';

/**
 * 5.5: the token showcase renders (light and dark share the markup; the mode is
 * a class on <html>), and nothing routes to it.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

it('renders the token showcase with the ts- components', function (): void {
    // Through a test-only route: the form fields read the session's `$errors`.
    Route::middleware('web')->get('/airside-views/showcase', fn (): Factory|View => view('airside::showcase'));

    $html = $this->get('/airside-views/showcase')->assertOk()->getContent();

    expect($html)
        ->toContain('Token showcase')
        ->toContain('role="button"')
        ->toContain('pv-eyebrow')
        ->toContain('bg-primary-600')
        ->toContain('var(--pv-ink-950)')
        ->toContain("select('dark')");
});

it('has no public route to the showcase', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri();

    expect($uris->filter(fn (string $uri): bool => str_contains($uri, 'showcase')))->toBeEmpty();
});

it("builds on TallStackUI's stylesheet with no token sheet of its own", function (): void {
    $css = file_get_contents(base_path('modules/phpvms-airside-theme/resources/css/airside.css'));

    expect($css)
        ->toContain('vendor/tallstackui/tallstackui/css/v4.css')
        ->not->toContain('--pv-')
        ->not->toContain('--color-primary-')
        ->not->toContain('@layer');
});
