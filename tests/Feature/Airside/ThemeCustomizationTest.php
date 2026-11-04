<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Filament\Pages\Theme as ThemePage;
use App\Models\GlobalSlot;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Igaster\LaravelTheme\Facades\Theme;
use Igaster\LaravelTheme\Theme as ThemeDefinition;

use function Pest\Livewire\livewire;

it('shows the tabs airside registers on the Theme page', function (): void {
    Filament::setCurrentPanel('admin');
    updateSetting('general.theme', 'airside');

    $admin = User::factory()->create();
    foreach (['view:theme', 'edit:theme'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $admin->givePermissionTo($name);
    }

    $this->actingAs($admin->fresh());

    $page = livewire(ThemePage::class)
        ->assertFormSet(['layout' => 'sidebar'])
        ->fillForm(['layout' => 'bogus'])
        ->call('save')
        ->assertHasFormErrors(['layout']);

    expect(array_keys($page->get('data')))->toContain('color_scheme', 'custom_css', 'header', 'footer', 'layout');
});

it('emits a global head row inside head for airside and for a child theme', function (): void {
    $user = useAirside();
    $this->withoutVite();
    new ThemeDefinition('airside-child', null, 'seven', Theme::find('airside'));
    GlobalSlot::factory()->create(['value' => '<meta name="global-probe" content="1">']);

    foreach (['airside', 'airside-child'] as $name) {
        Theme::set($name);
        app()->forgetScopedInstances();

        $html = $this->actingAs($user)->get('/airside-probe/pilot')->getContent();

        expect(strpos($html, 'name="global-probe"'))->toBeInt()
            ->toBeLessThan(strpos($html, '</head>'));
    }
});
