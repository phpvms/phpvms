<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Services\PermissionRegistry;
use Filament\Panel;

/*
|--------------------------------------------------------------------------
| Module panel access gating
|--------------------------------------------------------------------------
|
| PermissionRegistry derives a panel's module-access key from the *namespace*
| of its registered components (Modules\<Name>\...), so these tests use a fake
| module-namespaced class string. Nothing here depends on a real addon being
| installed — the class only needs to live under the Modules\ tree.
|
*/

// Fake module page — never instantiated; only its namespace is inspected.
const FAKE_MODULE_PAGE = 'Modules\\FakeModule\\Filament\\Pages\\FakePage';

it("derives the module key from a panel's module-namespaced components", function (): void {
    $panel = Panel::make()
        ->id('fakemodule::admin')
        ->pages([FAKE_MODULE_PAGE]);

    expect(app(PermissionRegistry::class)->moduleKeyForPanel($panel))->toBe('fakemodule');
});

it('returns null for a core panel', function (): void {
    $panel = Panel::make()->id('admin');

    expect(app(PermissionRegistry::class)->moduleKeyForPanel($panel))->toBeNull();
});

it('gates a module panel on its access permission', function (): void {
    $panel = Panel::make()
        ->id('fakemodule::admin')
        ->pages([FAKE_MODULE_PAGE]);

    Permission::firstOrCreate(['name' => 'access:fakemodule', 'guard_name' => 'web']);

    $user = User::factory()->create();
    expect($user->canAccessPanel($panel))->toBeFalse();

    $user->givePermissionTo('access:fakemodule');
    expect($user->fresh()->canAccessPanel($panel))->toBeTrue();
});
