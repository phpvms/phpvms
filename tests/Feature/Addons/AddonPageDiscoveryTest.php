<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Modules\AddonManager\Filament\Pages\Addons as AddonsPage;

beforeEach(function (): void {
    // The Addons page now lives in the bundled module namespace; prime the boot
    // cache and register enabled addons so `Modules\AddonManager\...` autoloads.
    app(BootCache::class)->delete();
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonAutoLoader::class)->register(app());

    // No network: the page assembles its list by merging an empty catalog with
    // the installed rows, so an on-disk addon still surfaces on its own.
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    // Bundled module rows are seeded by the addons migration; clear them so each
    // test starts from a known-empty DB.
    Addon::query()->delete();

    $this->modules = sys_get_temp_dir().'/addon-page-'.uniqid();
    File::ensureDirectoryExists($this->modules);
    Config::set('addons.paths.base', $this->modules);
});

afterEach(function (): void {
    File::deleteDirectory($this->modules);
    app(BootCache::class)->delete();
});

/**
 * Drop a valid addon (module.json + composer.json) directly on disk, mirroring
 * an FTP upload or an addon whose files remain after a panel delete.
 */
function placeAddonOnDisk(string $base, string $name): void
{
    $dir = $base.'/'.strtolower($name);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode(['name' => $name, 'registry_id' => 'acme/'.strtolower($name), 'providers' => []]));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => '']]]));
}

/**
 * A page "load": mount() runs the on-disk discovery, then allEntries() reads it.
 */
function loadAddonsPageEntries(): Collection
{
    $page = app(AddonsPage::class);
    $page->mount();

    return $page->allEntries();
}

it('surfaces an on-disk addon that has no DB row as an installable (disabled) entry', function (): void {
    placeAddonOnDisk($this->modules, 'VMSAcars');

    expect(Addon::query()->count())->toBe(0);

    $entries = loadAddonsPageEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()['name'])->toBe('VMSAcars')
        ->and($entries->first()['enabled'])->toBeFalse()
        ->and($entries->first()['installed'])->toBeTrue();

    // Discovery persisted it as a disabled row so it reads as installable.
    expect(Addon::query()->where('name', 'VMSAcars')->where('enabled', false)->exists())->toBeTrue();
});

it('re-detects an addon whose DB row was deleted while its files remain', function (): void {
    placeAddonOnDisk($this->modules, 'VMSAcars');

    // First load discovers + persists the disabled row.
    loadAddonsPageEntries();
    expect(Addon::query()->where('name', 'VMSAcars')->exists())->toBeTrue();

    // Simulate a panel delete: the row is hard-deleted, files stay on disk.
    Addon::query()->where('name', 'VMSAcars')->delete();
    expect(Addon::query()->count())->toBe(0);

    // Next page load must re-detect it rather than showing nothing.
    $entries = loadAddonsPageEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()['name'])->toBe('VMSAcars');
});
