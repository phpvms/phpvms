<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Theme\ThemeDocumentNormalizer;
use App\Services\Theme\ThemePublicationService;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

beforeEach(function (): void {
    config([
        'filesystems.theme_assets' => 'theme-assets',
        'themes.asset_delivery'    => 'route',
    ]);
    Storage::fake('theme-assets');
});

it('shares only the active normalized document and versions it with Inertia assets', function (): void {
    Theme::set('skylight');
    $middleware = app(HandleInertiaRequests::class);
    $request = Request::create('/dashboard');
    $before = $middleware->version($request);
    $document = app(ThemeDocumentNormalizer::class)->defaults();

    $published = app(ThemePublicationService::class)->publish('skylight', $document);
    $after = $middleware->version($request);
    $shared = $middleware->share($request);

    expect($after)->not->toBe($before)
        ->and($after)->toBe(hash('sha256', ((is_file(public_path('build/skylight/manifest.json'))
            ? (string) filemtime(public_path('build/skylight/manifest.json'))
            : parentInertiaVersion($request)) ?? '').'|'.$published->revision))
        ->and($shared)->toHaveKey('theme')
        ->and($shared['theme'])->toBe($document)
        ->and(array_keys($shared))->not->toContain('runtimeTheme');
});

it('uses a null theme prop and bundled application CSS when no revision is active', function (): void {
    Theme::set('skylight');
    $shared = app(HandleInertiaRequests::class)->share(Request::create('/'));

    expect($shared)->toHaveKey('theme')
        ->and($shared['theme'])->toBeNull();
});

it('orders production application theme and custom links before module scripts', function (): void {
    $themeName = 'skylight-test';
    $buildDir = public_path("build/{$themeName}");
    File::ensureDirectoryExists($buildDir);
    File::put($buildDir.'/manifest.json', json_encode([
        'src/app/main.ts' => [
            'file' => 'assets/app.js',
            'css'  => ['assets/app.css'],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        Theme::shouldReceive('get')->andReturn($themeName);
        app(ThemePublicationService::class)->publish($themeName, app(ThemeDocumentNormalizer::class)->defaults(), 'body { color: red; }');

        $html = view('layouts.skylight.spa', ['page' => []])->render();

        expect($html)->toContain('<body>')
            ->and(strpos($html, 'assets/app.css'))->toBeLessThan(strpos($html, '/theme.css'))
            ->and(strpos($html, '/theme.css'))->toBeLessThan(strpos($html, '/custom.css'))
            ->and(strpos($html, '/custom.css'))->toBeLessThan(strpos($html, 'assets/app.js'))
            ->and(strpos($html, "localStorage.getItem('skylight.theme')"))->toBeLessThan(strpos($html, 'assets/app.css'))
            ->and($html)->toContain("mode === 'auto'")
            ->toContain('prefers-color-scheme: dark');
    } finally {
        File::deleteDirectory($buildDir);
    }
});

it('orders the blocking Vite stylesheet and runtime links before development scripts', function (): void {
    $themeName = 'skylight-test';
    $buildDir = public_path("build/{$themeName}");
    File::ensureDirectoryExists($buildDir);
    File::put($buildDir.'/hot', 'http://localhost:5273');

    try {
        Theme::shouldReceive('get')->andReturn($themeName);
        app(ThemePublicationService::class)->publish($themeName, app(ThemeDocumentNormalizer::class)->defaults(), 'body { color: red; }');

        $html = view('layouts.skylight.spa', ['page' => []])->render();

        expect(strpos($html, '/src/app/app.css'))->toBeLessThan(strpos($html, '/theme.css'))
            ->and(strpos($html, '/theme.css'))->toBeLessThan(strpos($html, '/custom.css'))
            ->and(strpos($html, '/custom.css'))->toBeLessThan(strpos($html, '/@vite/client'))
            ->and(strpos($html, '/@vite/client'))->toBeLessThan(strpos($html, '/src/app/main.ts'));
    } finally {
        File::deleteDirectory($buildDir);
    }
});

it('renders the normal application shell without runtime links when no publication exists', function (): void {
    $themeName = 'skylight-test';
    $buildDir = public_path("build/{$themeName}");
    File::ensureDirectoryExists($buildDir);
    File::put($buildDir.'/manifest.json', json_encode([
        'src/app/main.ts' => [
            'file' => 'assets/app.js',
            'css'  => ['assets/app.css'],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        Theme::shouldReceive('get')->andReturn($themeName);
        $html = view('layouts.skylight.spa', ['page' => []])->render();

        expect($html)->toContain('assets/app.css')
            ->toContain('assets/app.js')
            ->toContain('/assets/img/favicon.png')
            ->toContain('<div id="app"></div>')
            ->not->toContain('/theme-assets/');
    } finally {
        File::deleteDirectory($buildDir);
    }
});

function parentInertiaVersion(Request $request): ?string
{
    return (new class() extends Middleware {})->version($request);
}
