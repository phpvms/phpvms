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

it('writes a manifest with the main entries', function () use ($manifest): void {
    $entries = json_decode((string) file_get_contents($manifest), true);

    expect($entries)->toHaveKeys(['resources/css/airside.css', 'resources/js/airside.js']);

    foreach ($entries as $entry) {
        expect(base_path('modules/phpvms-airside-theme/public/build/'.$entry['file']))->toBeFile();
    }
})->skip(fn (): bool => !file_exists($manifest), 'Run `pnpm run build:themes` first.');

it('renders the built asset URLs under the theme storage path', function (): void {
    useAirside();
    $public = deployedPublicPath();

    $html = Blade::render("@airsideVite(['resources/css/airside.css', 'resources/js/airside.js'])");

    expect($html)
        ->toContain('/storage/themes/airside/build/assets/airside-')
        ->toContain('rel="stylesheet"')
        ->toContain('type="module"');

    // The URL resolves to a real file through the link: the asset would load with status 200.
    preg_match('#/storage/themes/airside/build/assets/airside-[^"]+\.css#', $html, $match);

    expect($public.$match[0])->toBeFile();

    unlink($public.'/storage/themes/airside');
})->skip(fn (): bool => !file_exists($manifest), 'Run `pnpm run build:themes` first.');

it('leaves the shared Vite instance on its own build directory', function (): void {
    deployedPublicPath();
    $shared = app(Vite::class);

    Blade::render("@airsideVite(['resources/css/airside.css'])");

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
        $html = Blade::render("@airsideVite(['resources/css/airside.css', 'resources/js/airside.js'])");

        expect($html)->toContain('http://localhost:5199/@vite/client')
            ->and($html)->toContain('http://localhost:5199/resources/js/airside.js');
    } finally {
        unlink($hot);
    }

});
