<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Filament\Panel;
use Modules\Sample\Filament\Resources\SampleResource;
use Modules\Sample\Providers\Filament\SampleAdminPanelProvider;

function samplePanel(): Panel
{
    return new SampleAdminPanelProvider(app())->panel(Panel::make());
}

it('builds the Sample panel at /admin/sample from the base contract', function (): void {
    $panel = samplePanel();

    expect($panel->getId())->toBe('sample')
        ->and($panel->getPath())->toBe('admin/sample');
});

it('declares its panel provider in the boot cache so the engine registers it', function (): void {
    app(AddonDiscoveryService::class)->run();

    $sample = app(BootCache::class)->all()
        ->firstWhere(fn ($entry): bool => $entry->namespace === 'Modules\\Sample');

    expect($sample)->not->toBeNull()
        ->and($sample->providers)->toContain(SampleAdminPanelProvider::class);
});

it('does not register the Sample resource on the main admin panel', function (): void {
    $adminResources = Filament::getPanel('admin')->getResources();

    expect($adminResources)->not->toContain(SampleResource::class);
});

it('admits a user holding the per-module access permission', function (): void {
    Permission::create(['name' => 'access:sample', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('access:sample');

    expect($user->fresh()->canAccessPanel(samplePanel()))->toBeTrue();
});

it('admits a user via the legacy view:modules fallback', function (): void {
    Permission::create(['name' => 'view:modules', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('view:modules');

    expect($user->fresh()->canAccessPanel(samplePanel()))->toBeTrue();
});

it('admits a super admin', function (): void {
    $role = Role::create(['name' => Utils::getSuperAdminName(), 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->fresh()->canAccessPanel(samplePanel()))->toBeTrue();
});

it('denies a user without access:sample, view:modules, or super admin', function (): void {
    $user = User::factory()->create();

    expect($user->canAccessPanel(samplePanel()))->toBeFalse();
});
