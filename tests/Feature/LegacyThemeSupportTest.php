<?php

declare(strict_types=1);

use App\Features\Themes\Actions\FindDeprecatedThemeUsage;
use App\Filament\Pages\Theme as ThemePage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\LegacyModuleLinks;
use Filament\Facades\Filament;
use Igaster\LaravelTheme\Facades\Theme;
use Igaster\LaravelTheme\Theme as ThemeDefinition;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

test('a theme without a view falls back to seven for it', function (): void {
    Theme::set('a-7x-theme-without-profile-views');

    expect(View::exists('profile.connections'))->toBeTrue();
});

test('a 7.x theme reads back the frontend links added through $moduleSvc', function (): void {
    $links = View::shared('moduleSvc');
    $links->addFrontendLink('Tours', '/tours', 'fas fa-map', true);
    $links->addFrontendLink('Apply', '/apply', '', false);
    $links->addAdminLink('Tours', '/admin/tours');

    expect($links)->toBeInstanceOf(LegacyModuleLinks::class)
        ->and(array_column($links->getFrontendLinks(true), 'url'))->toBe(['/tours'])
        ->and(array_column($links->getFrontendLinks(false), 'url'))->toBe(['/apply']);
});

test('a 7.x admin-access check follows the access_admin gate', function (): void {
    $gate = "@ability('admin', 'admin-access') yes @else no @endability";
    $staff = Role::create(['name' => 'staff', 'guard_name' => 'web']);
    $staff->givePermissionTo(Permission::firstOrCreate(['name' => 'view:dashboard', 'guard_name' => 'web']));

    $admin = User::factory()->create()->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));
    $viaPermission = User::factory()->create()->assignRole($staff);
    $pilot = User::factory()->create();

    actingAs($admin);
    expect(trim(Blade::render($gate)))->toBe('yes');
    actingAs($viaPermission);
    expect(trim(Blade::render($gate)))->toBe('yes');
    actingAs($pilot);
    expect(trim(Blade::render($gate)))->toBe('no');
});

test('a 7.x @ability check uses the 8.x permissions, not the role, when the permission maps', function (): void {
    $admin = User::factory()->create()->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));
    actingAs($admin);

    // The admin role has no user permissions here; admin-user never shipped in 7.x, so the role decides.
    expect(trim(Blade::render("@ability('admin', 'users') yes @else no @endability")))->toBe('no')
        ->and(trim(Blade::render("@ability('admin', 'admin-user') yes @else no @endability")))->toBe('yes');

    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'edit:user', 'guard_name' => 'web']));
    expect(trim(Blade::render("@ability('admin', 'users') yes @else no @endability")))->toBe('yes');
});

test('the theme page warns when the selected theme still uses 7.x shims', function (): void {
    Filament::setCurrentPanel('admin');
    new ThemeDefinition('legacy-7x', null, '/tests/fixtures/themes/legacy-7x');
    updateSetting('general.theme', 'legacy-7x');

    $admin = User::factory()->create();
    foreach (['view:theme', 'edit:theme'] as $name) {
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }

    actingAs($admin);

    expect(app(FindDeprecatedThemeUsage::class)('legacy-7x'))->toBe(['$moduleSvc', '@ability'])
        ->and(app(FindDeprecatedThemeUsage::class)('seven'))->toBe([]);

    livewire(ThemePage::class)
        ->assertSee(__('filament.theme_deprecated'))
        ->fillForm(['theme' => 'seven'])
        ->assertDontSee(__('filament.theme_deprecated'));
});
