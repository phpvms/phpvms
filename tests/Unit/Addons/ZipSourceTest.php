<?php

use App\Addons\Sources\ZipSource;
use App\Exceptions\AddonInstallException;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/zipsrc-'.uniqid();
    $this->staging = $this->work.'/staging';
    File::ensureDirectoryExists($this->staging);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

function makeZip(string $path, array $files): void
{
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();
}

it('extracts a zip into the staging dir and returns the addon root', function (): void {
    $zipPath = $this->work.'/demo.zip';
    makeZip($zipPath, [
        'module.json'   => '{"name":"Demo"}',
        'composer.json' => '{}',
    ]);

    $root = new ZipSource($zipPath)->fetch($this->staging);

    expect(File::exists($root.'/module.json'))->toBeTrue();
});

it('unwraps a single top-level directory', function (): void {
    $zipPath = $this->work.'/demo.zip';
    makeZip($zipPath, [
        'Demo/module.json'   => '{"name":"Demo"}',
        'Demo/composer.json' => '{}',
    ]);

    $root = new ZipSource($zipPath)->fetch($this->staging);

    expect(File::exists($root.'/module.json'))->toBeTrue();
});

it('rejects a zip-slip entry', function (): void {
    $zipPath = $this->work.'/evil.zip';
    makeZip($zipPath, ['../../escape.txt' => 'pwned']);

    new ZipSource($zipPath)->fetch($this->staging);
})->throws(AddonInstallException::class);

it('rejects a missing zip file', function (): void {
    new ZipSource($this->work.'/nope.zip')->fetch($this->staging);
})->throws(AddonInstallException::class);
