<?php

declare(strict_types=1);

use App\Contracts\Modules\ServiceProvider as ModuleServiceProvider;
use App\Providers\AddonServiceProvider;
use App\Providers\PassportServiceProvider;
use App\Services\PermissionRegistry;
use App\Support\ApiScope;
use Laravel\Passport\Passport;

/*
 * Slice 3: the catalog merge (PassportServiceProvider::boot()) and the
 * registerApiScopes() base-provider helper (design.md §4).
 */

it('merges registered API scopes into the Passport catalog without clobbering legacy ApiScope entries', function (): void {
    app(PermissionRegistry::class)->registerApiScope('x:test', 'API');

    // PassportServiceProvider defers the catalog merge to an app()->booted()
    // callback. The test app is already booted, so re-running boot() both
    // registers and immediately fires that callback (Application::booted()
    // fires straightaway once isBooted() is true). Providers are instantiated
    // directly (not container-resolved), matching how Laravel boots them.
    new PassportServiceProvider(app())->boot();

    expect(Passport::hasScope('x:test'))->toBeTrue();
    expect(Passport::hasScope(ApiScope::UserRead->value))->toBeTrue();
});

it('gives App\\Contracts\\Modules\\ServiceProvider a registerApiScopes() helper that forwards to the registry', function (): void {
    $app = app();
    $provider = new class($app) extends ModuleServiceProvider
    {
        public function boot(): void
        {
            $this->registerApiScopes(['x:module-scope'], 'ModuleGroup');
        }
    };

    $provider->boot();

    $registry = app(PermissionRegistry::class);
    expect($registry->apiScopes())->toContain('x:module-scope');
    expect($registry->all())->toContain('x:module-scope');
});

it('preserves an explicit label when registerApiScopes is given a name => label map', function (): void {
    $app = app();
    $provider = new class($app) extends ModuleServiceProvider
    {
        public function boot(): void
        {
            $this->registerApiScopes(['x:labelled' => 'Nice Label'], 'ModuleGroup');
        }
    };

    $provider->boot();

    $labels = collect(app(PermissionRegistry::class)->grouped())
        ->flatMap(fn (array $group): array => $group['permissions'])
        ->keyBy('name');

    expect($labels->has('x:labelled'))->toBeTrue()
        ->and($labels['x:labelled']['label'])->toBe('Nice Label');
});

it('gives App\\Providers\\AddonServiceProvider a registerApiScopes() helper that forwards to the registry', function (): void {
    $provider = new AddonServiceProvider(app());

    // registerApiScopes() is a protected forwarding helper — invoke via a
    // bound closure the way the provider itself would call it internally.
    Closure::bind(function (): void {
        $this->registerApiScopes(['x:addon-scope'], 'AddonGroup');
    }, $provider, AddonServiceProvider::class)();

    $registry = app(PermissionRegistry::class);
    expect($registry->apiScopes())->toContain('x:addon-scope');
    expect($registry->all())->toContain('x:addon-scope');
});
