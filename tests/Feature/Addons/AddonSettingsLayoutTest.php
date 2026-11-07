<?php

declare(strict_types=1);

use App\Filament\Pages\AddonSettings;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;

/*
 * The addon settings page shares the core Settings layout: a searchable group
 * nav beside one section per group, each section saving on its own.
 */

beforeEach(function (): void {
    // The panel id resolves to the addon by name (or its manifest alias).
    $this->addon = Addon::factory()->create(['name' => 'demo', 'registry_id' => 'phpvms/demo']);

    $setting = fn (string $key, string $group, string $value, string $type = 'text'): AddonSetting => AddonSetting::factory()->create([
        'registry_id' => $this->addon->registry_id,
        'key'         => AddonSetting::formatKey($key),
        'group'       => $group,
        'type'        => $type,
        'value'       => $value,
        'default'     => $value,
    ]);

    $setting('general.callsign', 'general', 'DMO');
    $setting('fuel.margin', 'fuel', '5', 'int');
    $setting('fuel.enabled', 'fuel', '1', 'boolean');

    Permission::firstOrCreate(['name' => 'edit:addon-settings', 'guard_name' => 'web']);
    $editor = User::factory()->create();
    $editor->givePermissionTo('edit:addon-settings');
    $this->actingAs($editor);
    // Registered, not only set: Livewire's update requests restore the panel by id.
    $panel = Panel::make()->id('demo')->path('admin/demo');
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
});

function demoSetting(string $key): mixed
{
    return AddonSetting::query()
        ->where('registry_id', 'phpvms/demo')
        ->where('key', AddonSetting::formatKey($key))
        ->value('value');
}

it('lays the settings out like the core settings page', function (): void {
    Livewire::test(AddonSettings::class)
        ->assertSuccessful()
        ->assertSeeHtml('settings-page')
        ->assertSeeHtml('settings-nav')
        ->assertSeeHtml('id="settings-general"')
        ->assertSeeHtml('id="settings-fuel"')
        ->assertSeeHtml('saveSection-settings-fuel')
        ->assertDontSeeHtml('fi-tabs');
});

it('saves every group from the page save', function (): void {
    Livewire::test(AddonSettings::class)
        ->fillForm(['general_callsign' => 'NEW', 'fuel_margin' => 8])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(demoSetting('general.callsign'))->toBe('NEW')
        ->and(demoSetting('fuel.margin'))->toBe('8');
});

it('saves one group without writing the others', function (): void {
    Livewire::test(AddonSettings::class)
        ->fillForm(['general_callsign' => 'NEW', 'fuel_margin' => 8])
        ->call('saveGroup', 'fuel')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(demoSetting('fuel.margin'))->toBe('8')
        ->and(demoSetting('general.callsign'))->toBe('DMO');
});

it('refuses to save a group for a user who cannot edit', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(AddonSettings::class)
        ->call('saveGroup', 'fuel')
        ->assertForbidden();
});

it('shows a user who cannot edit the settings without a save', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(AddonSettings::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('saveSection-settings-fuel')
        ->assertFormFieldDisabled('settings-fuel.fuel_margin');
});
