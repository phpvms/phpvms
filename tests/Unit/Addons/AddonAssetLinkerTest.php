<?php

declare(strict_types=1);

use App\Addons\Support\AddonAssetLinker;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->base = sys_get_temp_dir().'/addon-assets-'.uniqid();
    $this->addonDir = $this->base.'/modules/Demo';
    $this->publicExt = $this->base.'/public/ext';
    File::ensureDirectoryExists($this->addonDir.'/public');
    File::put($this->addonDir.'/public/app.js', 'console.log(1)');
    File::ensureDirectoryExists($this->base.'/public');

    $this->linker = new AddonAssetLinker($this->publicExt);
});

afterEach(function (): void {
    File::deleteDirectory($this->base);
});

it('link() symlinks {addon}/public to public/ext/{name}', function (): void {
    $this->linker->link('Demo', $this->addonDir);

    $link = $this->publicExt.'/Demo';
    expect(is_link($link))->toBeTrue()
        ->and(realpath($link))->toBe(realpath($this->addonDir.'/public'));
});

it('link() is a no-op when the addon has no public dir', function (): void {
    File::deleteDirectory($this->addonDir.'/public');

    $this->linker->link('Demo', $this->addonDir);

    expect(file_exists($this->publicExt.'/Demo'))->toBeFalse();
});

it('link() is idempotent', function (): void {
    $this->linker->link('Demo', $this->addonDir);
    $this->linker->link('Demo', $this->addonDir);

    expect(is_link($this->publicExt.'/Demo'))->toBeTrue();
});

it('unlink() removes the symlink', function (): void {
    $this->linker->link('Demo', $this->addonDir);
    $this->linker->unlink('Demo');

    expect(file_exists($this->publicExt.'/Demo'))->toBeFalse();
});
