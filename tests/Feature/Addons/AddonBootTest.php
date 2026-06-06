<?php

declare(strict_types=1);

use App\Addons\BootCache;
use App\Addons\Compat\ModuleRepository;
use App\Models\Addon;
use App\Providers\AddonServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ModulesServiceProvider;
use Illuminate\Support\Collection;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\LaravelModulesServiceProvider;

// imported only for the negative "absent from providers.php" assertion below

// ─────────────────────────────────────────────────────────────────────────────
// Task 6: Cutover — nwidart loader retired, addon engine is live
// ─────────────────────────────────────────────────────────────────────────────

it('ModulesServiceProvider is NOT in bootstrap/providers.php', function (): void {
    $providers = require base_path('bootstrap/providers.php');

    expect(array_search(ModulesServiceProvider::class, $providers, true))
        ->toBeFalse('ModulesServiceProvider must be removed from providers list');
});

it("nwidart's base provider is NOT in the loaded providers", function (): void {
    expect(app()->getLoadedProviders())
        ->not->toHaveKey(LaravelModulesServiceProvider::class,
            'nwidart LaravelModulesServiceProvider must not be loaded (dont-discover + removal)');
});

it('AddonServiceProvider is loaded and comes before AdminPanelProvider', function (): void {
    $providers = require base_path('bootstrap/providers.php');

    $addonIdx = array_search(AddonServiceProvider::class, $providers, true);
    $adminIdx = array_search(AdminPanelProvider::class, $providers, true);

    expect($addonIdx)->not->toBeFalse('AddonServiceProvider not in providers list')
        ->and($adminIdx)->not->toBeFalse('AdminPanelProvider not in providers list')
        ->and($addonIdx)->toBeLessThan($adminIdx, 'AddonServiceProvider must come before AdminPanelProvider');
});

it('AddonServiceProvider is loaded by the application', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(AddonServiceProvider::class);
});

it("'modules' binding resolves to ModuleRepository", function (): void {
    expect(app('modules'))->toBeInstanceOf(ModuleRepository::class);
});

it("Module facade resolves through ModuleRepository and returns a collection containing 'Sample'", function (): void {
    // Prime so the addons table is populated.
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    $all = Module::all();

    expect($all)->toBeInstanceOf(Collection::class)
        ->and($all->keys()->toArray())->toContain('Sample');
});

it('provider does not auto-prime the boot cache in console context (D-17)', function (): void {
    // Ensure the cache is absent before the test.
    app(BootCache::class)->delete();

    // In console (artisan test) context, runningInConsole() is true.
    // register() must NOT create the boot cache when running in console.
    $provider = new AddonServiceProvider(app());
    $provider->register();

    expect(app(BootCache::class)->exists())
        ->toBeFalse('boot cache must not be created in console context (D-17)');
});

// ─────────────────────────────────────────────────────────────────────────────
// Task 2: phpvms:addons-prime command
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    app(BootCache::class)->delete();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

it('phpvms:addons-prime writes the boot cache and registers bundled addons', function (): void {
    expect(app(BootCache::class)->exists())->toBeFalse('cache should be absent before the command');

    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    expect(app(BootCache::class)->exists())->toBeTrue('boot cache must exist after prime command')
        ->and(Addon::query()->count())->toBeGreaterThan(0, 'bundled addons must be registered');
});

it('phpvms:addons-prime --force re-primes without duplicating rows', function (): void {
    // First prime — populates DB and cache.
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    $countAfterFirst = Addon::query()->count();

    // Second prime with --force — must be idempotent.
    $this->artisan('phpvms:addons-prime', ['--force' => true])->assertSuccessful();

    expect(Addon::query()->count())->toBe($countAfterFirst, '--force re-prime must not duplicate rows');
});
