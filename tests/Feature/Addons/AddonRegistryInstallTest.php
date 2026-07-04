<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\Sources\ZipSource;
use App\Exceptions\AddonInstallException;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/install-'.uniqid();
    $this->modules = $this->work.'/modules';
    File::ensureDirectoryExists($this->modules);
    Config::set('addons.paths.base', $this->modules);

    $this->zip = $this->work.'/demo.zip';
    $zip = new ZipArchive();
    $zip->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', json_encode(['name' => 'Demo', 'providers' => []]));
    $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Demo\\' => '']]]));
    $zip->close();
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

it('install() places the addon and registers a DB row', function (): void {
    $addon = app(AddonRegistry::class)->install(new ZipSource($this->zip));

    expect($addon)->toBeInstanceOf(Addon::class)
        ->and($addon->getName())->toBe('Demo')
        ->and(File::isDirectory($this->modules.'/demo'))->toBeTrue()
        ->and(Addon::query()->where('name', 'Demo')->exists())->toBeTrue();
});

it('install() rejects a duplicate addon', function (): void {
    app(AddonRegistry::class)->install(new ZipSource($this->zip));

    app(AddonRegistry::class)->install(new ZipSource($this->zip));
})->throws(AddonInstallException::class);
