<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\BootCache;
use App\Addons\Filament\FilamentPanelExtender;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a FilamentPanelExtender with a stubbed registry that returns $rows.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function makeFilamentPanelExtender(array $rows = []): FilamentPanelExtender
{
    $cache = new class($rows) extends BootCache
    {
        /** @param array<int, array<string, mixed>> $rows */
        public function __construct(private readonly array $rows) {}

        /** @return array<int, array<string, mixed>> */
        public function read(): array
        {
            return $this->rows;
        }
    };

    return new FilamentPanelExtender(new AddonRegistry($cache));
}

// ---------------------------------------------------------------------------
// discoveriesFor() — pure mapping logic
// ---------------------------------------------------------------------------

it('produces three admin entries for a row with all admin components', function (): void {
    $extender = makeFilamentPanelExtender();

    $row = [
        'namespace' => 'Modules\\Acme',
        'filament'  => [
            'admin' => [
                'Resources' => '/var/app/modules/Acme/Filament/Resources',
                'Pages'     => '/var/app/modules/Acme/Filament/Pages',
                'Widgets'   => '/var/app/modules/Acme/Filament/Widgets',
            ],
        ],
    ];

    $result = $extender->discoveriesFor($row);

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

    $row = [
        'namespace' => 'Modules\\Acme',
        'filament'  => [
            'system' => [
                'Resources' => '/var/app/modules/Acme/Filament/System/Resources',
            ],
        ],
    ];

    $result = $extender->discoveriesFor($row);

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

    $row = [
        'namespace' => 'Modules\\Acme',
        'filament'  => [],
    ];

    expect($extender->discoveriesFor($row))->toBe([]);
});

it('strips trailing backslash from namespace before building for: string', function (): void {
    $extender = makeFilamentPanelExtender();

    $row = [
        'namespace' => 'Modules\\Acme\\',   // trailing backslash
        'filament'  => [
            'admin' => [
                'Resources' => '/abs/path/Resources',
            ],
        ],
    ];

    $result = $extender->discoveriesFor($row);

    expect($result['admin'][0]['for'])->toBe('Modules\\Acme\\Filament\\Resources');
});

it('returns an empty array when namespace is absent and filament data is present', function (): void {
    $extender = makeFilamentPanelExtender();

    // Row has filament data for admin Resources but no namespace key.
    $row = [
        'filament' => [
            'admin' => [
                'Resources' => '/abs/path/Resources',
            ],
        ],
    ];

    expect($extender->discoveriesFor($row))->toBe([]);
});

it('returns an empty array when namespace is empty string and filament data is present', function (): void {
    $extender = makeFilamentPanelExtender();

    $row = [
        'namespace' => '',
        'filament'  => [
            'admin' => [
                'Resources' => '/abs/path/Resources',
            ],
        ],
    ];

    expect($extender->discoveriesFor($row))->toBe([]);
});
