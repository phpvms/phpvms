<?php

declare(strict_types=1);

use App\Models\Addon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function moveAddonsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_09_000001_move_addons_to_storage_app_addons.php');
}

function oldRootAddon(string $dir, string $registryId): void
{
    File::ensureDirectoryExists($dir);
    File::put($dir.'/addon.json', json_encode(addonManifest([
        'name'        => basename($dir),
        'registry_id' => $registryId,
        'providers'   => [],
    ])));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.Str::studly(basename($dir)).'\\' => 'app/']]]));
}

beforeEach(function (): void {
    // storage_path() is where the migration finds the old roots, so it is
    // pointed at a temp directory for the test and put back afterwards.
    $this->originalStorage = storage_path();
    $this->storage = sys_get_temp_dir().'/phpvms-storage-'.uniqid('', true);
    app()->useStoragePath($this->storage);

    $this->root = (string) config('addons.paths.root');
    config(['addons.bundled' => []]);
});

afterEach(function (): void {
    app()->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->storage);
});

it('moves runtime addons and themes from the old roots and re-points their rows', function (): void {
    oldRootAddon($this->storage.'/addons/acme-weather', 'acme/weather');
    oldRootAddon($this->storage.'/addon-themes/acme-sky', 'acme/sky');
    $row = Addon::factory()->create([
        'name'        => 'acme-weather',
        'registry_id' => 'acme/weather',
        'path'        => $this->storage.'/addons/acme-weather',
    ]);

    moveAddonsMigration()->up();

    expect(File::exists($this->root.'/acme-weather/addon.json'))->toBeTrue()
        ->and(File::exists($this->root.'/acme-sky/addon.json'))->toBeTrue()
        // Emptied old roots are removed.
        ->and(is_dir($this->storage.'/addons'))->toBeFalse()
        ->and(is_dir($this->storage.'/addon-themes'))->toBeFalse()
        ->and($row->refresh()->path)->toBe(realpath($this->root.'/acme-weather'));
});

it('leaves an addon in place when its name is already taken in the root', function (): void {
    oldRootAddon($this->storage.'/addons/acme-weather', 'acme/weather');
    oldRootAddon($this->root.'/acme-weather', 'acme/weather-new');

    moveAddonsMigration()->up();

    expect(File::exists($this->storage.'/addons/acme-weather/addon.json'))->toBeTrue()
        ->and(json_decode(File::get($this->root.'/acme-weather/addon.json'), true)['registry_id'])->toBe('acme/weather-new');
});
