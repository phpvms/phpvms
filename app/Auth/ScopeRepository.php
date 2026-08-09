<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Services\PermissionRegistry;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Override;

/**
 * Bridges permission-backed OAuth scopes (declared via
 * {@see PermissionRegistry::registerApiScope()}) to Spatie permissions.
 *
 * Passport, by default, grants any catalog scope a client requests + the user
 * consents to. This override adds a per-user gate at token issuance: a scope
 * registered as an API scope survives only when the user holds the
 * same-named permission. Scopes outside the registry (the legacy `ApiScope`
 * catalog) are left exactly as Passport finalized them, untouched.
 *
 * Uses `$user->can($scope)` (not `hasPermissionTo`) so the super-admin
 * `Gate::before` bypass (`AppServiceProvider::boot()`) applies — a
 * super-admin gets every API scope without an explicit grant. The granted
 * set is what Passport bakes into the token, so consumers enforce it via
 * `tokenCan()`/`scope:` route middleware.
 */
final class ScopeRepository extends PassportScopeRepository
{
    #[Override]
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        // Passport's own validation first: catalog membership, client allow-list,
        // wildcard handling.
        $scopes = parent::finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier, $authCodeId);

        $apiScopes = app(PermissionRegistry::class)->apiScopes();

        // No user (e.g. client_credentials): no per-user API scopes to grant.
        $user = $userIdentifier === null ? null : User::find($userIdentifier);

        return array_values(array_filter(
            $scopes,
            function (ScopeEntityInterface $scope) use ($apiScopes, $user): bool {
                $id = $scope->getIdentifier();

                if (!in_array($id, $apiScopes, true)) {
                    return true; // legacy ApiScope / non-API scope → leave as Passport finalized it
                }

                return $user !== null && $user->can($id);
            }
        ));
    }
}
