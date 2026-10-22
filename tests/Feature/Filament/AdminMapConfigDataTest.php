<?php

declare(strict_types=1);

use App\Models\MapLayer;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;

/**
 * design.md D7 "one config DTO, two transports" — the admin side. bootUsing()
 * (not ServiceProvider::boot()) is what makes this per-request rather than
 * frozen at app boot; see AdminPanelProvider::panel()'s comment. Calling
 * Filament::bootCurrentPanel() directly (rather than a full HTTP request)
 * exercises that hook without needing routing/middleware in the test harness.
 */
it('delivers the current map config through window.filamentData.maps', function (): void {
    Filament::setCurrentPanel('admin');

    MapLayer::factory()->basemap()->create(['url_template' => 'https://example.com/light.json']);
    MapLayer::factory()->create(['name' => 'Test Overlay', 'enabled' => true, 'order' => 0]);

    Filament::bootCurrentPanel();

    $maps = FilamentAsset::getScriptData()['maps'] ?? null;

    expect($maps)->not->toBeNull()
        ->and($maps['basemapLight'])->toBe('https://example.com/light.json')
        ->and($maps['layers'])->toHaveCount(1)
        ->and($maps['layers'][0]['name'])->toBe('Test Overlay');
});

it('reads the settings row current at boot time, not a config-file default', function (): void {
    Filament::setCurrentPanel('admin');

    MapLayer::factory()->basemap()->create(['url_template_dark' => 'https://example.com/dark.json']);

    Filament::bootCurrentPanel();

    expect(FilamentAsset::getScriptData()['maps']['basemapDark'] ?? null)
        ->toBe('https://example.com/dark.json');
});
