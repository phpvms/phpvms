<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;

/**
 * 5.4: the theme builds on its own, into its own folder, and `@airsideVite`
 * points at it without touching the Vite every other `@vite` uses.
 */
$manifest = dirname(__DIR__, 3).'/modules/phpvms-airside-theme/public/build/manifest.json';

/**
 * Lay out public/ the way a deployment does: public/storage/themes/airside is
 * the theme's own public folder (the storage link plus ThemeAssetLinker).
 */
function deployedPublicPath(): string
{
    $public = sys_get_temp_dir().'/airside-public-'.uniqid('', true);
    mkdir($public.'/storage/themes', 0777, true);
    symlink(base_path('modules/phpvms-airside-theme/public'), $public.'/storage/themes/airside');
    app()->usePublicPath($public);

    return $public;
}

it('leaves the shared Vite instance on its own build directory', function (): void {
    deployedPublicPath();
    $shared = app(Vite::class);

    Blade::render("@airsideVite(['resources/css/styles.css'])");

    $afterDirectory = new ReflectionProperty($shared, 'buildDirectory')->getValue($shared);
    $afterHot = new ReflectionProperty($shared, 'hotFile')->getValue($shared);

    expect($afterDirectory)->toBe('build')
        ->and($afterHot)->toBeNull();
})->skip(fn (): bool => !file_exists($manifest), 'Run `pnpm run build:themes` first.');

it('uses the module hot file while the dev server runs', function (): void {
    deployedPublicPath();
    $hot = base_path('modules/phpvms-airside-theme/public/hot');
    file_put_contents($hot, 'http://localhost:5199');

    try {
        $html = Blade::render("@airsideVite(['resources/css/styles.css', 'resources/js/airside/app.ts'])");

        expect($html)->toContain('http://localhost:5199/@vite/client')
            ->and($html)->toContain('http://localhost:5199/resources/js/airside/app.ts');
    } finally {
        unlink($hot);
    }

});
