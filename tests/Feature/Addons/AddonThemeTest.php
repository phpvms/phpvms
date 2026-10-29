<?php

declare(strict_types=1);

use App\Addons\AddonDeleteOutcome;
use App\Addons\AddonRegistry;
use App\Addons\Sources\ZipSource;
use App\Addons\Support\AddonAssetLinker;
use App\Addons\Support\AddonRoots;
use App\Addons\Support\BootCache;
use App\Addons\Support\ThemeAddonRegistrar;
use App\Addons\Support\ThemeAssetLinker;
use App\Exceptions\AddonInstallException;
use App\Http\Middleware\SetActiveTheme;
use App\Models\Addon;
use App\Services\Installer\InstallerService;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

/**
 * Addons of type "theme": installed into their own writable root, registered
 * with the theme engine from the boot cache, and served from the public disk
 * through a link inside the storage volume.
 */
beforeEach(function (): void {
    Addon::query()->delete();

    $this->work = sys_get_temp_dir().'/addon-themes-'.uniqid('', true);
    File::ensureDirectoryExists($this->work.'/image');
    File::ensureDirectoryExists($this->work.'/installed');
    File::ensureDirectoryExists($this->work.'/themes');

    Config::set('addons.paths.base', $this->work.'/image');
    Config::set('addons.paths.installed', $this->work.'/installed');
    Config::set('addons.paths.themes', $this->work.'/themes');
    Config::set('addons.paths.theme_assets', $this->work.'/public-disk/themes');
    Config::set('addons.paths.assets', $this->work.'/ext');

    // The linkers read their base path when first resolved, and the registry
    // holds them, so all three are rebuilt against the paths above.
    app()->forgetInstance(AddonAssetLinker::class);
    app()->forgetInstance(ThemeAssetLinker::class);
    app()->forgetInstance(AddonRegistry::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

/**
 * A theme addon zip as the registry would serve it.
 *
 * @param array<string, string> $extraFiles path => contents
 */
function themeAddonZip(string $workDir, string $themeName = 'testsky', array $extraFiles = []): string
{
    // Hashed: the theme name may be deliberately invalid as a path ("acme/sky").
    $path = $workDir.'/theme-'.md5($themeName.uniqid('', true)).'.zip';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', json_encode([
        'name'        => 'Testsky',
        'registry_id' => 'acme/testsky',
        'type'        => 'theme',
        'version'     => '1.0.0',
        'providers'   => [],
    ]));
    $zip->addFromString('composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\Testsky\\' => '']],
    ]));
    $zip->addFromString('theme.json', json_encode(['name' => $themeName, 'kind' => 'blade']));
    $zip->addFromString('resources/views/home.blade.php', 'testsky home');
    $zip->addFromString('public/css/app.css', 'body {}');

    foreach ($extraFiles as $file => $contents) {
        $zip->addFromString($file, $contents);
    }

    $zip->close();

    return $path;
}

function installThemeAddon(string $workDir, array $extraFiles = []): Addon
{
    return app(AddonRegistry::class)->install(new ZipSource(themeAddonZip($workDir, extraFiles: $extraFiles)));
}

// ── Placement ───────────────────────────────────────────────────────────────

it('installs a theme addon into the themes root, not the addons root', function (): void {
    $addon = installThemeAddon($this->work);

    expect($addon->type)->toBe('theme')
        ->and(realpath($addon->getPath()))->toBe(realpath($this->work.'/themes/acme-testsky'))
        ->and(File::isDirectory($this->work.'/installed/acme-testsky'))->toBeFalse();
});

it('routes the install root by addon type', function (): void {
    expect(AddonRoots::installedFor('theme'))->toBe($this->work.'/themes')
        ->and(AddonRoots::installedFor('module'))->toBe($this->work.'/installed')
        ->and(AddonRoots::all())->toBe([$this->work.'/image', $this->work.'/installed', $this->work.'/themes']);
});

// ── Asset link ──────────────────────────────────────────────────────────────

it('links the theme public folder into the public disk with a relative link', function (): void {
    $addon = installThemeAddon($this->work);
    $link = $this->work.'/public-disk/themes/testsky';

    expect(is_link($link))->toBeTrue()
        ->and(readlink($link))->not->toStartWith('/')
        ->and(realpath($link))->toBe(realpath($addon->getPath().'/public'))
        ->and(File::get($link.'/css/app.css'))->toBe('body {}');
});

it('rebuilds a missing theme link on relink', function (): void {
    installThemeAddon($this->work);
    $link = $this->work.'/public-disk/themes/testsky';
    unlink($link);

    app(AddonRegistry::class)->relinkAssets();

    expect(File::get($link.'/css/app.css'))->toBe('body {}');
});

it('removes the theme link, and only the link, when the theme is deleted', function (): void {
    $addon = installThemeAddon($this->work);
    $path = $addon->getPath();

    expect(app(AddonRegistry::class)->delete('Testsky'))->toBe(AddonDeleteOutcome::Removed)
        ->and(file_exists($this->work.'/public-disk/themes/testsky'))->toBeFalse()
        ->and(is_link($this->work.'/public-disk/themes/testsky'))->toBeFalse()
        ->and(File::isDirectory($path))->toBeFalse();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('refuses an addon with a PHP file in public/', function (string $file): void {
    expect(fn (): Addon => installThemeAddon($this->work, [$file => '<?php echo 1;']))
        ->toThrow(AddonInstallException::class, 'must not contain PHP or server config files');

    expect(Addon::query()->count())->toBe(0)
        ->and(File::isDirectory($this->work.'/themes/acme-testsky'))->toBeFalse();
})->with([
    'php'           => 'public/shell.php',
    'nested'        => 'public/js/vendor/x.phtml',
    'double ext'    => 'public/img/a.php.png',
    'upper case'    => 'public/A.PHP',
    'phar'          => 'public/x.phar',
    'versioned php' => 'public/x.php7',
    'in .git'       => 'public/.git/hook.php',
    'in CVS'        => 'public/CVS/x.php',
    '.htaccess'     => 'public/.htaccess',
    '.user.ini'     => 'public/js/.user.ini',
]);

it('allows PHP files outside public/', function (): void {
    $addon = installThemeAddon($this->work, ['src/Helper.php' => '<?php']);

    expect($addon->exists)->toBeTrue();
});

// ── Lifecycle ───────────────────────────────────────────────────────────────

it('refuses to disable the active theme', function (): void {
    installThemeAddon($this->work);
    updateSetting('general.theme', 'testsky');

    expect(fn () => app(AddonRegistry::class)->disable('Testsky'))
        ->toThrow(RuntimeException::class, 'active theme');

    expect(Addon::query()->where('name', 'Testsky')->value('enabled'))->toBeTruthy();
});

it('refuses to delete the active theme', function (): void {
    $addon = installThemeAddon($this->work);
    updateSetting('general.theme', 'testsky');

    expect(fn () => app(AddonRegistry::class)->delete('Testsky'))
        ->toThrow(RuntimeException::class, 'active theme');

    expect(File::isDirectory($addon->getPath()))->toBeTrue();
});

it('disables a theme addon that is not the active theme', function (): void {
    installThemeAddon($this->work);
    updateSetting('general.theme', 'seven');

    app(AddonRegistry::class)->disable('Testsky');

    expect(Addon::query()->where('name', 'Testsky')->value('enabled'))->toBeFalsy();
});

// ── Registration ────────────────────────────────────────────────────────────

it('registers an enabled theme addon with the theme engine', function (): void {
    $addon = installThemeAddon($this->work);
    Config::set('themes.themes', []);

    app(ThemeAddonRegistrar::class)->register();

    $entry = config('themes.themes.testsky');
    $themesPath = config('themes.themes_path');

    expect($entry['name'])->toBe('testsky')
        ->and($entry['kind'])->toBe('blade')
        ->and($entry['asset-path'])->toBe('storage/themes/testsky')
        ->and(realpath($themesPath.'/'.$entry['views-path']))->toBe(realpath($addon->getPath().'/resources/views'));
});

it('renders views from a registered theme addon', function (): void {
    installThemeAddon($this->work);
    Config::set('themes.themes', []);

    app(ThemeAddonRegistrar::class)->register();
    Theme::scanThemes();
    Theme::set('testsky');

    expect(view('home')->render())->toBe('testsky home');
});

it('does not register a disabled theme addon', function (): void {
    installThemeAddon($this->work);
    updateSetting('general.theme', 'seven');
    app(AddonRegistry::class)->disable('Testsky');
    Config::set('themes.themes', []);

    app(ThemeAddonRegistrar::class)->register();

    expect(config('themes.themes'))->not->toHaveKey('testsky');
});

it('refuses to install a theme addon named like a folder theme', function (): void {
    expect(fn (): Addon => app(AddonRegistry::class)->install(new ZipSource(themeAddonZip($this->work, 'seven'))))
        ->toThrow(AddonInstallException::class, 'A theme named "seven" already exists');

    expect(Addon::query()->count())->toBe(0);
});

it('refuses a theme name that cannot be a path segment', function (string $name): void {
    expect(fn (): Addon => app(AddonRegistry::class)->install(new ZipSource(themeAddonZip($this->work, $name))))
        ->toThrow(AddonInstallException::class, 'Invalid theme name');

    expect(Addon::query()->count())->toBe(0);
})->with(['slash' => 'acme/sky', 'dot dot' => '..']);

it('treats theme names that differ only by case as the same name', function (): void {
    expect(fn (): Addon => app(AddonRegistry::class)->install(new ZipSource(themeAddonZip($this->work, 'Skylight'))))
        ->toThrow(AddonInstallException::class, 'already exists');
});

it('skips a theme addon whose name cannot be registered, without breaking boot', function (): void {
    $addon = installThemeAddon($this->work);
    // As if an older install let the name through.
    File::put($addon->getPath().'/theme.json', json_encode(['name' => 'acme/sky']));
    Config::set('themes.themes', []);

    app(ThemeAddonRegistrar::class)->register();

    expect(config('themes.themes'))->toBe([]);
});

it('refuses an update that renames the active theme', function (): void {
    installThemeAddon($this->work);
    updateSetting('general.theme', 'testsky');

    expect(fn (): Addon => app(AddonRegistry::class)->update('Testsky', new ZipSource(themeAddonZip($this->work, 'renamed'))))
        ->toThrow(AddonInstallException::class, 'renames or removes the active theme');

    expect(is_link($this->work.'/public-disk/themes/testsky'))->toBeTrue();
});

it('moves the asset link when an update renames an inactive theme', function (): void {
    installThemeAddon($this->work);
    updateSetting('general.theme', 'seven');

    app(AddonRegistry::class)->update('Testsky', new ZipSource(themeAddonZip($this->work, 'renamed')));

    expect(is_link($this->work.'/public-disk/themes/testsky'))->toBeFalse()
        ->and(is_link($this->work.'/public-disk/themes/renamed'))->toBeTrue();
});

it('refuses a second theme addon with the same theme name', function (): void {
    installThemeAddon($this->work);

    $other = $this->work.'/other.zip';
    $zip = new ZipArchive();
    $zip->open($other, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', json_encode([
        'name' => 'Other', 'registry_id' => 'acme/other', 'type' => 'theme', 'version' => '1.0.0', 'providers' => [],
    ]));
    $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Other\\' => '']]]));
    $zip->addFromString('theme.json', json_encode(['name' => 'testsky']));
    $zip->addFromString('resources/views/home.blade.php', 'other');
    $zip->close();

    expect(fn (): Addon => app(AddonRegistry::class)->install(new ZipSource($other)))
        ->toThrow(AddonInstallException::class, 'A theme named "testsky" already exists');
});

it('does not register a theme addon under a name already in the theme config', function (): void {
    installThemeAddon($this->work);
    Config::set('themes.themes', ['testsky' => ['views-path' => 'elsewhere']]);
    Config::set('themes.addon_themes', []);

    app(ThemeAddonRegistrar::class)->register();

    expect(config('themes.themes.testsky'))->toBe(['views-path' => 'elsewhere']);
});

it('drops a theme it registered earlier once the addon is disabled', function (): void {
    // config:cache boots the app first, so registered entries end up in the
    // cached config; the next boot must not keep a theme that is now disabled.
    installThemeAddon($this->work);
    Config::set('themes.themes', []);
    app(ThemeAddonRegistrar::class)->register();
    expect(config('themes.themes'))->toHaveKey('testsky');

    updateSetting('general.theme', 'seven');
    app(AddonRegistry::class)->disable('Testsky');
    app(ThemeAddonRegistrar::class)->register();

    expect(config('themes.themes'))->not->toHaveKey('testsky')
        ->and(config('themes.addon_themes'))->toBe([]);
});

it('records the new type when an update turns a module into a theme', function (): void {
    $moduleZip = $this->work.'/module.zip';
    $zip = new ZipArchive();
    $zip->open($moduleZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', json_encode([
        'name'        => 'Testsky',
        'registry_id' => 'acme/testsky',
        'version'     => '0.9.0',
        'providers'   => [],
    ]));
    $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Testsky\\' => '']]]));
    $zip->close();

    $registry = app(AddonRegistry::class);
    $registry->install(new ZipSource($moduleZip));
    updateSetting('general.theme', 'seven');

    $updated = $registry->update('Testsky', new ZipSource(themeAddonZip($this->work)));

    expect($updated->type)->toBe('theme')
        ->and(realpath($updated->getPath()))->toBe(realpath($this->work.'/themes/acme-testsky'))
        ->and(is_link($this->work.'/public-disk/themes/testsky'))->toBeTrue()
        // The module copy is gone, so deleting the theme cannot resurrect it.
        ->and(File::isDirectory($this->work.'/installed/acme-testsky'))->toBeFalse();
});

it('computes relative paths between directories', function (string $from, string $to, string $expected): void {
    expect(ThemeAssetLinker::relativePath($from, $to))->toBe($expected);
})->with([
    'sibling'  => ['/a/b/c', '/a/b/d', '../d'],
    'deeper'   => ['/a/b', '/a/b/c/d', 'c/d'],
    'up'       => ['/a/b/c/d', '/a/x', '../../../x'],
    'same'     => ['/a/b', '/a/b', '.'],
    'disjoint' => ['/a', '/x/y', '../x/y'],
]);

// ── Theme fallback ──────────────────────────────────────────────────────────

it('falls back to the configured default theme when the active one is gone', function (): void {
    updateSetting('general.theme', 'no-such-theme');
    Config::set('themes.default', 'seven');

    new SetActiveTheme()->setTheme(Request::create('/'));

    expect(Theme::get())->toBe('seven');
});

// ── storage:link ────────────────────────────────────────────────────────────

it('creates the storage link with a relative target', function (): void {
    Artisan::shouldReceive('call')->once()->with('storage:link', ['--relative' => true]);

    app(InstallerService::class)->linkStorage();
});

it('reports a storage link that cannot be made instead of throwing', function (): void {
    Artisan::shouldReceive('call')->once()->andThrow(new ErrorException('symlink() has been disabled'));

    expect(app(InstallerService::class)->linkStorage())->toBeFalse();
});

// ── Boot cache ──────────────────────────────────────────────────────────────

it('rebuilds the addon boot cache from the command line', function (): void {
    installThemeAddon($this->work);
    File::delete(config('addons.paths.boot_cache'));

    $this->artisan('addons:cache')->assertSuccessful();

    expect(app(BootCache::class)->enabled()->pluck('name')->all())->toContain('Testsky');
});
