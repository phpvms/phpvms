<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Jobs\ChangeJournalCurrencies;
use App\Models\Permission;
use App\Models\User;
use App\Settings\UnitsSettings;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SavingSettings;

/**
 * The core Settings page edits every visible setting, one section per group.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('shows visible settings with their stored values and hides hidden ones', function (): void {
    setting_save('general.site_name', 'Lunair');
    setting_save('units.weight', 'kg');

    Livewire::test(Settings::class)
        ->assertSuccessful()
        ->assertSet('data.general.site_name', 'Lunair')
        ->assertSet('data.units.weight', 'kg')
        ->assertFormFieldExists('settings-general.general.site_name')
        ->assertFormFieldDoesNotExist('settings-general.general.theme')
        ->assertDontSeeHtml('id="settings-registry"')
        ->assertDontSeeHtml('id="settings-branding"');
});

it('shows a never-saved setting at its default', function (): void {
    Livewire::test(Settings::class)
        ->assertSet('data.livemap.default_zoom', 5);
});

it('shows a secret as its plain value for editing', function (): void {
    setting_save('captcha.secret_key', 'hunter2');

    Livewire::test(Settings::class)
        ->assertSet('data.captcha.secret_key', 'hunter2');
});

it('saves the whole page', function (): void {
    Livewire::test(Settings::class)
        ->set('data.general.site_name', 'Saved')
        ->set('data.units.weight', 'kg')
        ->set('data.pilots.id_length', 6)
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved successfully');

    expect(setting('general.site_name'))->toBe('Saved')
        ->and(setting('units.weight'))->toBe('kg')
        ->and(setting('pilots.id_length'))->toBe(6);
});

it('clears a text setting emptied on the page', function (): void {
    setting_save('general.google_analytics_id', 'G-123');

    Livewire::test(Settings::class)
        ->set('data.general.google_analytics_id', '')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(setting('general.google_analytics_id'))->toBe('');
});

it('reports a negative bid expiry on its field and saves nothing', function (): void {
    Livewire::test(Settings::class)
        ->set('data.general.site_name', 'Not saved')
        ->set('data.bids.expire_time', -1)
        ->call('save')
        ->assertHasFormErrors(['bids.expire_time']);

    expect(setting('general.site_name'))->toBe('')
        ->and(setting('bids.expire_time'))->toBe(48);
});

it('rejects an option that is not offered', function (): void {
    Livewire::test(Settings::class)
        ->set('data.units.weight', 'stone')
        ->call('save')
        ->assertHasFormErrors(['units.weight']);

    expect(setting('units.weight'))->toBe('lbs');
});

it('writes nothing when a save fails part way', function (): void {
    // The units group saves after general; fail it.
    Event::listen(SavingSettings::class, function (SavingSettings $event): void {
        if ($event->settings instanceof UnitsSettings) {
            throw new RuntimeException('units failed');
        }
    });

    expect(fn () => Livewire::test(Settings::class)
        ->set('data.general.site_name', 'Half saved')
        ->set('data.units.weight', 'kg')
        ->call('save'))->toThrow(RuntimeException::class);

    reloadSettings();

    expect(setting('general.site_name'))->toBe('')
        ->and(setting('units.weight'))->toBe('lbs');
});

it('announces a currency change in the notification', function (): void {
    Queue::fake();

    Livewire::test(Settings::class)
        ->set('data.units.currency', 'EUR')
        ->callAction(TestAction::make('saveSection-settings-units')->schemaComponent('settings-units', schema: 'form'))
        ->assertNotified();

    Queue::assertPushed(ChangeJournalCurrencies::class, 1);
});

it('refuses to save for a user who may view but not edit the settings', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'view:settings', 'guard_name' => 'web']));
    $this->actingAs($viewer);

    Livewire::test(Settings::class)
        ->set('data.general.site_name', 'Hijacked')
        ->call('save')
        ->assertForbidden();

    Livewire::test(Settings::class)
        ->call('saveGroup', 'general')
        ->assertForbidden();

    expect(setting('general.site_name'))->toBe('');
});
