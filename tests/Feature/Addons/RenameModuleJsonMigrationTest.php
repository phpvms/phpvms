<?php

declare(strict_types=1);

use App\Features\Addons\Support\ManifestParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/rename-manifest-'.uniqid();
    $this->base = $this->work.'/root';
    File::ensureDirectoryExists($this->base);
    Config::set('addons.paths.root', $this->base);

    $this->migration = require database_path('migrations_data/2026_11_10_000000_rename_module_json_to_addon_json.php');
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

it('renames module.json to addon.json in the addon root', function (): void {
    foreach ([$this->base.'/legacy', $this->base.'/acme-legacy', $this->base.'/acme-theme'] as $dir) {
        File::ensureDirectoryExists($dir);
        File::put($dir.'/module.json', '{"name":"Legacy"}');
    }

    $this->migration->up();
    // Safe to run again.
    $this->migration->up();

    foreach ([$this->base.'/legacy', $this->base.'/acme-legacy', $this->base.'/acme-theme'] as $dir) {
        expect(File::exists($dir.'/module.json'))->toBeFalse()
            ->and(File::get($dir.'/addon.json'))->toBe('{"name":"Legacy"}');
    }
});

it('leaves a directory that has both files alone', function (): void {
    $dir = $this->base.'/both';
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', '{"old":true}');
    File::put($dir.'/addon.json', '{"new":true}');

    $this->migration->up();

    expect(File::get($dir.'/module.json'))->toBe('{"old":true}')
        ->and(File::get($dir.'/addon.json'))->toBe('{"new":true}');
});

it('reads a module.json addon before and after the rename', function (): void {
    $dir = $this->base.'/legacy';
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode(['registry_id' => 'acme/legacy']));
    File::put($dir.'/composer.json', '{}');

    expect(new ManifestParser()->parse($dir)?->registryId)->toBe('acme/legacy');

    $this->migration->up();

    expect(new ManifestParser()->parse($dir)?->registryId)->toBe('acme/legacy');
});

it('leaves the retired awards and widgets manifests as module.json', function (): void {
    foreach (['Awards' => 'phpvms/awards', 'phpvms-widgets' => 'phpvms/widgets'] as $name => $registryId) {
        File::ensureDirectoryExists($this->base.'/'.$name);
        File::put($this->base.'/'.$name.'/module.json', json_encode(['registry_id' => $registryId]));
    }

    $this->migration->up();

    expect(File::exists($this->base.'/Awards/addon.json'))->toBeFalse()
        ->and(File::exists($this->base.'/phpvms-widgets/addon.json'))->toBeFalse();
});
