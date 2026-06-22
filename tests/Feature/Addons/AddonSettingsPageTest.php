<?php

declare(strict_types=1);

use App\Filament\Pages\AddonSettings;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Models\Permission;
use App\Models\User;
use App\Services\AddonSettingService;
use App\Services\AddonSettingSyncService;
use App\Services\PermissionRegistry;
use Filament\Facades\Filament;
use Filament\Panel;
use Modules\Sample\Providers\Filament\SampleAdminPanelProvider;

function sampleSettingsPanel(): Panel
{
    return new SampleAdminPanelProvider(app())->panel(Panel::make());
}

beforeEach(function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonSettingSyncService::class)->sync();
});

it('registers the shared AddonSettings page on an addon panel', function (): void {
    $panel = sampleSettingsPanel();

    expect($panel->getPages())->toContain(AddonSettings::class);
});

it('resolves the owning addon from the panel id', function (): void {
    $service = app(AddonSettingService::class);

    expect($service->resolveAddon('sample'))->not->toBeNull()
        ->and($service->resolveAddon('sample')->namespace)->toBe('Modules\\Sample')
        ->and($service->resolveAddon('admin'))->toBeNull();
});

it('grants page access within the addon panel but not on the core panel', function (): void {
    Filament::setCurrentPanel(sampleSettingsPanel());
    expect(AddonSettings::canAccess())->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    expect(AddonSettings::canAccess())->toBeFalse();
});

it('scopes settings to the owning addon only', function (): void {
    $sample = Addon::where('namespace', 'Modules\\Sample')->firstOrFail();

    // A second addon with its own settings must not appear in Sample's scope.
    $other = Addon::factory()->create(['registry_id' => 'other/addon']);
    AddonSetting::factory()->create(['addon_id' => $other->id, 'key' => 'secret']);

    $scoped = app(AddonSettingService::class)->all($sample->id);

    expect($scoped)->toHaveCount(5)
        ->and($scoped->pluck('addon_id')->unique()->all())->toBe([$sample->id]);
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
