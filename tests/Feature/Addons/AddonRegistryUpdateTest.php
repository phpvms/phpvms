<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\Sources\ZipSource;
use App\Exceptions\AddonInstallException;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/update-'.uniqid();
    $this->modules = $this->work.'/modules';
    File::ensureDirectoryExists($this->modules);
    Config::set('addons.paths.base', $this->modules);

    $this->makeZip = function (string $version): string {
        $path = $this->work.'/demo-'.$version.'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('module.json', json_encode(['name' => 'Demo', 'version' => $version, 'providers' => []]));
        $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Demo\\' => '']]]));
        $zip->close();

        return $path;
    };
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

it('update() replaces files and bumps the version, preserving enabled', function (): void {
    $registry = app(AddonRegistry::class);
    $registry->install(new ZipSource(($this->makeZip)('1.0.0')));
    $registry->disable('Demo');

    $registry->update('Demo', new ZipSource(($this->makeZip)('2.0.0')));

    $row = Addon::query()->where('name', 'Demo')->first();
    expect($row->version)->toBe('2.0.0')
        ->and($row->enabled)->toBeFalse();
});

it('update() throws for an addon that is not installed', function (): void {
    app(AddonRegistry::class)->update('Ghost', new ZipSource(($this->makeZip)('1.0.0')));
})->throws(AddonInstallException::class);
