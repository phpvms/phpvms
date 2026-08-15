<?php

declare(strict_types=1);

use App\Http\Middleware\UpdatePending;
use App\Models\Role;
use App\Models\User;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Facade;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Group 5 ("theme picker split") moved brand colour off the topbar picker
 * and onto `AdminPanelProvider::colors()` as a closure sourced from
 * `App\Support\Branding::brandColor()`. These tests cover the three
 * guarantees `specs/branding-admin-page/spec.md`'s "Theme picker is split"
 * requirement makes.
 */
beforeEach(function (): void {
    $this->withoutMiddleware(UpdatePending::class);

    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user);
});

it('reflects a brand colour change on the next request without an Octane worker restart', function (): void {
    updateSetting('branding.brand_color', '#4f46e5');

    $first = get('/admin')->assertOk();
    $firstPrimary600 = firstPrimary600($first->getContent());

    expect($firstPrimary600)->toBe(Color::generatePalette('#4f46e5')[600]);

    updateSetting('branding.brand_color', '#e11d48');

    // Simulates the container reset Octane performs between requests on the
    // same worker:
    //   - Worker::handle() clones the app and calls CurrentApplication::set(),
    //     which calls Facade::clearResolvedInstances() (vendor/laravel/octane/src/CurrentApplication.php:21) --
    //     needed because Facade caches its resolved instance in a static
    //     property that forgetScopedInstances() alone does not touch, and
    //     FilamentColor::register() resolves ColorManager through the facade.
    //   - Laravel\Octane\Listeners\FlushTemporaryContainerInstances, on every
    //     RequestTerminated event, calls forgetScopedInstances() (rebuilding
    //     Filament's `scoped`-bound ColorManager/FilamentManager) and forgets
    //     every config('octane.flush') binding (rebuilding SettingService,
    //     whose memo `setting()` reads through).
    Facade::clearResolvedInstances();
    app()->forgetScopedInstances();
    foreach (config('octane.flush', []) as $binding) {
        app()->forgetInstance($binding);
    }

    $second = get('/admin')->assertOk();
    $secondPrimary600 = firstPrimary600($second->getContent());

    expect($secondPrimary600)
        ->toBe(Color::generatePalette('#e11d48')[600])
        ->not->toBe($firstPrimary600);
});

it('no longer exposes brand-colour swatches or a hex input in the topbar picker', function (): void {
    get('/admin')
        ->assertOk()
        ->assertDontSee('fi-picker-hex', escape: false)
        ->assertDontSee('data-hex-color', escape: false)
        ->assertDontSee('data-preset', escape: false);
});

it('still renders appearance and density controls in the topbar picker', function (): void {
    get('/admin')
        ->assertOk()
        ->assertSee('data-mode="light"', escape: false)
        ->assertSee('data-mode="dark"', escape: false)
        ->assertSee('data-density="compact"', escape: false)
        ->assertSee('data-density="comfortable"', escape: false);
});

// A stale `brand` key in localStorage is client-side state this PHP test
// suite can't observe -- covered instead by
// resources/js/apps/admin/theme-picker.test.js "ignores a stale brand colour
// already sitting in localStorage" (vitest), which fresh-imports the module
// against a seeded store and asserts it never reaches --primary-600.

/**
 * Pulls `--primary-600:<value>;` out of the inline `:root{...}` style block
 * Filament's asset renderer emits (vendor/filament/support/resources/views/assets.blade.php),
 * which is what both the old JS and the new ->colors() closure ultimately feed.
 */
function firstPrimary600(string $html): string
{
    preg_match('/--primary-600:([^;]+);/', $html, $matches);

    return $matches[1] ?? '';
}
