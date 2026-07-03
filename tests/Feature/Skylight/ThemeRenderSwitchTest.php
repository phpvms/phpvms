<?php

declare(strict_types=1);

use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Response;

/**
 * spa-theme-render-switch spec (skylight-dashboard-slice/specs/spa-theme-render-switch/spec.md)
 *
 * Verifies:
 *  - theme_kind() returns 'blade' for a theme with no kind key (seven)
 *  - theme_kind() returns 'spa' for a theme with kind: spa (skylight)
 *  - theme_setting() reads framework and manifest from the active theme
 *  - Response::themed macro exists and branches correctly
 *  - admin/api routes do NOT carry the Inertia middleware
 *
 * @group skylight
 */
pest()->group('skylight');

test('theme_kind defaults to blade when kind is absent', function (): void {
    Theme::set('seven');

    expect(theme_kind())->toBe('blade');
});

test('theme_kind returns spa for skylight theme', function (): void {
    Theme::set('skylight');

    expect(theme_kind())->toBe('spa');
});

test('theme_setting reads framework and manifest from skylight theme.json', function (): void {
    Theme::set('skylight');

    expect(theme_setting('framework'))->toBe('vue');
    expect(theme_setting('manifest'))->toBe('manifest.json');
});

test('theme_setting returns null for missing keys on blade theme', function (): void {
    Theme::set('seven');

    expect(theme_setting('framework'))->toBeNull();
    expect(theme_setting('manifest'))->toBeNull();
});

test('Response themed macro is registered', function (): void {
    expect(Response::hasMacro('themed'))->toBeTrue();
});

test('themed macro returns inertia response when active theme is spa', function (): void {
    Theme::set('skylight');

    $presenter = new class {
        public function toInertiaArray(): array { return ['kind' => 'inertia']; }

        public function toBladeArray(): array { return ['kind' => 'blade']; }
    };

    $response = response()->themed('Dashboard', 'dashboard.index', $presenter);

    // Inertia responses are Laravel responses with specific JSON structure.
    // When the X-Inertia header is absent (not a client-side nav request) the
    // first visit renders HTML via the root Blade template. The returned object
    // is an Inertia\Response, not a Illuminate\Http\Response, so we check the
    // class hierarchy instead of the status code which requires a full render.
    expect($response)->toBeInstanceOf(\Inertia\Response::class);
});

test('themed macro returns blade view when active theme is blade', function (): void {
    Theme::set('seven');

    $presenter = new class {
        public function toInertiaArray(): array { return ['kind' => 'inertia']; }

        public function toBladeArray(): array { return ['greeting' => 'hello']; }
    };

    // dashboard.index view may not render in the test environment so we just
    // verify the returned object is a Blade View (not an Inertia response).
    try {
        $response = response()->themed('Dashboard', 'dashboard.index', $presenter);
        // If the view renders, it's a Blade View or Response — not Inertia.
        expect($response)->not->toBeInstanceOf(\Inertia\Response::class);
    } catch (\Illuminate\View\ViewException|\InvalidArgumentException $e) {
        // View doesn't exist in the test env — that's expected and still proves
        // the macro took the Blade path (it tried to render the view).
        expect($e->getMessage())->toContain('dashboard');
    }
});

test('admin routes do not carry the Inertia middleware', function (): void {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    $adminRoute = $routes->first(fn ($r) => $r->uri() === 'admin');

    expect($adminRoute)->not->toBeNull();

    $middleware = $adminRoute->middleware();

    expect($middleware)->not->toContain(\Inertia\Middleware::class);
});

test('api routes do not carry the Inertia middleware', function (): void {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    // api/user is a representative api route
    $apiRoute = $routes->first(fn ($r) => str_starts_with($r->uri(), 'api/'));

    expect($apiRoute)->not->toBeNull();

    $middleware = $apiRoute->middleware();

    expect($middleware)->not->toContain(\Inertia\Middleware::class);
});

test('frontend dashboard route carries the Inertia middleware', function (): void {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    $dashRoute = $routes->first(fn ($r) => $r->uri() === 'dashboard' && in_array('GET', $r->methods(), true));

    expect($dashRoute)->not->toBeNull();

    $middleware = $dashRoute->middleware();

    expect($middleware)->toContain(\Inertia\Middleware::class);
});
