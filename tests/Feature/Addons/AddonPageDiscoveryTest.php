<?php

declare(strict_types=1);

use App\Filament\Pages\Addons as AddonsPage;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Bundled module rows are seeded by the addons migration; clear them so each
    // test starts from a known-empty DB.
    Addon::query()->delete();

    $this->modules = sys_get_temp_dir().'/addon-page-'.uniqid();
    File::ensureDirectoryExists($this->modules);
    Config::set('addons.paths.base', $this->modules);
});

afterEach(function (): void {
    File::deleteDirectory($this->modules);
});

/**
 * Drop a valid addon (module.json + composer.json) directly on disk, mirroring
 * an FTP upload or an addon whose files remain after a panel delete.
 */
function placeAddonOnDisk(string $base, string $name): void
{
    $dir = $base.'/'.strtolower($name);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode(['name' => $name, 'providers' => []]));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => '']]]));
}

it('surfaces an on-disk addon that has no DB row as an installable (disabled) entry', function (): void {
    placeAddonOnDisk($this->modules, 'VMSAcars');

    expect(Addon::query()->count())->toBe(0);

    $records = app(AddonsPage::class)->getModulesRecords();

    expect($records)->toHaveCount(1)
        ->and($records->first()['name'])->toBe('VMSAcars')
        ->and($records->first()['enabled'])->toBeFalse();

    // Discovery persisted it as a disabled row so it reads as installable.
    expect(Addon::query()->where('name', 'VMSAcars')->where('enabled', false)->exists())->toBeTrue();
});

it('re-detects an addon whose DB row was deleted while its files remain', function (): void {
    placeAddonOnDisk($this->modules, 'VMSAcars');

    // First load discovers + persists the disabled row.
    app(AddonsPage::class)->getModulesRecords();
    expect(Addon::query()->where('name', 'VMSAcars')->exists())->toBeTrue();

    // Simulate a panel delete: the row is hard-deleted, files stay on disk.
    Addon::query()->where('name', 'VMSAcars')->delete();
    expect(Addon::query()->count())->toBe(0);

    // Next page load must re-detect it rather than showing nothing.
    $records = app(AddonsPage::class)->getModulesRecords();

    expect($records)->toHaveCount(1)
        ->and($records->first()['name'])->toBe('VMSAcars');
});
