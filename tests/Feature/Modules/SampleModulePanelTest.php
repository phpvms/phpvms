<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Modules\Sample\Filament\Resources\SampleResource;
use Modules\Sample\Providers\Filament\SampleAdminPanelProvider;

// The root psr-4 map ("Modules\\": "modules/") only ever resolved Modules\Sample
// because the bundled module happened to sit in a directory literally named
// "Sample". The addon engine is directory-name agnostic — it reads each addon's
// own composer.json psr-4 map — so these tests must boot it the way production
// does: prime the boot cache, then register the addon namespaces. Priming has to
// happen before the first Modules\Sample\* lookup, because Composer's ClassLoader
// memoises misses in $missingClasses and a later addPsr4() will not undo one.
beforeEach(function (): void {
    app(AddonDiscoveryService::class)->run();
    app(AddonAutoLoader::class)->register(app());
});

function samplePanel(): Panel
{
    return new SampleAdminPanelProvider(app())->panel(Panel::make());
}

it('builds the Sample panel at /admin/sample from the base contract', function (): void {
    $panel = samplePanel();

    expect($panel->getId())->toBe('sample')
        ->and($panel->getPath())->toBe('admin/sample');
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('declares its panel provider in the boot cache so the engine registers it', function (): void {
    $sample = app(BootCache::class)->all()
        ->firstWhere(fn ($entry): bool => $entry->namespace === 'Modules\\Sample');

    expect($sample)->not->toBeNull()
        ->and($sample->providers)->toContain(SampleAdminPanelProvider::class);
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('does not register the Sample resource on the main admin panel', function (): void {
    $adminResources = Filament::getPanel('admin')->getResources();

    expect($adminResources)->not->toContain(SampleResource::class);
});

it('admits a user holding the per-module access permission', function (): void {
    Permission::firstOrCreate(['name' => 'access:sample', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('access:sample');

    expect($user->fresh()->canAccessPanel(samplePanel()))->toBeTrue();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('admits a user via the legacy view:modules fallback', function (): void {
    Permission::firstOrCreate(['name' => 'view:modules', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('view:modules');

    expect($user->fresh()->canAccessPanel(samplePanel()))->toBeTrue();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('admits a super admin', function (): void {
    $role = Role::firstOrCreate(['name' => Role::superAdminName(), 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->fresh()->canAccessPanel(samplePanel()))->toBeTrue();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('denies a user without access:sample, view:modules, or super admin', function (): void {
    $user = User::factory()->create();

    expect($user->canAccessPanel(samplePanel()))->toBeFalse();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');
