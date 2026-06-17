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

it('resolves composer autoload.files into absolute paths under the addon dir', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);
    mkdir($tmpDir.'/src', 0755, true);
    file_put_contents($tmpDir.'/helpers.php', "<?php\n");
    file_put_contents($tmpDir.'/src/fns.php', "<?php\n");
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'      => 'FilesWidget',
        'providers' => [],
    ]));
    file_put_contents($tmpDir.'/composer.json', json_encode([
        'autoload' => [
            'files' => ['helpers.php', '/src/fns.php', '', 42],
        ],
    ]));

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result->files)->toBe([
            $tmpDir.'/helpers.php',
            $tmpDir.'/src/fns.php',
        ]);
    } finally {
        unlink($tmpDir.'/composer.json');
        unlink($tmpDir.'/module.json');
        unlink($tmpDir.'/src/fns.php');
        unlink($tmpDir.'/helpers.php');
        rmdir($tmpDir.'/src');
        rmdir($tmpDir);
    }
});

it('returns an empty files list when autoload.files is absent or malformed', function (): void {
    $tmpDir = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'      => 'NoFiles',
        'providers' => [],
    ]));
    file_put_contents($tmpDir.'/composer.json', json_encode([
        'autoload' => [
            'psr-4' => ['Modules\\NoFiles\\' => '.'],
            'files' => 'not-an-array',
        ],
    ]));

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($tmpDir);

        expect($result->files)->toBe([]);
    } finally {
        unlink($tmpDir.'/composer.json');
        unlink($tmpDir.'/module.json');
        rmdir($tmpDir);
    }
});

it('rejects autoload.files entries that escape the addon directory', function (): void {
    $base = sys_get_temp_dir().'/manifest_parser_test_'.uniqid();
    $addonDir = $base.'/addon';
    mkdir($addonDir, 0755, true);

    // A real file that lives OUTSIDE the addon directory — the path traversal
    // must not be able to point the file loader at it.
    file_put_contents($base.'/secret.php', "<?php\n");

    file_put_contents($addonDir.'/module.json', json_encode([
        'name'      => 'Escaper',
        'providers' => [],
    ]));
    file_put_contents($addonDir.'/composer.json', json_encode([
        'autoload' => [
            'files' => ['../secret.php'],
        ],
    ]));

    try {
        $parser = new ManifestParser();
        $result = $parser->parse($addonDir);

        expect($result->files)->toBe([]);
    } finally {
        unlink($addonDir.'/composer.json');
        unlink($addonDir.'/module.json');
        unlink($base.'/secret.php');
        rmdir($addonDir);
        rmdir($base);
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
