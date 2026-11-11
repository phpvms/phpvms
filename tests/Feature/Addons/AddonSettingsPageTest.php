<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Filament\Pages\AddonSettings;
use App\Models\Permission;
use App\Models\User;
use App\Services\AddonSettingService;
use App\Services\PermissionRegistry;
use Filament\Facades\Filament;
use Filament\Panel;
use Modules\Sample\Providers\Filament\SampleAdminPanelProvider;
use Tests\Support\AddonSettings\AlphaSettings;
use Tests\Support\AddonSettings\WidgetSettings;

function sampleSettingsPanel(): Panel
{
    return new SampleAdminPanelProvider(app())->panel(Panel::make());
}

beforeEach(function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    // Priming only writes the boot cache; it does not touch the class loader.
    // Modules\Sample lives in a directory the root psr-4 map does not cover, so
    // its namespace is only resolvable once the addon loader has registered it —
    // and that must happen before the first Modules\Sample\* lookup, since
    // Composer's ClassLoader memoises misses in $missingClasses.
    app(AddonAutoLoader::class)->register(app());
});

it('registers the shared AddonSettings page on an addon panel', function (): void {
    $panel = sampleSettingsPanel();

    expect($panel->getPages())->toContain(AddonSettings::class);
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('resolves the owning addon from the panel id', function (): void {
    $service = app(AddonSettingService::class);

    expect($service->resolveAddon('sample'))->not->toBeNull()
        ->and($service->resolveAddon('sample')->namespace)->toBe('Modules\\Sample')
        ->and($service->resolveAddon('admin'))->toBeNull();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('grants page access within the addon panel but not on the core panel', function (): void {
    $this->actingAs(User::factory()->create());

    Filament::setCurrentPanel(sampleSettingsPanel());
    expect(AddonSettings::canAccess())->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    expect(AddonSettings::canAccess())->toBeFalse();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('scopes settings to the owning addon only', function (): void {
    registerAddonSettings('demo/widget', [WidgetSettings::class]);
    registerAddonSettings('other/addon', [AlphaSettings::class]);

    $scoped = collect(app(AddonSettingService::class)->all('demo/widget'));

    expect($scoped->pluck('class')->unique()->all())->toBe([WidgetSettings::class])
        ->and($scoped->pluck('name'))->not->toContain('api_token');
});

it('contributes the edit:addon-settings permission to the registry', function (): void {
    expect(app(PermissionRegistry::class)->all())->toContain('edit:addon-settings');
});

it('only allows editing for a user holding edit:addon-settings', function (): void {
    Permission::firstOrCreate(['name' => 'edit:addon-settings', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $viewer = User::factory()->create();
    $editor = User::factory()->create();
    $editor->givePermissionTo('edit:addon-settings');

    $this->actingAs($viewer);
    expect(AddonSettings::canEdit())->toBeFalse();

    $this->actingAs($editor);
    expect(AddonSettings::canEdit())->toBeTrue();
});
