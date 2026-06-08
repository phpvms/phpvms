<?php

declare(strict_types=1);

use App\Addons\Filament\FilamentPanelExtender;
use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a FilamentPanelExtender backed by a real (no-cache) AddonRuntime.
 *
 * These tests call discoveriesFor() directly, so no cache reading happens.
 */
function makeFilamentPanelExtender(): FilamentPanelExtender
{
    return new FilamentPanelExtender(new BootCache());
}

/**
 * Build an AddonBootCache from a minimal array, filling in required fields.
 *
 * @param array<string, mixed> $data
 */
function makeEntry(array $data): AddonBootCache
{
    return AddonBootCache::fromArray(array_merge([
        'name'          => 'Test',
        'alias'         => null,
        'type'          => 'module',
        'registry_id'   => null,
        'version'       => null,
        'namespace'     => '',
        'providers'     => [],
        'path'          => '/tmp/test',
        'autoload_path' => '/tmp/test',
        'layout'        => 'app',
        'description'   => null,
        'enabled'       => true,
        'filament'      => [],
    ], $data));
}

// ---------------------------------------------------------------------------
// discoveriesFor() — pure mapping logic
// ---------------------------------------------------------------------------

it('produces three admin entries for a row with all admin components', function (): void {
    $extender = makeFilamentPanelExtender();

    $entry = makeEntry([
        'namespace' => 'Modules\\Acme',
        'filament'  => [
            'admin' => [
                'Resources' => '/var/app/modules/Acme/Filament/Resources',
                'Pages'     => '/var/app/modules/Acme/Filament/Pages',
                'Widgets'   => '/var/app/modules/Acme/Filament/Widgets',
            ],
        ],
    ]);

    $result = $extender->discoveriesFor($entry);

    expect($result)->toHaveKey('admin')
        ->and($result)->not->toHaveKey('system')
        ->and($result['admin'])->toHaveCount(3);

    $byMethod = collect($result['admin'])->keyBy('method');

    expect($byMethod['discoverResources'])->toBe([
        'method' => 'discoverResources',
        'in'     => '/var/app/modules/Acme/Filament/Resources',
        'for'    => 'Modules\\Acme\\Filament\\Resources',
    ]);

    expect($byMethod['discoverPages'])->toBe([
        'method' => 'discoverPages',
        'in'     => '/var/app/modules/Acme/Filament/Pages',
        'for'    => 'Modules\\Acme\\Filament\\Pages',
    ]);

    expect($byMethod['discoverWidgets'])->toBe([
        'method' => 'discoverWidgets',
        'in'     => '/var/app/modules/Acme/Filament/Widgets',
        'for'    => 'Modules\\Acme\\Filament\\Widgets',
    ]);
});

it('produces one system entry for a row with only system Resources', function (): void {
    $extender = makeFilamentPanelExtender();

    $entry = makeEntry([
        'namespace' => 'Modules\\Acme',
        'filament'  => [
            'system' => [
                'Resources' => '/var/app/modules/Acme/Filament/System/Resources',
            ],
        ],
    ]);

    $result = $extender->discoveriesFor($entry);

    expect($result)->not->toHaveKey('admin')
        ->and($result)->toHaveKey('system')
        ->and($result['system'])->toHaveCount(1)
        ->and($result['system'][0])->toBe([
            'method' => 'discoverResources',
            'in'     => '/var/app/modules/Acme/Filament/System/Resources',
            'for'    => 'Modules\\Acme\\Filament\\System\\Resources',
        ]);
});

it('returns an empty array when filament key is empty', function (): void {
    $extender = makeFilamentPanelExtender();

    $entry = makeEntry([
        'namespace' => 'Modules\\Acme',
        'filament'  => [],
    ]);

    expect($extender->discoveriesFor($entry))->toBe([]);
});

it('strips trailing backslash from namespace before building for: string', function (): void {
    $extender = makeFilamentPanelExtender();

    $entry = makeEntry([
        'namespace' => 'Modules\\Acme\\',   // trailing backslash
        'filament'  => [
            'admin' => [
                'Resources' => '/abs/path/Resources',
            ],
        ],
    ]);

    $result = $extender->discoveriesFor($entry);

    expect($result['admin'][0]['for'])->toBe('Modules\\Acme\\Filament\\Resources');
});

it('returns an empty array when namespace is absent and filament data is present', function (): void {
    $extender = makeFilamentPanelExtender();

    // namespace key missing — fromArray defaults to '' which triggers the empty guard.
    $entry = makeEntry([
        'filament' => [
            'admin' => [
                'Resources' => '/abs/path/Resources',
            ],
        ],
    ]);

    expect($extender->discoveriesFor($entry))->toBe([]);
});

it('returns an empty array when namespace is empty string and filament data is present', function (): void {
    $extender = makeFilamentPanelExtender();

    $entry = makeEntry([
        'namespace' => '',
        'filament'  => [
            'admin' => [
                'Resources' => '/abs/path/Resources',
            ],
        ],
    ]);

    expect($extender->discoveriesFor($entry))->toBe([]);
});
