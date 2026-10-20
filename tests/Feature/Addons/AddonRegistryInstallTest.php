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

    // install() places files in the writable root, never in the image root,
    // so that is where this suite asserts they land.
    $this->installed = $this->work.'/installed';
    Config::set('addons.paths.installed', $this->installed);

    $this->zip = $this->work.'/demo.zip';
    $zip = new ZipArchive();
    $zip->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', json_encode(['name' => 'Demo', 'registry_id' => 'acme/demo', 'providers' => []]));
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
        ->and(File::isDirectory($this->installed.'/acme-demo'))->toBeTrue()
        ->and(Addon::query()->where('name', 'Demo')->exists())->toBeTrue();
});

it('install() rejects a duplicate addon', function (): void {
    app(AddonRegistry::class)->install(new ZipSource($this->zip));

    app(AddonRegistry::class)->install(new ZipSource($this->zip));
})->throws(AddonInstallException::class);

it('install() cleans up only its own staging directory', function (): void {
    $staging = $this->work.'/staging';
    Config::set('addons.paths.staging', $staging);

    // Stands in for a concurrent install/update that has extracted its archive
    // but not yet placed it. install() must not delete this on its way out.
    $concurrent = $staging.'/op_concurrent';
    File::ensureDirectoryExists($concurrent);

    app(AddonRegistry::class)->install(new ZipSource($this->zip));

    // The sibling survived, and install() still removed the directory it owned:
    // exactly one is left, and it is the other operation's.
    expect(File::isDirectory($concurrent))->toBeTrue()
        ->and(File::directories($staging))->toHaveCount(1);
});

it('install() leaves no staging directory behind when the fetch fails', function (): void {
    $staging = $this->work.'/staging';
    Config::set('addons.paths.staging', $staging);

    $notAZip = $this->work.'/broken.zip';
    File::put($notAZip, 'not a zip');

    // Three attempts, because the failure mode is accumulation: one directory
    // per failed attempt, left for nothing to sweep up.
    foreach (range(1, 3) as $ignored) {
        try {
            app(AddonRegistry::class)->install(new ZipSource($notAZip));
        } catch (AddonInstallException) {
            // Expected. What matters is what is left on disk afterwards.
        }
    }

    expect(File::isDirectory($staging) ? File::directories($staging) : [])->toBe([]);
});
