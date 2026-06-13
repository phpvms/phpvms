<?php

use App\Addons\Models\AddonManifest;
use App\Addons\Support\ManifestParser;

it('normalises database.tables: trims, drops blanks/non-strings, de-dupes', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'      => 'TablesWidget',
        'providers' => [],
        'database'  => [
            'tables' => ['  things  ', '', 'things', 42, 'more_things'],
        ],
    ]));

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result->tables)->toBe(['things', 'more_things']);
    } finally {
        unlink($tmpDir.'/module.json');
        rmdir($tmpDir);
    }
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
