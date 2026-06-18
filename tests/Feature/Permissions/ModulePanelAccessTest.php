<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Services\PermissionRegistry;
use Filament\Panel;
use Modules\VMSAcars\Filament\Pages\Rules;

it("derives the module key from a panel's module-namespaced components", function (): void {
    $panel = Panel::make()
        ->id('vmsacars::admin')
        ->pages([Rules::class]);

    expect(app(PermissionRegistry::class)->moduleKeyForPanel($panel))->toBe('vmsacars');
});

it('returns null for a core panel', function (): void {
    $panel = Panel::make()->id('admin');

    expect(app(PermissionRegistry::class)->moduleKeyForPanel($panel))->toBeNull();
});

it('gates a module panel on its access permission', function (): void {
    $panel = Panel::make()
        ->id('vmsacars::admin')
        ->pages([Rules::class]);

    Permission::firstOrCreate(['name' => 'access:vmsacars', 'guard_name' => 'web']);

    $user = User::factory()->create();
    expect($user->canAccessPanel($panel))->toBeFalse();

    $user->givePermissionTo('access:vmsacars');
    expect($user->fresh()->canAccessPanel($panel))->toBeTrue();
});
