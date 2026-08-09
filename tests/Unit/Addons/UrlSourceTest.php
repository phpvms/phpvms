<?php

declare(strict_types=1);

use App\Addons\Sources\UrlSource;
use App\Exceptions\AddonInstallException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/urlsrc-'.uniqid();
    $this->staging = $this->work.'/staging';
    File::ensureDirectoryExists($this->staging);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

it('downloads a zip and extracts it', function (): void {
    $zipPath = $this->work.'/demo.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', '{"name":"Demo"}');
    $zip->close();

    Http::fake([
        'example.test/*' => Http::response(File::get($zipPath), 200),
    ]);

    $root = new UrlSource('https://example.test/demo.zip')->fetch($this->staging);

    expect(File::exists($root.'/module.json'))->toBeTrue();
});

it('throws when the download fails', function (): void {
    Http::fake(['example.test/*' => Http::response('', 404)]);

    new UrlSource('https://example.test/missing.zip')->fetch($this->staging);
})->throws(AddonInstallException::class);

it('extracts when the sha256 matches', function (): void {
    $zipPath = $this->work.'/demo.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', '{"name":"Demo"}');
    $zip->close();

    $bytes = File::get($zipPath);
    Http::fake(['example.test/*' => Http::response($bytes, 200)]);

    $root = new UrlSource('https://example.test/demo.zip', hash('sha256', $bytes))->fetch($this->staging);

    expect(File::exists($root.'/module.json'))->toBeTrue();
});

it('rejects a checksum mismatch and deletes the staged file', function (): void {
    $zipPath = $this->work.'/demo.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', '{"name":"Demo"}');
    $zip->close();

    Http::fake(['example.test/*' => Http::response(File::get($zipPath), 200)]);

    try {
        new UrlSource('https://example.test/demo.zip', str_repeat('0', 64))->fetch($this->staging);
        $this->fail('Expected a checksum mismatch to throw.');
    } catch (AddonInstallException $addonInstallException) {
        expect($addonInstallException->getMessage())->toContain('Checksum mismatch');
    }

    // No staged download left behind.
    expect(collect(File::files($this->staging))->filter(fn ($f): bool => str_starts_with($f->getFilename(), 'dl_')))->toBeEmpty();
});
