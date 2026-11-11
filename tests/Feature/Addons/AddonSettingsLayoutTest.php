<?php

declare(strict_types=1);

use App\Contracts\Addons\AddonSettings as AddonSettingsClass;
use App\Filament\Pages\AddonSettings;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;
use Tests\Support\AddonSettings\WidgetNetworkSettings;
use Tests\Support\AddonSettings\WidgetSettings;

/*
 * The addon settings page shares the core Settings layout: a searchable section
 * nav beside one section per settings class, each section saving on its own.
 */

final class DemoUpperFuelSettings extends AddonSettingsClass
{
    public string $upper = 'upper';

    public static function section(): string
    {
        return 'Fuel';
    }
}

final class DemoLowerFuelSettings extends AddonSettingsClass
{
    public string $lower = 'lower';

    public static function section(): string
    {
        return 'fuel';
    }
}

final class DemoSeasonSettings extends AddonSettingsClass
{
    public string $season = 'winter';

    public static function section(): string
    {
        return '2024';
    }
}

/**
 * @param list<class-string<AddonSettingsClass>> $classes
 */
function demoAddon(array $classes = [WidgetSettings::class, WidgetNetworkSettings::class]): void
{
    // The panel id resolves to the addon by name (or its manifest alias).
    registerAddonSettings('phpvms/demo', $classes, ['name' => 'demo']);
}

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'edit:addon-settings', 'guard_name' => 'web']);
    $editor = User::factory()->create();
    $editor->givePermissionTo('edit:addon-settings');
    $this->actingAs($editor);
    // Registered, not only set: Livewire's update requests restore the panel by id.
    $panel = Panel::make()->id('demo')->path('admin/demo');
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
});

it('lays the settings out like the core settings page', function (): void {
    demoAddon();

    Livewire::test(AddonSettings::class)
        ->assertSuccessful()
        ->assertSeeHtml('settings-page')
        ->assertSeeHtml('settings-nav')
        ->assertSeeHtml('id="settings-general"')
        ->assertSeeHtml('id="settings-network"')
        ->assertSeeHtml('saveSection-settings-network')
        ->assertSee('API token')
        ->assertSee('The token the widget sends')
        ->assertDontSeeHtml('fi-tabs');
});

it('does not list a hidden setting', function (): void {
    demoAddon();

    Livewire::test(AddonSettings::class)
        ->assertFormFieldExists('settings-general.token')
        ->assertFormFieldDoesNotExist('settings-general.cursor');
});

it('saves every section from the page save', function (): void {
    demoAddon();

    Livewire::test(AddonSettings::class)
        ->fillForm(['token' => 'NEW', 'retries' => 8])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(addon_setting('phpvms/demo', 'token'))->toBe('NEW')
        ->and(addon_setting('phpvms/demo', 'retries'))->toBe(8);
});

it('saves one section without writing the others', function (): void {
    demoAddon();

    Livewire::test(AddonSettings::class)
        ->fillForm(['token' => 'NEW', 'retries' => 8])
        ->call('saveGroup', 'network')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(addon_setting('phpvms/demo', 'retries'))->toBe(8)
        ->and(addon_setting('phpvms/demo', 'token'))->toBe('');
});

it('reports a value of the wrong type on its field', function (): void {
    demoAddon();

    Livewire::test(AddonSettings::class)
        ->fillForm(['retries' => 'many'])
        ->call('save')
        ->assertHasFormErrors(['retries']);

    expect(addon_setting('phpvms/demo', 'retries'))->toBe(3);
});

it('refuses to save a section for a user who cannot edit', function (): void {
    demoAddon();
    $this->actingAs(User::factory()->create());

    Livewire::test(AddonSettings::class)
        ->call('saveGroup', 'network')
        ->assertForbidden();
});

it('shows a user who cannot edit the settings without a save', function (): void {
    demoAddon();
    $this->actingAs(User::factory()->create());

    Livewire::test(AddonSettings::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('saveSection-settings-network')
        ->assertFormFieldDisabled('settings-network.retries');
});

it('keeps sections whose names slug alike apart', function (): void {
    demoAddon([DemoUpperFuelSettings::class, DemoLowerFuelSettings::class]);

    $page = Livewire::test(AddonSettings::class)
        ->assertSeeHtml('id="settings-fuel"')
        ->assertSeeHtml('id="settings-fuel-2"');

    // Each section's Save writes its own settings: one class per section.
    $page->fillForm(['lower' => 'LOWER', 'upper' => 'UPPER']);

    $saved = fn (): array => [addon_setting('phpvms/demo', 'upper'), addon_setting('phpvms/demo', 'lower')];

    $page->callAction(TestAction::make('saveSection-settings-fuel')->schemaComponent('settings-fuel'));
    expect(array_intersect($saved(), ['UPPER', 'LOWER']))->toHaveCount(1);

    $page->callAction(TestAction::make('saveSection-settings-fuel-2')->schemaComponent('settings-fuel-2'));
    expect($saved())->toBe(['UPPER', 'LOWER']);
});

it('keeps a numeric section name', function (): void {
    demoAddon([DemoSeasonSettings::class]);

    Livewire::test(AddonSettings::class)
        ->assertSeeHtml('id="settings-2024"')
        ->fillForm(['season' => 'summer'])
        ->call('saveGroup', '2024')
        ->assertHasNoFormErrors();

    expect(addon_setting('phpvms/demo', 'season'))->toBe('summer');
});
