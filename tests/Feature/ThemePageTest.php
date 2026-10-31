<?php

declare(strict_types=1);

use App\Enums\NavigationGroup;
use App\Features\Themes\ThemeFields;
use App\Filament\Pages\Theme;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\ThemeSetting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
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

it('is discovered on the admin panel under Content', function (): void {
    expect(Filament::getPanel('admin')->getPages())->toContain(Theme::class)
        ->and(Theme::getNavigationGroup())->toBe(NavigationGroup::Content);
});

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

it('frames the pilot dashboard under the selected theme as a preview', function (): void {
    $this->actingAs(themeAdmin('view:theme'));

    livewire(Theme::class)
        ->assertSeeHtml('<iframe')
        ->assertSeeHtml('?${this.param}=')
        ->assertSeeHtml(str_replace('/', '\\/', route('frontend.dashboard.index')))
        ->assertSeeHtml('role="tablist"')
        ->assertSee(__('filament.theme_customize_tab'));
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

it('seeds general.theme as a hidden row and hides an existing one by data migration', function (): void {
    $id = Setting::formatKey('general.theme');

    DB::table('settings')->where('id', $id)->update(['type' => 'select']);
    $migration = require base_path('database/migrations_data/2026_10_30_000000_hide_theme_setting.php');
    $migration->up();

    expect(DB::table('settings')->where('id', $id)->value('type'))->toBe('hidden');

    DB::table('settings')->where('id', $id)->delete();
    app(SettingsSeeder::class)->run();

    expect(DB::table('settings')->where('id', $id)->value('type'))->toBe('hidden');
});

it('shows the shipped css when the theme has no override', function (): void {
    updateSetting('general.theme', 'airside');
    $this->actingAs(themeAdmin('view:theme'));

    livewire(Theme::class)
        ->assertFormSet(['css' => app(ThemeFields::class)->defaultFor('airside', 'css')]);
});

it('stores custom css as a theme override', function (): void {
    updateSetting('general.theme', 'airside');
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['theme' => 'airside', 'css' => ':root{--color-primary-600:#ff0000}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ThemeSetting::query()->where('theme', 'airside')->where('name', 'css')->value('content'))
        ->toBe(':root{--color-primary-600:#ff0000}');
});

it('stores no row when the shipped css is saved back', function (): void {
    updateSetting('general.theme', 'airside');
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['theme' => 'airside', 'css' => app(ThemeFields::class)->defaultFor('airside', 'css')])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ThemeSetting::query()->count())->toBe(0);
});

it('hides the reset action when no override exists', function (): void {
    updateSetting('general.theme', 'airside');
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)->assertActionDoesNotExist(TestAction::make('resetCss')->schemaComponent('customize'));
});

it('shows the reset action when an override exists', function (): void {
    updateSetting('general.theme', 'airside');
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'a{}']);
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)->assertActionVisible(TestAction::make('resetCss')->schemaComponent('customize'));
});

it('removes the override and refills the default on reset', function (): void {
    updateSetting('general.theme', 'airside');
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'a{}']);
    $this->actingAs(themeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->callAction(TestAction::make('resetCss')->schemaComponent('customize'))
        ->assertNotified()
        ->assertFormSet(['theme' => 'airside', 'css' => app(ThemeFields::class)->defaultFor('airside', 'css')]);

    expect(ThemeSetting::query()->count())->toBe(0);
});
