<?php

declare(strict_types=1);

use App\Filament\Pages\Theme;
use App\Models\Permission;
use App\Models\User;
use App\Support\Settings\SettingsRegistry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

/**
 * Content > Theme owns `general.theme`; the Settings page no longer shows it.
 */
beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function themeAdmin(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('saves the selected theme', function (): void {
    updateSetting('general.theme', 'seven');
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->assertFormSet(['theme' => 'seven'])
        ->fillForm(['theme' => 'airside'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament.theme_saved'));

    expect(setting('general.theme'))->toBe('airside');
});

it('rejects a theme that is not installed', function (): void {
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['theme' => 'does-not-exist'])
        ->call('save')
        ->assertHasFormErrors(['theme']);
});

it('blocks saving with only the view permission', function (): void {
    $this->actingAs(themeAdmin('view:theme'));

    livewire(Theme::class)
        ->fillForm(['theme' => 'airside'])
        ->call('save')
        ->assertForbidden();
});

it('declares general.theme hidden and hides an existing backup row by data migration', function (): void {
    DB::table('settings_backup')->insert([
        'id'    => 'general_theme',
        'key'   => 'general.theme',
        'name'  => 'Current Theme',
        'value' => 'airside',
        'group' => 'general',
        'type'  => 'select',
    ]);

    $migration = require base_path('database/migrations_data/2026_10_30_000000_hide_theme_setting.php');
    $migration->up();

    expect(DB::table('settings_backup')->where('id', 'general_theme')->value('type'))->toBe('hidden')
        ->and(app(SettingsRegistry::class)->find('general.theme')->hidden)->toBeTrue();
});

it('hides the reset action when no override exists', function (): void {
    updateSetting('general.theme', 'airside');
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)->assertActionDoesNotExist(TestAction::make('resetField')->schemaComponent('customize'));
});
