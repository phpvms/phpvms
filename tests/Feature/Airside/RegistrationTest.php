<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonAssetLinker;
use App\Addons\Support\AddonCacheRefresher;
use App\Addons\Support\BootCache;
use App\Addons\Support\OctaneReloader;
use App\Addons\Support\ThemeAssetLinker;
use App\Models\Addon;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

/**
 * D12: airside is registered in tests, on a fresh install and on update.
 */
beforeEach(function (): void {
    Addon::query()->delete();

    $this->work = sys_get_temp_dir().'/airside-registration-'.uniqid('', true);
    File::ensureDirectoryExists($this->work.'/image');
    File::ensureDirectoryExists($this->work.'/installed');
    File::ensureDirectoryExists($this->work.'/themes');

    Config::set('addons.paths.base', $this->work.'/image');
    Config::set('addons.paths.installed', $this->work.'/installed');
    Config::set('addons.paths.themes', $this->work.'/themes');
    Config::set('addons.paths.theme_assets', $this->work.'/public-disk/themes');
    Config::set('addons.paths.assets', $this->work.'/ext');

    app()->forgetInstance(AddonAssetLinker::class);
    app()->forgetInstance(ThemeAssetLinker::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

/**
 * A bundled theme in the image root, shaped like modules/phpvms-airside-theme.
 */
function bundledThemeInImage(string $work, bool $bundled = true): string
{
    $dir = $work.'/image/phpvms-fixture-theme';
    File::ensureDirectoryExists($dir.'/public/build');
    File::ensureDirectoryExists($dir.'/resources/views');

    File::put($dir.'/module.json', json_encode([
        'name'        => 'FixtureTheme',
        'registry_id' => 'phpvms/fixture-theme',
        'type'        => 'theme',
        'version'     => '1.0.0',
        'bundled'     => $bundled,
        'providers'   => [],
    ]));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\FixtureTheme\\' => 'app']]]));
    File::put($dir.'/theme.json', json_encode(['name' => 'fixture', 'kind' => 'blade']));
    File::put($dir.'/public/build/manifest.json', '{}');

    return $dir;
}

it('ships airside as a bundled theme addon', function (): void {
    $manifest = json_decode((string) file_get_contents(base_path('modules/phpvms-airside-theme/module.json')), true);
    $theme = json_decode((string) file_get_contents(base_path('modules/phpvms-airside-theme/theme.json')), true);

    expect($manifest)->toMatchArray(['registry_id' => 'phpvms/airside-theme', 'type' => 'theme', 'bundled' => true])
        ->and($theme)->toMatchArray(['name' => 'airside', 'kind' => 'blade', 'data' => 'spa']);
});

it('boots a test run with airside registered', function (): void {
    expect(Theme::exists('airside'))->toBeTrue();
});

it('links the assets of a bundled theme that discovery enables', function (): void {
    bundledThemeInImage($this->work);

    app(AddonDiscoveryService::class)->discoverNewAddons();

    $link = $this->work.'/public-disk/themes/fixture';

    expect(Addon::query()->where('registry_id', 'phpvms/fixture-theme')->value('enabled'))->toBeTruthy()
        ->and(is_link($link))->toBeTrue()
        ->and(File::exists($link.'/build/manifest.json'))->toBeTrue();
});

it('links the public/ext folder of a bundled addon as well', function (): void {
    bundledThemeInImage($this->work);

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(is_link($this->work.'/ext/fixturetheme'))->toBeTrue();
});

it('leaves a non-bundled theme unlinked until it is installed', function (): void {
    bundledThemeInImage($this->work, bundled: false);

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'phpvms/fixture-theme')->value('enabled'))->toBeFalsy()
        ->and(is_link($this->work.'/public-disk/themes/fixture'))->toBeFalse();
});

it('does not fail discovery when the link cannot be made', function (): void {
    bundledThemeInImage($this->work);
    // A file where the link directory must go.
    File::ensureDirectoryExists($this->work.'/public-disk');
    File::put($this->work.'/public-disk/themes', 'not a directory');

    expect(fn () => app(AddonDiscoveryService::class)->discoverNewAddons())->not->toThrow(Throwable::class);
    expect(Addon::query()->where('registry_id', 'phpvms/fixture-theme')->exists())->toBeTrue();
});

it('rebuilds the boot cache and reloads Octane when the installer or updater finishes', function (): void {
    bundledThemeInImage($this->work);
    app(BootCache::class)->delete();

    $this->mock(OctaneReloader::class, fn ($mock) => $mock->shouldReceive('reload')->once());

    app(AddonCacheRefresher::class)->refresh();

    $registryIds = array_map(static fn (AddonBootCache $entry): string => $entry->registryId, app(BootCache::class)->read());

    expect($registryIds)->toContain('phpvms/fixture-theme');
});

it('does not fail an install when the cache rebuild breaks', function (): void {
    $this->mock(OctaneReloader::class, fn ($mock) => $mock->shouldReceive('reload')->andThrow(new RuntimeException('no octane')));

    expect(fn () => app(AddonCacheRefresher::class)->refresh())->not->toThrow(Throwable::class);
});
