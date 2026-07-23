<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Services\PermissionRegistry;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Override;
use SensitiveParameter;

/**
 * Bearer token response that also returns the resource owner's roles and
 * effective permissions alongside the standard OAuth2 fields.
 *
 * RFC 6749 §5.1 permits additional parameters in the token response. Emitting the
 * pilot's RBAC here means the device-code client (vmsACARS) receives its granted
 * scopes AND the pilot's capabilities in the single token exchange, rather than a
 * second /api/user round trip that would sit outside the OAuth flow.
 *
 * Permissions are evaluated via can() so the super-admin Gate::before bypass is
 * reflected (a super-admin gets every registered permission without being
 * assigned one). Tokens with no resource owner (client-credentials) add nothing.
 *
 * Registered via Passport::useAuthorizationServerResponseType() — see
 * PassportServiceProvider. League clones this instance per request and injects
 * the signing keys, so the base token generation is unaffected.
 */
class RichBearerTokenResponse extends BearerTokenResponse
{
    #[Override]
    protected function getExtraParams(
        #[SensitiveParameter]
        AccessTokenEntityInterface $accessToken
    ): array {
        $userId = $accessToken->getUserIdentifier();

        if ($userId === null) {
            return [];
        }

        $user = User::find($userId);

        if (!$user instanceof User) {
            return [];
        }

        return [
            'roles'       => $user->getRoleNames()->values()->all(),
            'permissions' => collect(array_keys(app(PermissionRegistry::class)->all()))
                ->filter(static fn (string $permission): bool => $user->can($permission))
                ->values()
                ->all(),
        ];
    }
}
