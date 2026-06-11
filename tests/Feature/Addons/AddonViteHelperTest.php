<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Manifest lives under public/ext/{lower-name}/build, where the addon's
    // public/ dir is symlinked by AddonAssetLinker.
    $this->buildDir = public_path('ext/samplevite/build');
    File::ensureDirectoryExists($this->buildDir.'/assets');
    File::put($this->buildDir.'/manifest.json', json_encode([
        'resources/js/app.js' => [
            'file'    => 'assets/app-abc123.js',
            'src'     => 'resources/js/app.js',
            'isEntry' => true,
        ],
    ]));
});

afterEach(function (): void {
    File::deleteDirectory(public_path('ext/samplevite'));
});

it('addon_vite() renders tags from the addon manifest at the lower-cased path', function (): void {
    $html = addon_vite('SampleVite', 'resources/js/app.js')->toHtml();

    expect($html)
        ->toContain('/ext/samplevite/build/assets/app-abc123.js')
        ->toContain('<script');
});

it('addon_vite() accepts multiple entry points', function (): void {
    $html = addon_vite('SampleVite', ['resources/js/app.js'])->toHtml();

    expect($html)->toContain('/ext/samplevite/build/assets/app-abc123.js');
});
