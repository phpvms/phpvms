<?php

use App\Addons\Models\AddonManifest;
use App\Addons\Support\ManifestParser;
use Modules\Awards\Providers\AwardServiceProvider;

it('parses Awards module (legacy nwidart, no phpVMS keys, no composer.json)', function (): void {
    $parser = new ManifestParser();
    $result = $parser->parse(base_path('modules/Awards'));

    expect($result)->toBeInstanceOf(AddonManifest::class)
        ->and($result->name)->toBe('Awards')
        ->and($result->type)->toBe('module')
        ->and($result->registryId)->toBeNull()
        ->and($result->compat)->toBeNull()
        ->and($result->version)->toBeNull()
        ->and($result->namespace)->toBe('Modules\\Awards')
        ->and($result->providers)->toContain(AwardServiceProvider::class);
});

it('parses Sample module (composer.json psr-4 dot key, no version)', function (): void {
    $parser = new ManifestParser();
    $result = $parser->parse(base_path('modules/Sample'));

    expect($result)->toBeInstanceOf(AddonManifest::class)
        ->and($result->namespace)->toBe('Modules\\Sample')
        ->and($result->version)->toBeNull();
});

it('parses VMSAcars module (composer.json psr-4 empty string key, version from composer)', function (): void {
    $parser = new ManifestParser();
    $result = $parser->parse(base_path('modules/VMSAcars'));

    expect($result)->toBeInstanceOf(AddonManifest::class)
        ->and($result->namespace)->toBe('Modules\\VMSAcars')
        ->and($result->version)->toBe('1.1.0');
});

it('parses phpVMS keys: type, compat, registry_id, version', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'        => 'TestWidget',
        'type'        => 'theme',
        'compat'      => '^7.0',
        'registry_id' => 'acme/widget',
        'version'     => '2.3.0',
        'providers'   => [],
    ]));

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result)->toBeInstanceOf(AddonManifest::class)
            ->and($result->type)->toBe('theme')
            ->and($result->compat)->toBe('^7.0')
            ->and($result->registryId)->toBe('acme/widget')
            ->and($result->version)->toBe('2.3.0');
    } finally {
        unlink($tmpDir.'/module.json');
        rmdir($tmpDir);
    }
});

it('returns null when module.json contains invalid JSON (D-15)', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', '{not valid json}');

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result)->toBeNull();
    } finally {
        unlink($tmpDir.'/module.json');
        rmdir($tmpDir);
    }
});

it('returns null when no module.json exists', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result)->toBeNull();
    } finally {
        rmdir($tmpDir);
    }
});

it('normalises blank registry_id to null (D-03)', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'        => 'BlankId',
        'registry_id' => '   ',
        'providers'   => [],
    ]));

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result)->toBeInstanceOf(AddonManifest::class)
            ->and($result->registryId)->toBeNull();
    } finally {
        unlink($tmpDir.'/module.json');
        rmdir($tmpDir);
    }
});
