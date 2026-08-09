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
     * Accepts either a plain list of names or a `name => label` map (the label
     * feeds the roles matrix); mirrors {@see PermissionRegistry::registerMany()}.
     *
     * @param array<int|string, string> $scopes
     */
    protected function registerApiScopes(array $scopes, string $group): void
    {
        $registry = app(PermissionRegistry::class);

        foreach ($scopes as $name => $label) {
            if (is_int($name)) {
                $name = $label;
                $label = null;
            }

            $registry->registerApiScope($name, $group, $label);
        }
    }
}
