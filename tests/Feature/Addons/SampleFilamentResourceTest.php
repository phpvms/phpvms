<?php

declare(strict_types=1);

use App\Addons\BootCache;
use App\Addons\Filament\FilamentPanelExtender;
use Filament\PanelRegistry;
use Modules\Sample\Filament\Resources\SampleResource;

beforeEach(function (): void {
    app(BootCache::class)->delete();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

it('phpvms:addons-prime records the Sample Filament Resources path in the boot cache', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    $cached = app(BootCache::class)->read();
    $sample = collect($cached)->first(fn (array $row): bool => ($row['name'] ?? null) === 'Sample');

    expect($sample)->not->toBeNull('Sample row must exist in cache after prime')
        ->and($sample['filament'])->toBeArray()
        ->and($sample['filament']['admin'] ?? null)->toBeArray()
        ->and($sample['filament']['admin']['Resources'] ?? null)->not->toBeNull(
            'Sample admin Resources path must be probed and recorded'
        )
        ->and($sample['filament']['admin']['Resources'])->toEndWith('modules/Sample/Filament/Resources');
});

it('FilamentPanelExtender::apply() registers SampleResource on the admin panel (criterion #2)', function (): void {
    // 1. Prime — probes modules/Sample/Filament/Resources (now exists) and writes cache.
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    // 2. Apply the panel extender — reads primed cache and calls discoverResources on admin panel.
    app(FilamentPanelExtender::class)->apply();

    // 3. Assert the admin panel now registers SampleResource.
    $adminPanel = app(PanelRegistry::class)->panels['admin'];

    expect($adminPanel->getResources())->toContain(SampleResource::class);
});
