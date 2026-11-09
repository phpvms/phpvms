<?php

declare(strict_types=1);

use App\Contracts\Addons\AddonServiceProvider as AddonBaseProvider;
use App\Providers\AddonServiceProvider;
use App\Providers\PassportServiceProvider;
use App\Services\PermissionRegistry;
use App\Support\ApiScope;
use App\Support\Permissions\Scope;
use Laravel\Passport\Passport;

/*
 * Slice 3: the catalog merge (PassportServiceProvider::boot()) and the
 * registerScopes() addon-provider declaration (design.md §4).
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

it('applies the API scopes an addon provider returns from registerScopes()', function (): void {
    $provider = new class(app()) extends AddonBaseProvider
    {
        public function registerScopes(): array
        {
            return [new Scope('x:module-scope', 'ModuleGroup', 'Nice Label'), new Scope('x:default-scope')];
        }
    };

    $provider->boot();

    $registry = app(PermissionRegistry::class);
    $permissions = collect($registry->grouped())
        ->flatMap(fn (array $group): array => $group['permissions'])
        ->keyBy('name');

    expect($registry->apiScopes())->toContain('x:module-scope', 'x:default-scope')
        ->and($registry->all())->toContain('x:module-scope')
        ->and($permissions['x:module-scope']['label'])->toBe('Nice Label')
        ->and(collect($registry->grouped())->pluck('label'))->toContain('ModuleGroup');
});

/**
 * @return list<mixed>
 */
function mixedList(mixed ...$items): array
{
    return array_values($items);
}

it('registers no scope when registerScopes() holds a non-Scope entry', function (): void {
    $provider = new class(app()) extends AddonBaseProvider
    {
        public function registerScopes(): array
        {
            return mixedList(new Scope('x:valid-scope'), 'x:legacy-scope');
        }
    };

    $provider->boot();

    expect(app(PermissionRegistry::class)->apiScopes())->not->toContain('x:valid-scope', 'x:legacy-scope');
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
