<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('saves only the category whose section save was used', function (): void {
    setting_save('general.site_name', 'Before A');
    setting_save('units.distance', 'km');

    Livewire::test(Settings::class)
        ->set('data.general.site_name', 'After A')
        ->set('data.units.distance', 'nmi')
        ->callAction(TestAction::make('saveSection-settings-general')->schemaComponent('settings-general', schema: 'form'));

    expect(setting('general.site_name'))->toBe('After A')
        ->and(setting('units.distance'))->toBe('km');
});
