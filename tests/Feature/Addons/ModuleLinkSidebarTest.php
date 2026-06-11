<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Enums\NavigationGroup;
use App\Services\ModuleService;
use Database\Seeders\ShieldSeeder;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());

    app(BootCache::class)->delete();
    app(AddonDiscoveryService::class)->run();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

it('registers legacy addAdminLink() links as native sidebar items under AddOns', function (): void {
    $panel = Filament::getPanel('admin');

    app(ModuleService::class)->addAdminLink('LegacyNavProbe', '/admin/legacy-nav-probe', 'bi bi-box');

    event(new ServingFilament());

    $match = collect($panel->getNavigationItems())
        ->first(fn ($item): bool => $item->getLabel() === 'LegacyNavProbe');

    expect($match)->not->toBeNull()
        ->and($match->getUrl())->toBe('/admin/legacy-nav-probe')
        ->and($match->getGroup())->toBe(NavigationGroup::AddOns);
});

it('does not duplicate legacy links across repeated serving (Octane-safe)', function (): void {
    $panel = Filament::getPanel('admin');

    app(ModuleService::class)->addAdminLink('LegacyNavProbe', '/admin/legacy-nav-probe', 'bi bi-box');

    event(new ServingFilament());
    event(new ServingFilament());
    event(new ServingFilament());

    $count = collect($panel->getNavigationItems())
        ->filter(fn ($item): bool => $item->getLabel() === 'LegacyNavProbe')
        ->count();

    expect($count)->toBe(1);
});

it('excludes the Sample legacy link because it ships a Filament resource', function (): void {
    $panel = Filament::getPanel('admin');

    app(ModuleService::class)->addAdminLink('Sample', '/admin/sample', 'bi bi-note');

    event(new ServingFilament());

    $sampleLegacy = collect($panel->getNavigationItems())
        ->first(fn ($item): bool => $item->getLabel() === 'Sample' && $item->getUrl() === '/admin/sample');

    expect($sampleLegacy)->toBeNull();
});
