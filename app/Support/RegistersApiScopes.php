<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\PermissionRegistry;

/**
 * One-line declaration of permission-backed OAuth scopes from a provider's
 * `boot()`. Forwards each name to {@see PermissionRegistry::registerApiScope()},
 * which records it as both an assignable permission and an OAuth-exposable
 * scope. Shared by `App\Contracts\Modules\ServiceProvider` (addon-authored
 * providers) and `App\Providers\AddonServiceProvider` (the core addon engine).
 */
trait RegistersApiScopes
{
    /**
     * Declare permission-backed API scopes under one group.
     *
     * @param list<string> $names
     */
    protected function registerApiScopes(array $names, string $group): void
    {
        $registry = app(PermissionRegistry::class);

        foreach ($names as $name) {
            $registry->registerApiScope($name, $group);
        }
    }
}
